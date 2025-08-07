<?php
/**
 * MapVision Analytics - API de Administración
 * Archivo: api/admin.php
 * Descripción: Endpoints REST para administración de usuarios y sistema
 */

// Headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // En producción, cambiar por dominio específico
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Cargar dependencias
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/UserStatus.php';

// Configurar manejo de errores
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    app_log("PHP Error en Admin API: {$message} in {$file}:{$line}", 'ERROR');
    return true;
});

class AdminAPI {
    private $auth;
    private $userStatus;
    private $currentUser;
    private $allowedActions;
    private $rateLimits;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->userStatus = new UserStatus();
        
        // Verificar autenticación de administrador
        $this->currentUser = $this->auth->verifySession();
        
        // Acciones permitidas por método HTTP
        $this->allowedActions = [
            'GET' => [
                'get_users_status', 'get_general_stats', 'get_user_logs', 
                'get_online_users', 'get_activity_stats', 'get_system_alerts',
                'get_users_by_connection_time', 'export_users', 'get_user_detail'
            ],
            'POST' => [
                'toggle_user_status', 'send_notification', 'perform_maintenance',
                'bulk_action', 'create_user'
            ],
            'PUT' => [
                'update_user', 'update_user_role'
            ],
            'DELETE' => [
                'delete_user_sessions', 'delete_user_logs'
            ]
        ];
        
        // Rate limits más estrictos para operaciones administrativas
        $this->rateLimits = [
            'toggle_user_status' => 20,    // 20 cambios por minuto
            'bulk_action' => 5,            // 5 acciones bulk por minuto
            'perform_maintenance' => 2,     // 2 mantenimientos por minuto
            'export_users' => 3,           // 3 exportaciones por minuto
            'send_notification' => 10      // 10 notificaciones por minuto
        ];
    }
    
    /**
     * Procesar request de administración
     */
    public function handleRequest() {
        try {
            // Verificar autenticación y permisos
            $authCheck = $this->checkAdminAuth();
            if (!$authCheck['authorized']) {
                return $this->sendResponse($authCheck['response'], $authCheck['http_code']);
            }
            
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? '';
            
            // Validar método y acción
            $validation = $this->validateRequest($method, $action);
            if (!$validation['valid']) {
                return $this->sendResponse($validation['response']);
            }
            
            // Verificar rate limiting
            $rateLimitCheck = $this->checkRateLimit($action);
            if (!$rateLimitCheck['allowed']) {
                return $this->sendResponse([
                    'success' => false,
                    'message' => 'Límite de solicitudes administrativas excedido',
                    'code' => 'ADMIN_RATE_LIMIT',
                    'retry_after' => $rateLimitCheck['retry_after']
                ], 429);
            }
            
            // Obtener datos de entrada
            $inputData = $this->getInputData($method);
            
            // Log de acción administrativa
            app_log("Acción administrativa: {$action} por {$this->currentUser['USUARIO_EMAIL']}", 'INFO');
            
            // Procesar la acción
            $response = $this->processAction($action, $inputData, $method);
            
            // Registrar en rate limiting
            $this->recordRequest($action);
            
            return $this->sendResponse($response);
            
        } catch (Exception $e) {
            app_log("Error en AdminAPI: " . $e->getMessage(), 'CRITICAL');
            return $this->sendResponse([
                'success' => false,
                'message' => 'Error interno del servidor administrativo',
                'code' => 'ADMIN_INTERNAL_ERROR'
            ], 500);
        }
    }
    
    /**
     * Verificar autenticación y permisos de administrador
     */
    private function checkAdminAuth() {
        if (!$this->currentUser) {
            app_log("Intento de acceso admin sin autenticación", 'WARNING');
            return [
                'authorized' => false,
                'response' => [
                    'success' => false,
                    'message' => 'Autenticación requerida',
                    'code' => 'AUTHENTICATION_REQUIRED'
                ],
                'http_code' => 401
            ];
        }
        
        if ($this->currentUser['ROL'] !== 'Administrador') {
            app_log("Intento de acceso admin sin permisos: {$this->currentUser['USUARIO_EMAIL']} (rol: {$this->currentUser['ROL']})", 'WARNING');
            return [
                'authorized' => false,
                'response' => [
                    'success' => false,
                    'message' => 'Permisos de administrador requeridos',
                    'code' => 'INSUFFICIENT_PRIVILEGES'
                ],
                'http_code' => 403
            ];
        }
        
        return ['authorized' => true];
    }
    
    /**
     * Validar request
     */
    private function validateRequest($method, $action) {
        if (empty($action)) {
            return [
                'valid' => false,
                'response' => [
                    'success' => false,
                    'message' => 'Acción administrativa requerida',
                    'code' => 'MISSING_ACTION'
                ]
            ];
        }
        
        if (!isset($this->allowedActions[$method]) || !in_array($action, $this->allowedActions[$method])) {
            return [
                'valid' => false,
                'response' => [
                    'success' => false,
                    'message' => 'Acción no permitida para este método HTTP',
                    'code' => 'METHOD_NOT_ALLOWED',
                    'allowed_actions' => $this->allowedActions[$method] ?? []
                ]
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Obtener datos de entrada
     */
    private function getInputData($method) {
        switch ($method) {
            case 'POST':
            case 'PUT':
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('JSON inválido en request body');
                }
                
                return array_merge($_GET, $data ?: []);
                
            case 'GET':
            case 'DELETE':
                return $_GET;
                
            default:
                return [];
        }
    }
    
    /**
     * Procesar acción específica
     */
    private function processAction($action, $data, $method) {
        switch ($action) {
            // GET Actions
            case 'get_users_status':
                return $this->handleGetUsersStatus($data);
                
            case 'get_general_stats':
                return $this->handleGetGeneralStats();
                
            case 'get_user_logs':
                return $this->handleGetUserLogs($data);
                
            case 'get_online_users':
                return $this->handleGetOnlineUsers();
                
            case 'get_activity_stats':
                return $this->handleGetActivityStats($data);
                
            case 'get_system_alerts':
                return $this->handleGetSystemAlerts();
                
            case 'get_users_by_connection_time':
                return $this->handleGetUsersByConnectionTime($data);
                
            case 'export_users':
                return $this->handleExportUsers($data);
                
            case 'get_user_detail':
                return $this->handleGetUserDetail($data);
                
            // POST Actions
            case 'toggle_user_status':
                return $this->handleToggleUserStatus($data);
                
            case 'send_notification':
                return $this->handleSendNotification($data);
                
            case 'perform_maintenance':
                return $this->handlePerformMaintenance();
                
            case 'bulk_action':
                return $this->handleBulkAction($data);
                
            case 'create_user':
                return $this->handleCreateUser($data);
                
            // PUT Actions
            case 'update_user':
                return $this->handleUpdateUser($data);
                
            case 'update_user_role':
                return $this->handleUpdateUserRole($data);
                
            // DELETE Actions
            case 'delete_user_sessions':
                return $this->handleDeleteUserSessions($data);
                
            case 'delete_user_logs':
                return $this->handleDeleteUserLogs($data);
                
            default:
                return [
                    'success' => false,
                    'message' => 'Acción administrativa no implementada',
                    'code' => 'ACTION_NOT_IMPLEMENTED'
                ];
        }
    }
    
    // Implementación de handlers
    
    private function handleGetUsersStatus($data) {
        $filters = [
            'estado' => $data['estado'] ?? '',
            'rol' => $data['rol'] ?? '',
            'empresa' => $data['empresa'] ?? '',
            'fecha_desde' => $data['fecha_desde'] ?? '',
            'fecha_hasta' => $data['fecha_hasta'] ?? '',
            'orden' => $data['orden'] ?? ''
        ];
        
        $users = $this->userStatus->getAllUsersStatus($filters);
        
        return [
            'success' => true,
            'data' => $users,
            'total' => count($users),
            'filters_applied' => array_filter($filters)
        ];
    }
    
    private function handleGetGeneralStats() {
        $stats = $this->userStatus->getGeneralStats();
        
        return [
            'success' => true,
            'data' => $stats,
            'generated_at' => date('c')
        ];
    }
    
    private function handleGetUserLogs($data) {
        $userEmail = $data['user_email'] ?? '';
        $limit = min((int)($data['limit'] ?? 50), 500); // Máximo 500
        
        if (empty($userEmail)) {
            return [
                'success' => false,
                'message' => 'Email de usuario requerido',
                'code' => 'MISSING_USER_EMAIL'
            ];
        }
        
        $filters = [
            'accion' => $data['accion'] ?? '',
            'fecha_desde' => $data['fecha_desde'] ?? '',
            'fecha_hasta' => $data['fecha_hasta'] ?? '',
            'ip' => $data['ip'] ?? ''
        ];
        
        $logs = $this->userStatus->getUserLogs($userEmail, $limit, $filters);
        
        return [
            'success' => true,
            'data' => $logs,
            'user_email' => $userEmail,
            'total' => count($logs),
            'limit' => $limit
        ];
    }
    
    private function handleGetOnlineUsers() {
        $users = $this->userStatus->getOnlineUsers();
        
        return [
            'success' => true,
            'data' => $users,
            'count' => count($users),
            'timestamp' => date('c')
        ];
    }
    
    private function handleGetActivityStats($data) {
        $days = min((int)($data['days'] ?? 30), 90); // Máximo 90 días
        $stats = $this->userStatus->getActivityStats($days);
        
        return [
            'success' => true,
            'data' => $stats,
            'period_days' => $days
        ];
    }
    
    private function handleGetSystemAlerts() {
        $alerts = $this->userStatus->getSystemAlerts();
        
        return [
            'success' => true,
            'data' => $alerts,
            'alert_count' => count($alerts),
            'generated_at' => date('c')
        ];
    }
    
    private function handleGetUsersByConnectionTime($data) {
        $orderBy = $data['order'] ?? 'DESC';
        $limit = min((int)($data['limit'] ?? 20), 100); // Máximo 100
        
        $users = $this->userStatus->getUsersByConnectionTime($orderBy, $limit);
        
        return [
            'success' => true,
            'data' => $users,
            'order' => $orderBy,
            'limit' => $limit
        ];
    }
    
    private function handleToggleUserStatus($data) {
        $userEmail = $data['user_email'] ?? '';
        $active = $data['active'] ?? false;
        
        if (empty($userEmail)) {
            return [
                'success' => false,
                'message' => 'Email de usuario requerido',
                'code' => 'MISSING_USER_EMAIL'
            ];
        }
        
        // No permitir que un admin se deshabilite a sí mismo
        if ($userEmail === $this->currentUser['USUARIO_EMAIL'] && !$active) {
            return [
                'success' => false,
                'message' => 'No puedes deshabilitarte a ti mismo',
                'code' => 'CANNOT_DISABLE_SELF'
            ];
        }
        
        $adminEmail = $this->currentUser['USUARIO_EMAIL'];
        return $this->userStatus->toggleUserStatus($userEmail, $active, $adminEmail);
    }
    
    private function handleSendNotification($data) {
        $userEmail = $data['user_email'] ?? '';
        $subject = $data['subject'] ?? '';
        $message = $data['message'] ?? '';
        
        if (empty($userEmail) || empty($subject) || empty($message)) {
            return [
                'success' => false,
                'message' => 'Email, asunto y mensaje son requeridos',
                'code' => 'MISSING_NOTIFICATION_FIELDS'
            ];
        }
        
        // Usar EmailService para enviar notificación
        try {
            $emailService = new EmailService();
            $result = $emailService->sendCustomEmail($userEmail, $subject, $message, [
                'admin_name' => $this->currentUser['NOMBRE'] . ' ' . $this->currentUser['APELLIDO']
            ]);
            
            // Log de la notificación
            app_log("Notificación enviada por admin {$this->currentUser['USUARIO_EMAIL']} a {$userEmail}: {$subject}", 'INFO');
            
            return $result;
            
        } catch (Exception $e) {
            app_log("Error enviando notificación administrativa: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Error enviando notificación'
            ];
        }
    }
    
    private function handlePerformMaintenance() {
        app_log("Mantenimiento iniciado por admin: {$this->currentUser['USUARIO_EMAIL']}", 'INFO');
        
        $results = $this->userStatus->performMaintenance();
        
        return [
            'success' => true,
            'message' => 'Mantenimiento ejecutado',
            'results' => $results,
            'executed_by' => $this->currentUser['USUARIO_EMAIL'],
            'executed_at' => date('c')
        ];
    }
    
    private function handleBulkAction($data) {
        $action = $data['action'] ?? '';
        $userEmails = $data['user_emails'] ?? [];
        
        if (empty($action) || empty($userEmails) || !is_array($userEmails)) {
            return [
                'success' => false,
                'message' => 'Acción y lista de emails son requeridos',
                'code' => 'MISSING_BULK_DATA'
            ];
        }
        
        // Limitar cantidad de usuarios para operaciones bulk
        if (count($userEmails) > 50) {
            return [
                'success' => false,
                'message' => 'Máximo 50 usuarios por operación bulk',
                'code' => 'BULK_LIMIT_EXCEEDED'
            ];
        }
        
        $results = [];
        $adminEmail = $this->currentUser['USUARIO_EMAIL'];
        
        foreach ($userEmails as $userEmail) {
            if ($userEmail === $adminEmail && in_array($action, ['disable', 'delete'])) {
                $results[$userEmail] = ['success' => false, 'message' => 'No puedes realizar esta acción sobre ti mismo'];
                continue;
            }
            
            switch ($action) {
                case 'enable':
                    $results[$userEmail] = $this->userStatus->toggleUserStatus($userEmail, true, $adminEmail);
                    break;
                case 'disable':
                    $results[$userEmail] = $this->userStatus->toggleUserStatus($userEmail, false, $adminEmail);
                    break;
                default:
                    $results[$userEmail] = ['success' => false, 'message' => 'Acción no soportada'];
            }
        }
        
        app_log("Acción bulk '{$action}' ejecutada por {$adminEmail} en " . count($userEmails) . " usuarios", 'INFO');
        
        return [
            'success' => true,
            'message' => 'Operación bulk completada',
            'action' => $action,
            'results' => $results,
            'total_processed' => count($userEmails)
        ];
    }
    
    private function handleExportUsers($data) {
        $format = $data['format'] ?? 'json';
        $filters = [
            'estado' => $data['estado'] ?? '',
            'rol' => $data['rol'] ?? '',
            'empresa' => $data['empresa'] ?? ''
        ];
        
        if (!in_array($format, ['json', 'csv'])) {
            return [
                'success' => false,
                'message' => 'Formato no soportado. Use: json, csv',
                'code' => 'INVALID_FORMAT'
            ];
        }
        
        $exportData = $this->userStatus->exportUserData($format, $filters);
        
        if ($exportData === false) {
            return [
                'success' => false,
                'message' => 'Error exportando datos'
            ];
        }
        
        app_log("Exportación de usuarios en formato {$format} por admin: {$this->currentUser['USUARIO_EMAIL']}", 'INFO');
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="usuarios_' . date('Y-m-d_H-i-s') . '.csv"');
            echo $exportData;
            exit;
        }
        
        return [
            'success' => true,
            'format' => $format,
            'data' => $exportData,
            'exported_by' => $this->currentUser['USUARIO_EMAIL'],
            'exported_at' => date('c')
        ];
    }
    
    private function handleGetUserDetail($data) {
        $userEmail = $data['user_email'] ?? '';
        
        if (empty($userEmail)) {
            return [
                'success' => false,
                'message' => 'Email de usuario requerido'
            ];
        }
        
        // Obtener información completa del usuario
        $users = $this->userStatus->getAllUsersStatus(['email' => $userEmail]);
        
        if (empty($users)) {
            return [
                'success' => false,
                'message' => 'Usuario no encontrado'
            ];
        }
        
        $user = $users[0];
        
        // Obtener logs recientes
        $recentLogs = $this->userStatus->getUserLogs($userEmail, 20);
        
        // Obtener sesiones activas
        $sessionManager = new SessionManager();
        $activeSessions = $sessionManager->getUserActiveSessions($userEmail);
        
        return [
            'success' => true,
            'user' => $user,
            'recent_logs' => $recentLogs,
            'active_sessions' => $activeSessions,
            'session_count' => count($activeSessions)
        ];
    }
    
    // Métodos auxiliares para rate limiting (similares a auth.php)
    
    private function checkRateLimit($action) {
        if (!isset($this->rateLimits[$action])) {
            return ['allowed' => true];
        }
        
        $limit = $this->rateLimits[$action];
        $adminEmail = $this->currentUser['USUARIO_EMAIL'];
        $cacheFile = sys_get_temp_dir() . "/mv_admin_rate_limit_{$action}_{$adminEmail}.json";
        
        $now = time();
        $windowStart = $now - 60; // Ventana de 1 minuto
        
        $requests = [];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $requests = array_filter($data, function($timestamp) use ($windowStart) {
                    return $timestamp > $windowStart;
                });
            }
        }
        
        if (count($requests) >= $limit) {
            return [
                'allowed' => false,
                'retry_after' => 60,
                'current_count' => count($requests),
                'limit' => $limit
            ];
        }
        
        return ['allowed' => true];
    }
    
    private function recordRequest($action) {
        if (!isset($this->rateLimits[$action])) {
            return;
        }
        
        $adminEmail = $this->currentUser['USUARIO_EMAIL'];
        $cacheFile = sys_get_temp_dir() . "/mv_admin_rate_limit_{$action}_{$adminEmail}.json";
        $now = time();
        $windowStart = $now - 60;
        
        $requests = [];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $requests = array_filter($data, function($timestamp) use ($windowStart) {
                    return $timestamp > $windowStart;
                });
            }
        }
        
        $requests[] = $now;
        file_put_contents($cacheFile, json_encode(array_values($requests)), LOCK_EX);
    }
    
    private function sendResponse($response, $httpCode = 200) {
        if (config('debug')) {
            $response['debug'] = [
                'admin_user' => $this->currentUser['USUARIO_EMAIL'] ?? 'no_auth',
                'timestamp' => date('c'),
                'memory_usage' => memory_get_usage(true)
            ];
        }
        
        if (!$response['success']) {
            $logLevel = $httpCode >= 500 ? 'ERROR' : 'WARNING';
            app_log("Admin API Error: " . json_encode($response), $logLevel);
        }
        
        http_response_code($httpCode);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// Manejo de errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        app_log("Fatal Error en Admin API: {$error['message']} in {$error['file']}:{$error['line']}", 'CRITICAL');
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error crítico del servidor administrativo',
                'code' => 'ADMIN_FATAL_ERROR'
            ]);
        }
    }
});

// Ejecutar Admin API
try {
    app_log("Admin API iniciada - Método: {$_SERVER['REQUEST_METHOD']}, Acción: " . ($_GET['action'] ?? 'ninguna'), 'INFO');
    
    $api = new AdminAPI();
    $api->handleRequest();
    
} catch (Throwable $e) {
    app_log("Error no manejado en Admin API: " . $e->getMessage(), 'CRITICAL');
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor administrativo',
            'code' => 'ADMIN_UNHANDLED_ERROR'
        ]);
    }
}
?>