<?php
// src/config/database.php

class Database
{
    public const NODE_MATRIZ = 'matriz';
    public const NODE_NORTE  = 'norte';
    public const NODE_SUR    = 'sur';
    public const NODE_CENTRO = 'centro';

    public const LOCAL_NODES = [
        self::NODE_NORTE,
        self::NODE_SUR,
        self::NODE_CENTRO,
    ];

    private static ?Database $instance = null;

    private PDO $matrizConnection;
    private string $nodeType;
    private string $nodeName;

    /** @var array<string, ?PDO> */
    private array $nodeConnections = [];

    private function __construct()
    {
        $this->nodeType = getenv('NODE_TYPE') ?: self::NODE_MATRIZ;

        $hostMap = [
            self::NODE_MATRIZ => getenv('DB_MATRIZ_HOST') ?: 'nodo_matriz_db',
            self::NODE_NORTE  => getenv('DB_HOST_NORTE') ?: 'nodo_norte_db',
            self::NODE_SUR    => getenv('DB_HOST_SUR') ?: 'nodo_sur_db',
            self::NODE_CENTRO => getenv('DB_HOST_CENTRO') ?: 'nodo_centro_db',
        ];

        if (!isset($hostMap[$this->nodeType])) {
            throw new Exception("NODE_TYPE inválido: {$this->nodeType}");
        }

        $this->nodeName = $hostMap[$this->nodeType];

        $user = getenv('DB_USER') ?: 'appuser';
        $pass = getenv('DB_PASSWORD') ?: 'apppassword';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];

        try {
            $this->matrizConnection = new PDO(
                "mysql:host={$hostMap[self::NODE_MATRIZ]};dbname=db_matriz;charset=utf8mb4",
                $user,
                $pass,
                $options
            );
        } catch (PDOException $e) {
            throw new Exception("Error conexión db_matriz: " . $e->getMessage(), 0, $e);
        }

        foreach (self::LOCAL_NODES as $node) {
            $dbName = 'db_' . $node;
            try {
                $this->nodeConnections[$node] = new PDO(
                    "mysql:host={$hostMap[$node]};dbname={$dbName};charset=utf8mb4",
                    $user,
                    $pass,
                    $options
                );
            } catch (PDOException $e) {
                error_log(sprintf('[DB WARN] Nodo %s no disponible: %s', $node, $e->getMessage()));
                $this->nodeConnections[$node] = null;
            }
        }

        error_log(sprintf(
            '[DB NODE] type=%s | host=%s | matriz=%s',
            $this->nodeType,
            $this->nodeName,
            $hostMap[self::NODE_MATRIZ]
        ));
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getMatrizConnection(): PDO
    {
        return $this->matrizConnection;
    }

    public function getNodeConnection(string $node): PDO
    {
        if (!in_array($node, self::LOCAL_NODES, true)) {
            throw new Exception("Nodo [{$node}] no reconocido.");
        }

        $connection = $this->nodeConnections[$node] ?? null;

        if ($connection === null) {
            throw new Exception("Nodo [{$node}] no disponible.");
        }

        return $connection;
    }

    public function getCurrentLocalConnection(): PDO
    {
        if ($this->nodeType === self::NODE_MATRIZ) {
            throw new Exception(
                'El nodo matriz no tiene conexión local operativa. Usa dbMatriz() o dbSucursal($node).'
            );
        }

        return $this->getNodeConnection($this->nodeType);
    }

    public function getAvailableNodes(): array
    {
        return array_filter(
            $this->nodeConnections,
            fn($connection) => $connection instanceof PDO
        );
    }

    public function getNodeInfo(): array
    {
        return [
            'type'           => $this->nodeType,
            'name'           => $this->nodeName,
            'is_matrix'      => $this->isMatrixNode(),
            'available_nodes'=> array_keys($this->getAvailableNodes()),
        ];
    }

    public function isMatrixNode(): bool
    {
        return $this->nodeType === self::NODE_MATRIZ;
    }

    public function getNodeType(): string
    {
        return $this->nodeType;
    }
}

/** PDO del nodo local activo; inválido si el contenedor corre como matriz */
function db(): PDO
{
    return Database::getInstance()->getCurrentLocalConnection();
}

/** PDO de db_matriz */
function dbMatriz(): PDO
{
    return Database::getInstance()->getMatrizConnection();
}

/** PDO de un nodo local específico */
function dbSucursal(string $node): PDO
{
    return Database::getInstance()->getNodeConnection($node);
}

/** Todos los nodos locales disponibles */
function dbNodos(): array
{
    return Database::getInstance()->getAvailableNodes();
}

function shouldUseMatrixNode(): bool
{
    return Database::getInstance()->isMatrixNode();
}

function currentNodeType(): string
{
    return Database::getInstance()->getNodeType();
}