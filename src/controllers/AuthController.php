<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

class AuthController
{
    public function showLogin(): array
    {
        return [
            'view'  => 'login',
            'title' => 'Iniciar sesión',
            'data'  => [],
        ];
    }

    public function showRegister(): array
    {
        return [
            'view'  => 'register',
            'title' => 'Crear cuenta',
            'data'  => [],
        ];
    }

    public function handleLoginRequest(array $input): void
    {
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            $_SESSION['flash_error'] = 'Debes completar email y contraseña';
            header('Location: ' . url('login'));
            exit;
        }

        try {
            $usuario = queryOne(dbMatriz(), '
                SELECT id, email, password, rol
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ', [':email' => $email]);

            if (!$usuario || !password_verify($password, (string) $usuario['password'])) {
                $_SESSION['flash_error'] = 'Credenciales inválidas';
                header('Location: ' . url('login'));
                exit;
            }

            $cliente = queryOne(dbMatriz(), '
                SELECT id, nombre, apellido, rut, telefono
                FROM clientes
                WHERE usuario_id = :uid
                LIMIT 1
            ', [':uid' => $usuario['id']]);

            if (!$cliente) {
                $_SESSION['flash_error'] = 'No existe perfil de cliente para este usuario';
                header('Location: ' . url('login'));
                exit;
            }

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['rol'] = $usuario['rol'] ?? 'cliente';
            $_SESSION['nombre'] = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellido'] ?? ''));

            $_SESSION['flash_success'] = 'Sesión iniciada correctamente';
            header('Location: ' . url('checkout'));
            exit;
        } catch (Throwable $e) {
            error_log('[AuthController::handleLoginRequest] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'No se pudo iniciar sesión';
            header('Location: ' . url('login'));
            exit;
        }
    }

    public function handleRegisterRequest(array $input): void
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $apellido = trim((string) ($input['apellido'] ?? ''));
        $rut = trim((string) ($input['rut'] ?? ''));
        $telefono = trim((string) ($input['telefono'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($nombre === '' || $apellido === '' || $rut === '' || $email === '' || $password === '') {
            $_SESSION['flash_error'] = 'Debes completar nombre, apellido, RUT, email y contraseña';
            header('Location: ' . url('register'));
            exit;
        }

        $pdo = dbMatriz();

        try {
            $pdo->beginTransaction();

            $usuarioExistente = queryOne($pdo, '
                SELECT id
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ', [':email' => $email]);

            if ($usuarioExistente) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'Ya existe un usuario con ese email';
                header('Location: ' . url('register'));
                exit;
            }

            $clienteExistente = queryOne($pdo, '
                SELECT id
                FROM clientes
                WHERE rut = :rut
                LIMIT 1
            ', [':rut' => $rut]);

            if ($clienteExistente) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'Ya existe un cliente con ese RUT';
                header('Location: ' . url('register'));
                exit;
            }

            $usuarioId = generateUuid();
            $clienteId = generateUuid();

            $stmt = $pdo->prepare('
                INSERT INTO usuarios (id, email, password, rol)
                VALUES (:id, :email, :password, :rol)
            ');
            $stmt->execute([
                ':id'       => $usuarioId,
                ':email'    => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':rol'      => 'cliente',
            ]);

            $stmt = $pdo->prepare('
                INSERT INTO clientes (id, usuario_id, rut, nombre, apellido, telefono)
                VALUES (:id, :uid, :rut, :nombre, :apellido, :telefono)
            ');
            $stmt->execute([
                ':id'       => $clienteId,
                ':uid'      => $usuarioId,
                ':rut'      => $rut,
                ':nombre'   => $nombre,
                ':apellido' => $apellido,
                ':telefono' => $telefono !== '' ? $telefono : null,
            ]);

            $pdo->commit();

            $_SESSION['usuario_id'] = $usuarioId;
            $_SESSION['cliente_id'] = $clienteId;
            $_SESSION['rol'] = 'cliente';
            $_SESSION['nombre'] = trim($nombre . ' ' . $apellido);
            $_SESSION['flash_success'] = 'Cuenta creada correctamente';

            header('Location: ' . url('checkout'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[AuthController::handleRegisterRequest] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'No se pudo crear la cuenta';
            header('Location: ' . url('register'));
            exit;
        }
    }

    public function logout(): void
    {
        unset(
            $_SESSION['usuario_id'],
            $_SESSION['cliente_id'],
            $_SESSION['rol'],
            $_SESSION['nombre']
        );

        $_SESSION['flash_success'] = 'Sesión cerrada correctamente';
        header('Location: ' . url(''));
        exit;
    }
}