<?php
/**
 * MapVision Analytics - Configuraci��n de Base de Datos Multi-Engine
 * Archivo: config/database.php
 * Descripci��n: Gesti��n de conexi��n compatible con Oracle, PostgreSQL, SQLite y MySQL
 */

require_once __DIR__ . '/app.php';

class DatabaseConfig {
    private static $instance = null;
    private $connection = null;
    private $connectionAttempts = 0;
    private $maxConnectionAttempts = 3;
    private $dbType;
    
    // ?? CONFIGURACI��N DE BASE DE DATOS - EDITAR ESTAS L��NEAS
    private $config = [
        'driver' => 'oracle',                  // ?? CAMBIAR: 'oracle', 'postgresql', 'sqlite'
        
        // Para Oracle
        'oracle_host' => 'localhost',
        'oracle_port' => '1521',
        'oracle_sid' => 'XE',
        'oracle_service_name' => '',           // Usar service_name en lugar de SID si prefieres
        
        // Para PostgreSQL
        'pgsql_host' => 'localhost',
        'pgsql_port' => '5432',
        
        // Configuraci��n com��n
        'database' => 'mapvision',             // Nombre de BD (no aplica para Oracle)
        'username' => 'mapvision',             // ?? Usuario de BD
        'password' => 'password123',           // ?? Contrase?a de BD
        
        // Para SQLite
        'sqlite_path' => __DIR__ . '/../data/mapvision.db',
        
        // Configuraci��n general
        'charset' => 'utf8',
        'persistent' => false,
        'timeout' => 30,
        'retry_interval' => 5
    ];
    
    /**
     * Obtener conexi��n
     */
    public function getConnection() {
        // Verificar si la conexi��n sigue activa
        if (!$this->isConnectionAlive()) {
            app_log("Conexi��n perdida, reconectando...", 'WARNING');
            $this->connect();
        }
        
        return $this->connection;
    }
    
    /**
     * Verificar si la conexi��n sigue activa
     */
    private function isConnectionAlive() {
        if ($this->connection === null) {
            return false;
        }
        
        try {
            $testQuery = $this->getTestQuery();
            $stmt = $this->connection->query($testQuery);
            return $stmt !== false;
        } catch (PDOException $e) {
            app_log("Conexi��n no activa: " . $e->getMessage(), 'DEBUG');
            return false;
        }
    }
    
    /**
     * Iniciar transacci��n
     */
    public function beginTransaction() {
        try {
            $result = $this->getConnection()->beginTransaction();
            app_log("Transacci��n iniciada", 'DEBUG');
            return $result;
        } catch (PDOException $e) {
            app_log("Error iniciando transacci��n: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Confirmar transacci��n
     */
    public function commit() {
        try {
            $result = $this->connection->commit();
            app_log("Transacci��n confirmada", 'DEBUG');
            return $result;
        } catch (PDOException $e) {
            app_log("Error confirmando transacci��n: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Cancelar transacci��n
     */
    public function rollback() {
        try {
            $result = $this->connection->rollback();
            app_log("Transacci��n cancelada", 'DEBUG');
            return $result;
        } catch (PDOException $e) {
            app_log("Error cancelando transacci��n: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener ��ltimo ID insertado (compatible con m��ltiples motores)
     */
    public function lastInsertId($table = null, $column = 'id') {
        try {
            switch ($this->dbType) {
                case 'oracle':
                    // Oracle requiere el nombre de la secuencia
                    if ($table) {
                        $sequenceName = "mv_{$table}_seq";
                        $stmt = $this->connection->query("SELECT {$sequenceName}.CURRVAL as last_id FROM DUAL");
                        $result = $stmt->fetch();
                        return $result ? $result['LAST_ID'] : null;
                    }
                    return $this->connection->lastInsertId();
                    
                case 'postgresql':
                    // PostgreSQL usa secuencias autom��ticas
                    if ($table) {
                        $sequenceName = "mv_{$table}_{$column}_seq";
                        $stmt = $this->connection->query("SELECT currval('{$sequenceName}') as last_id");
                        $result = $stmt->fetch();
                        return $result ? $result['last_id'] : null;
                    }
                    return $this->connection->lastInsertId();
                    
                case 'sqlite':
                    return $this->connection->lastInsertId();
                    
                default:
                    return $this->connection->lastInsertId();
            }
        } catch (Exception $e) {
            app_log("Error obteniendo ��ltimo ID: " . $e->getMessage(), 'WARNING');
            return null;
        }
    }
    
    /**
     * Obtener informaci��n de la base de datos
     */
    public function getDatabaseInfo() {
        try {
            switch ($this->dbType) {
                case 'oracle':
                    $stmt = $this->connection->query("
                        SELECT 
                            banner as version,
                            (SELECT value FROM v\$parameter WHERE name = 'db_name') as db_name,
                            (SELECT CASE WHEN COUNT(*) >= 4 THEN 'Disponible' ELSE 'Incompleto' END
                             FROM user_tables WHERE table_name LIKE 'MV_%') as schema_status
                        FROM v\$version 
                        WHERE banner LIKE 'Oracle%'
                        AND ROWNUM = 1
                    ");
                    break;
                    
                case 'postgresql':
                    $stmt = $this->connection->query("
                        SELECT 
                            version() as version,
                            current_database() as db_name,
                            (SELECT CASE WHEN COUNT(*) >= 4 THEN 'Disponible' ELSE 'Incompleto' END
                             FROM information_schema.tables 
                             WHERE table_schema = 'public' AND table_name LIKE 'mv_%') as schema_status
                    ");
                    break;
                    
                case 'sqlite':
                    $stmt = $this->connection->query("
                        SELECT 
                            sqlite_version() as version,
                            'SQLite Database' as db_name,
                            (SELECT CASE WHEN COUNT(*) >= 4 THEN 'Disponible' ELSE 'Incompleto' END
                             FROM sqlite_master 
                             WHERE type='table' AND name LIKE 'mv_%') as schema_status
                    ");
                    break;
            }
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            app_log("Error obteniendo informaci��n de BD: " . $e->getMessage(), 'WARNING');
            return [
                'version' => 'Desconocida',
                'db_name' => 'Desconocido',
                'schema_status' => 'Error'
            ];
        }
    }
    
    /**
     * Verificar estado del schema
     */
    public function checkSchemaStatus() {
        try {
            $requiredTables = ['mv_usuarios', 'mv_sesiones', 'mv_tokens', 'mv_log_accesos'];
            
            switch ($this->dbType) {
                case 'oracle':
                    $stmt = $this->connection->prepare("
                        SELECT COUNT(*) as table_count 
                        FROM user_tables 
                        WHERE UPPER(table_name) IN ('" . implode("', '", array_map('strtoupper', $requiredTables)) . "')
                    ");
                    break;
                    
                case 'postgresql':
                    $stmt = $this->connection->prepare("
                        SELECT COUNT(*) as table_count 
                        FROM information_schema.tables 
                        WHERE table_schema = 'public' 
                        AND table_name IN ('" . implode("', '", $requiredTables) . "')
                    ");
                    break;
                    
                case 'sqlite':
                    $stmt = $this->connection->prepare("
                        SELECT COUNT(*) as table_count 
                        FROM sqlite_master 
                        WHERE type='table' 
                        AND name IN ('" . implode("', '", $requiredTables) . "')
                    ");
                    break;
            }
            
            $stmt->execute();
            $result = $stmt->fetch();
            
            return (int)$result['table_count'] === count($requiredTables);
            
        } catch (PDOException $e) {
            app_log("Error verificando schema: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obtener estad��sticas de conexi��n
     */
    public function getConnectionStats() {
        $stats = [
            'driver' => $this->dbType,
            'charset' => $this->config['charset'],
            'persistent' => $this->config['persistent'],
            'is_connected' => $this->isConnectionAlive(),
            'connection_attempts' => $this->connectionAttempts
        ];
        
        // Agregar informaci��n espec��fica por motor
        switch ($this->dbType) {
            case 'oracle':
                $stats['host'] = $this->config['oracle_host'];
                $stats['port'] = $this->config['oracle_port'];
                $stats['database'] = $this->config['oracle_sid'] ?: $this->config['oracle_service_name'];
                break;
                
            case 'postgresql':
                $stats['host'] = $this->config['pgsql_host'];
                $stats['port'] = $this->config['pgsql_port'];
                $stats['database'] = $this->config['database'];
                break;
                
            case 'sqlite':
                $stats['file_path'] = $this->config['sqlite_path'];
                $stats['file_exists'] = file_exists($this->config['sqlite_path']);
                if ($stats['file_exists']) {
                    $stats['file_size'] = filesize($this->config['sqlite_path']);
                }
                break;
        }
        
        $stats['username'] = $this->config['username'];
        
        return $stats;
    }
    
    /**
     * Cerrar conexi��n
     */
    public function close() {
        if ($this->connection) {
            app_log("Cerrando conexi��n a {$this->dbType}", 'DEBUG');
            $this->connection = null;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
    
    /**
     * Prevenir clonaci��n
     */
    public function __clone() {
        throw new Exception("No se puede clonar una instancia Singleton");
    }
    
    /**
     * Prevenir deserializaci��n
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar una instancia Singleton");
    }
}

// Funci��n helper para obtener instancia de BD
if (!function_exists('db')) {
    function db() {
        return DatabaseConfig::getInstance();
    }
}

// Log de inicializaci��n
app_log("M��dulo de base de datos cargado", 'INFO');
?>
     * Constructor privado para patr��n Singleton
     */
    private function __construct() {
        $this->dbType = $this->config['driver'];
        $this->connect();
    }
    
    /**
     * Obtener instancia ��nica (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establecer conexi��n a la base de datos
     */
    private function connect() {
        try {
            $this->connectionAttempts++;
            
            // Construir DSN seg��n el tipo de BD
            $dsn = $this->buildDSN();
            
            app_log("Intentando conectar a {$this->dbType} (intento {$this->connectionAttempts})", 'INFO');
            
            // Opciones de PDO
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => $this->config['timeout']
            ];
            
            // Agregar persistencia si est�� habilitada
            if ($this->config['persistent']) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }
            
            // Configuraciones espec��ficas por motor
            $this->configureDriverSpecificOptions($options);
            
            // Crear conexi��n PDO
            if ($this->dbType === 'sqlite') {
                $this->connection = new PDO($dsn, null, null, $options);
                $this->ensureSQLiteDirectory();
            } else {
                $this->connection = new PDO(
                    $dsn,
                    $this->config['username'], 
                    $this->config['password'],
                    $options
                );
            }
            
            // Configurar sesi��n de BD
            $this->configureSession();
            
            // Verificar conexi��n
            $this->testConnection();
            
            app_log("Conexi��n a {$this->dbType} establecida exitosamente", 'INFO');
            $this->connectionAttempts = 0;
            
        } catch(PDOException $e) {
            $this->handleConnectionError($e);
        }
    }
    
    /**
     * Construir DSN seg��n el tipo de base de datos
     */
    private function buildDSN() {
        switch ($this->dbType) {
            case 'oracle':
                $host = $this->config['oracle_host'];
                $port = $this->config['oracle_port'];
                $charset = $this->config['charset'] === 'utf8' ? 'UTF8' : $this->config['charset'];
                
                if (!empty($this->config['oracle_service_name'])) {
                    $serviceName = $this->config['oracle_service_name'];
                    return "oci:dbname=//{$host}:{$port}/{$serviceName};charset={$charset}";
                } else {
                    $sid = $this->config['oracle_sid'];
                    $tns = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST={$host})(PORT={$port})))(CONNECT_DATA=(SID={$sid})))";
                    return "oci:dbname={$tns};charset={$charset}";
                }
                
            case 'postgresql':
                return "pgsql:host={$this->config['pgsql_host']};port={$this->config['pgsql_port']};dbname={$this->config['database']};options='--client_encoding=utf8'";
                
            case 'sqlite':
                return "sqlite:{$this->config['sqlite_path']}";
                
            default:
                throw new Exception("Driver de base de datos no soportado: {$this->dbType}. Soportados: oracle, postgresql, sqlite");
        }
    }
    
    /**
     * Configurar opciones espec��ficas por motor
     */
    private function configureDriverSpecificOptions(&$options) {
        switch ($this->dbType) {
            case 'sqlite':
                // SQLite habilitar�� claves for��neas y WAL mode despu��s de la conexi��n
                break;
            case 'oracle':
            case 'postgresql':
                // No requieren opciones especiales de conexi��n
                break;
        }
    }
    
    /**
     * Configurar sesi��n de base de datos
     */
    private function configureSession() {
        try {
            $timezone = config('timezone', 'America/Santiago');
            
            switch ($this->dbType) {
                case 'oracle':
                    $this->connection->exec("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
                    $this->connection->exec("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
                    $this->connection->exec("ALTER SESSION SET TIME_ZONE = '{$timezone}'");
                    break;
                    
                case 'postgresql':
                    $this->connection->exec("SET timezone = '{$timezone}'");
                    $this->connection->exec("SET datestyle = 'ISO, MDY'");
                    break;
                    
                case 'sqlite':
                    $this->connection->exec("PRAGMA foreign_keys = ON");
                    $this->connection->exec("PRAGMA journal_mode = WAL");
                    $this->connection->exec("PRAGMA synchronous = NORMAL");
                    break;
            }
            
            app_log("Sesi��n de {$this->dbType} configurada correctamente", 'DEBUG');
            
        } catch (PDOException $e) {
            app_log("Advertencia: No se pudo configurar la sesi��n de {$this->dbType}: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Probar conexi��n con consulta simple
     */
    private function testConnection() {
        $testQuery = $this->getTestQuery();
        $stmt = $this->connection->query($testQuery);
        $result = $stmt->fetch();
        
        if (!$result || !isset($result['test'])) {
            throw new Exception("Prueba de conexi��n fall��");
        }
    }
    
    /**
     * Obtener consulta de prueba seg��n el motor
     */
    private function getTestQuery() {
        switch ($this->dbType) {
            case 'oracle':
                return 'SELECT 1 as test FROM DUAL';
            case 'postgresql':
            case 'sqlite':
                return 'SELECT 1 as test';
            default:
                return 'SELECT 1 as test';
        }
    }
    
    /**
     * Asegurar que el directorio SQLite existe
     */
    private function ensureSQLiteDirectory() {
        if ($this->dbType === 'sqlite') {
            $dir = dirname($this->config['sqlite_path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                app_log("Directorio SQLite creado: {$dir}", 'INFO');
            }
        }
    }
    
    /**
     * Manejar errores de conexi��n
     */
    private function handleConnectionError($e) {
        $errorMessage = "Error de conexi��n {$this->dbType}: " . $e->getMessage();
        app_log($errorMessage, 'ERROR');
        
        // Si no hemos alcanzado el m��ximo de intentos, esperar y reintentar
        if ($this->connectionAttempts < $this->maxConnectionAttempts) {
            app_log("Reintentando conexi��n en {$this->config['retry_interval']} segundos...", 'INFO');
            sleep($this->config['retry_interval']);
            $this->connect();
            return;
        }
        
        // Si llegamos aqu��, todos los intentos fallaron
        $this->connectionAttempts = 0;
        throw new Exception("No se pudo conectar a {$this->dbType} despu��s de {$this->maxConnectionAttempts} intentos: " . $e->getMessage());
    }
    
    /**