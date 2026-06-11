<?php
class InventoryController
{
    public function handleAdjustRequest(array $input): void
    {
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            http_response_code(403);
            $_SESSION['flash_error'] = 'No autorizado';
            header('Location: ' . url('inventory'));
            exit;
        }

        $stockId = trim((string) ($input['stock_id'] ?? ''));
        $productoId = trim((string) ($input['producto_id'] ?? ''));
        $sucursalId = trim((string) ($input['sucursal_id'] ?? ($_GET['node'] ?? 'norte')));
        $deltaRaw = trim((string) ($input['delta'] ?? ''));
        $delta = (int) $deltaRaw;

        $allowed = ['norte', 'sur', 'centro'];
        $sucursalId = strtolower($sucursalId);
        if (!in_array($sucursalId, $allowed, true)) {
            $sucursalId = 'norte';
        }

        if ($productoId === '' || $stockId === '' || $deltaRaw === '' || !is_numeric($deltaRaw) || $delta === 0) {
            $_SESSION['flash_error'] = 'Datos inválidos para ajustar stock';
            header('Location: ' . url('inventory') . '?node=' . urlencode($sucursalId));
            exit;
        }

        try {
            $pdo = dbSucursal($sucursalId);
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('
                SELECT cantidad_real
                FROM stock
                WHERE id = :id AND producto_id = :pid
                LIMIT 1
            ');
            $stmt->execute([':id' => $stockId, ':pid' => $productoId]);
            $actual = $stmt->fetchColumn();
            $actual = ($actual === false) ? 0 : (int) $actual;

            $nuevo = $actual + $delta;
            if ($nuevo < 0) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = 'El stock no puede quedar negativo';
                header('Location: ' . url('inventory') . '?node=' . urlencode($sucursalId));
                exit;
            }

            if ($actual === 0 && $stockId === '') {
                $insert = $pdo->prepare('
                    INSERT INTO stock (id, producto_id, sucursal_id, cantidad_real)
                    VALUES (:id, :pid, :sid, :cantidad)
                ');
                $insert->execute([
                    ':id' => generateUuid(),
                    ':pid' => $productoId,
                    ':sid' => $sucursalId,
                    ':cantidad' => $nuevo,
                ]);
            } else {
                $update = $pdo->prepare('
                    UPDATE stock
                    SET cantidad_real = :cantidad
                    WHERE id = :id AND producto_id = :pid
                ');
                $update->execute([
                    ':cantidad' => $nuevo,
                    ':id' => $stockId,
                    ':pid' => $productoId,
                ]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Stock actualizado correctamente';
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[InventoryController] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'No se pudo actualizar el stock';
        }

        header('Location: ' . url('inventory') . '?node=' . urlencode($sucursalId));
        exit;
    }
}
