<?php
/**
 * MapVision Analytics - Configuración de Aplicación
 * Archivo: config/app.php
 * Descripción: Configuración general de la aplicación
 */

class AppConfig {
    private static $config = [
        // Información básica de la aplicación
        'app_name' => 'MapVision Analytics',
        'app_version' => '2.0.0',
        'app_url' => 'https://tudominio.com', // 🔧 CAMBIAR POR TU DOMINIO
        
        // Configuración de sesiones
        'session_duration' => 86400, // 24 horas en segundos
        'remember_me_duration' => 2592000, // 30 días en segundos
        
        // Configuración de tokens
        'password_reset_expiry' => 3600, // 1 hora en segundos
        'email_verification_expiry' => 86400, // 24 horas en segundos
        
        // Configuración de seguridad
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutos en segundos
        'password_min_length' => 8,
        'require_email_verification' => true,
        
        // Configuración regional
        'timezone' => 'America/Santiago',
        'locale' => 'es_CL',
        'currency' => 'CLP',
        
        // Configuración de logging
        'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'log_file' => 'logs/mapvision.log',
        'log_max_size' => 10485760, // 10MB
        
        // Configuración de desarrollo
        'debug' => false, // 🔧 CAMBIAR A false EN PRODUCCIÓN
        'maintenance_mode' => false,
        
        // Límites y quotas por rol
        'role_limits' => [
            'Trial' => [
                'max_maps' => 5,
                'max_data_points' => 1000,
                'storage_mb' => 50
            ],
            'Personal' => [
                'max_maps' => 25,
                'max_data_points' => 10000,
                'storage_mb' => 500
            ],
            'Empresa' => [
                'max_maps' => 100,
                'max_data_points' => 100000,
                'storage_mb' => 5000
            ],
            'Administrador' => [
                'max_maps' => -1, // Sin límite
                'max_data_points' => -1,
                'storage_mb' => -1
            ]
        ],
        
        // URLs importantes
        'urls' => [
            'login' => '/index.html',
            'dashboard' => '/dashboard.html',
            'admin' => '/admin.html',
            'support' => 'mailto:support@mapvision.com',
            'privacy' => '/privacy.html',
            'terms' => '/terms.html'
        ]
    ];
    
    /**
     * Obtener valor de configuración
     */
    public static function get($key = null) {
        if ($key === null) {
            return self::$config;
        }
        
        // Soporte para notación de punto: 'role_limits.Trial.max_maps'
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = self::$config;
            
            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return null;
                }
                $value = $value[$k];
            }
            
            return $value;
        }
        
        return self::$config[$key] ?? null;
    }
    
    /**
     * Establecer valor de configuración
     */
    public static function set($key, $value) {
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $config = &self::$config;
            
            foreach ($keys as $k) {
                if (!isset($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
            
            $config = $value;
        } else {
            self::$config[$key] = $value;
        }
    }
    
    /**
     * Verificar si está en modo debug
     */
    public static function isDebug() {
        return self::get('debug') === true;
    }
    
    /**
     * Verificar si está en modo mantenimiento
     */
    public static function isMaintenanceMode() {
        return self::get('maintenance_mode') === true;
    }
    
    /**
     * Obtener límites para un rol específico
     */
    public static function getRoleLimits($role) {
        return self::get("role_limits.{$role}") ?? self::get('role_limits.Trial');
    }
    
    /**
     * Verificar si un rol puede realizar una acción
     */
    public static function canRolePerform($role, $action, $current_count = 0) {
        $limits = self::getRoleLimits($role);
        
        if (!isset($limits[$action])) {
            return true; // Si no hay límite definido, permitir
        }
        
        $limit = $limits[$action];
        
        // -1 significa sin límite
        if ($limit === -1) {
            return true;
        }
        
        return $current_count < $limit;
    }
    
    /**
     * Obtener URL completa
     */
    public static function url($path = '') {
        $baseUrl = rtrim(self::get('app_url'), '/');
        $path = ltrim($path, '/');
        
        if (empty($path)) {
            return $baseUrl;
        }
        
        return $baseUrl . '/' . $path;
    }
    
    /**
     * Obtener información del entorno
     */
    public static function getEnvironment() {
        return [
            'app_name' => self::get('app_name'),
            'version' => self::get('app_version'),
            'debug' => self::isDebug(),
            'maintenance' => self::isMaintenanceMode(),
            'timezone' => self::get('timezone'),
            'locale' => self::get('locale')
        ];
    }
    
    /**
     * Inicializar configuración de la aplicación
     */
    public static function init() {
        // Configurar zona horaria
        date_default_timezone_set(self::get('timezone'));
        
        // Configurar locale
        if (function_exists('setlocale')) {
            setlocale(LC_ALL, self::get('locale'));
        }
        
        // Configurar manejo de errores según modo debug
        if (self::isDebug()) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        } else {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        }
        
        // Configurar log de errores
        $logFile = self::get('log_file');
        if ($logFile) {
            // Crear directorio de logs si no existe
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            ini_set('error_log', $logFile);
        }
        
        // Configurar sesiones
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Verificar modo mantenimiento
        if (self::isMaintenanceMode()) {
            self::showMaintenancePage();
        }
    }
    
    /**
     * Mostrar página de mantenimiento
     */
    private static function showMaintenancePage() {
        // Solo mostrar si no es un admin
        if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'admin') {
            http_response_code(503);
            header('Retry-After: 3600'); // 1 hora
            
            echo '<!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Mantenimiento - ' . self::get('app_name') . '</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f8f9fa; }
                    .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                    h1 { color: #dc3545; margin-bottom: 20px; }
                    p { color: #6c757d; line-height: 1.6; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>🔧 Mantenimiento Programado</h1>
                    <p>Estamos realizando mejoras en ' . self::get('app_name') . ' para ofrecerte una mejor experiencia.</p>
                    <p>Estaremos de vuelta pronto. Gracias por tu paciencia.</p>
                    <hr>
                    <p><small>Si necesitas soporte urgente: ' . self::get('urls.support') . '</small></p>
                </div>
            </body>
            </html>';
            exit;
        }
    }
    
    /**
     * Log personalizado
     */
    public static function log($message, $level = 'INFO') {
        $allowedLevel = self::get('log_level');
        $levels = ['DEBUG' => 1, 'INFO' => 2, 'WARNING' => 3, 'ERROR' => 4];
        
        if ($levels[$level] >= $levels[$allowedLevel]) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
            
            $logFile = self::get('log_file');
            if ($logFile) {
                // Verificar tamaño del archivo
                if (file_exists($logFile) && filesize($logFile) > self::get('log_max_size')) {
                    // Rotar log
                    rename($logFile, $logFile . '.' . date('Ymd'));
                }
                
                file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            }
        }
    }
}

// Inicializar configuración automáticamente
AppConfig::init();

// Hacer disponible la función log globalmente
if (!function_exists('app_log')) {
    function app_log($message, $level = 'INFO') {
        AppConfig::log($message, $level);
    }
}

// Función helper para obtener configuración
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        $value = AppConfig::get($key);
        return $value !== null ? $value : $default;
    }
}

// Función helper para URLs
if (!function_exists('app_url')) {
    function app_url($path = '') {
        return AppConfig::url($path);
    }
}

// Log de inicialización
app_log('Aplicación iniciada - ' . AppConfig::get('app_name') . ' v' . AppConfig::get('app_version'));
?>