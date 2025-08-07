<?php
/**
 * MapVision Analytics - Gestión de Estados de Usuario (SQL Estándar)
 * Archivo: classes/UserStatus.php
 * Descripción: Monitoreo y control de estados de usuarios - Compatible con múltiples BD
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

class UserStatus {
    private $db;
    private $dbType;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        $this->dbType = DatabaseConfig::getInstance()->getConnectionStats()['driver'];
        app_log("UserStatus inicializado con motor: {$this->dbType}", 'DEBUG');
    }
    
    /**
     * Obtener estado completo de todos los usuarios con filtros
     */
    public function getAllUsersStatus($filters = []) {
        try {
            $whereClause = 'WHERE 1=1';
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['estado'])) {
                switch ($filters['estado']) {
                    case 'conectado':
                        $whereClause .= ' AND conectado = 1';
                        break;
                    case 'desconectado':
                        $whereClause .= ' AND conectado = 0 AND activo = 1';
                        break;
                    case 'deshabilitado':
                        $whereClause .= ' AND activo = 0';
                        break;
                    case 'bloqueado':
                        $whereClause .= ' AND bloqueado_hasta > CURRENT_TIMESTAMP';
                        break;
                    case 'no_verificado':
                        $whereClause .= ' AND email_verificado = 0';
                        break;
                }
            }
            
            if (!empty($filters['rol'])) {
                $whereClause .= ' AND rol = ?';
                $params[] = $filters['rol'];
            }
            
            if (!empty($filters['empresa'])) {
                // Usar LIKE case-insensitive compatible
                $whereClause .= ' AND ' . $this->getCaseInsensitiveLike('empresa') . ' ?';
                $params[] = '%' . strtoupper($filters['empresa']) . '%';
            }
            
            if (!empty($filters['fecha_desde'])) {
                $whereClause .= ' AND fecha_creacion >= ?';
                $params[] = $filters['fecha_desde'] . ' 00:00:00';
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $whereClause .= ' AND fecha_creacion <= ?';
                $params[] = $filters['fecha_hasta'] . ' 23:59:59';
            }
            
            // Ordenamiento
            $orderBy = 'ORDER BY fecha_ultimo_acceso DESC';
            if (!empty($filters['orden'])) {
                switch ($filters['orden']) {
                    case 'nombre':
                        $orderBy = 'ORDER BY nombre, apellido';
                        break;
                    case 'email':
                        $orderBy = 'ORDER BY email';
                        break;
                    case 'rol':
                        $orderBy = 'ORDER BY rol, nombre';
                        break;
                    case 'tiempo_conectado':
                        $orderBy = 'ORDER BY total_tiempo_conectado DESC';
                        break;
                    case 'fecha_creacion':
                        $orderBy = 'ORDER BY fecha_creacion DESC';
                        break;
                }
            }
            
            $sql = "SELECT * FROM vw_status_usuarios {$whereClause} {$orderBy}";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $users = $stmt->fetchAll();
            
            app_log("Obtenidos " . count($users) . " usuarios con filtros aplicados", 'DEBUG');
            
            return $users;
            
        } catch (Exception $e) {
            app_log("Error obteniendo estado de usuarios: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener estadísticas generales del sistema
     */
    public function getGeneralStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_usuarios,
                    SUM(CASE WHEN conectado = 1 THEN 1 ELSE 0 END) as usuarios_conectados,
                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as usuarios_activos,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as usuarios_deshabilitados,
                    SUM(CASE WHEN bloqueado_hasta > CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as usuarios_bloqueados,
                    SUM(CASE WHEN email_verificado = 0 THEN 1 ELSE 0 END) as usuarios_no_verificados,
                    SUM(CASE WHEN rol = 'Trial' THEN 1 ELSE 0 END) as usuarios_trial,
                    SUM(CASE WHEN rol = 'Personal' THEN 1 ELSE 0 END) as usuarios_personal,
                    SUM(CASE WHEN rol = 'Empresa' THEN 1 ELSE 0 END) as usuarios_empresa,
                    SUM(CASE WHEN rol = 'Administrador' THEN 1 ELSE 0 END) as usuarios_admin,
                    SUM(CASE WHEN fecha_creacion > ? THEN 1 ELSE 0 END) as nuevos_hoy,
                    SUM(CASE WHEN fecha_ultimo_acceso > ? THEN 1 ELSE 0 END) as activos_hoy
                FROM mv_usuarios
            ");
            
            // Calcular timestamp de hace 24 horas
            $oneDayAgo = date('Y-m-d H:i:s', time() - 86400);
            
            $stmt->execute([$oneDayAgo, $oneDayAgo]);
            $stats = $stmt->fetch();
            
            // Agregar estadísticas de sesiones
            $sessionStats = $this->getSessionStats();
            $stats = array_merge($stats, $sessionStats);
            
            app_log("Estadísticas generales calculadas", 'DEBUG');
            
            return $stats;
            
        } catch (Exception $e) {
            app_log("Error obteniendo estadísticas generales: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de sesiones
     */
    private function getSessionStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_sesiones_activas,
                    COUNT(DISTINCT usuario_email) as usuarios_con_sesiones_activas,
                    AVG(duracion_minutos) as promedio_duracion_sesiones,
                    MAX(duracion_minutos) as sesion_mas_larga,
                    SUM(CASE WHEN fecha_inicio > ? THEN 1 ELSE 0 END) as sesiones_ultima_hora
                FROM mv_sesiones 
                WHERE activa = 1 AND fecha_expiracion > CURRENT_TIMESTAMP
            ");
            
            $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
            $stmt->execute([$oneHourAgo]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            app_log("Error obteniendo estadísticas de sesiones: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Habilitar o deshabilitar usuario (sin procedimiento almacenado)
     */
    public function toggleUserStatus($userEmail, $active, $adminEmail) {
        try {
            app_log("Cambiando estado de usuario {$userEmail} a " . ($active ? 'activo' : 'inactivo') . " por {$adminEmail}", 'INFO');
            
            DatabaseConfig::getInstance()->beginTransaction();
            
            // Actualizar estado del usuario
            $stmt = $this->db->prepare("
                UPDATE mv_usuarios 
                SET activo = ? 
                WHERE email = ?
            ");
            $stmt->execute([$active ? 1 : 0, $userEmail]);
            
            // Si se deshabilita, cerrar todas las sesiones activas y desconectar
            if (!$active) {
                // Cerrar sesiones activas
                $stmt = $this->db->prepare("
                    UPDATE mv_sesiones 
                    SET activa = 0, fecha_fin = CURRENT_TIMESTAMP 
                    WHERE usuario_email = ? AND activa = 1
                ");
                $stmt->execute([$userEmail]);
                
                // Marcar como desconectado
                $stmt = $this->db->prepare("
                    UPDATE mv_usuarios 
                    SET conectado = 0 
                    WHERE email = ?
                ");
                $stmt->execute([$userEmail]);
            }
            
            // Log de la acción administrativa
            $action = $active ? 'usuario_habilitado' : 'usuario_deshabilitado';
            $details = 'Usuario ' . $userEmail . ($active ? ' habilitado' : ' deshabilitado');
            
            $stmt = $this->db->prepare("
                INSERT INTO mv_log_accesos (usuario_email, accion, detalles, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $adminEmail, 
                $action, 
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            DatabaseConfig::getInstance()->commit();
            
            $actionText = $active ? 'habilitado' : 'deshabilitado';
            app_log("Usuario {$userEmail} {$actionText} exitosamente por {$adminEmail}", 'INFO');
            
            return [
                'success' => true, 
                'message' => "Usuario {$actionText} correctamente"
            ];
            
        } catch (Exception $e) {
            DatabaseConfig::getInstance()->rollback();
            app_log("Error cambiando estado de usuario {$userEmail}: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error al cambiar estado del usuario'
            ];
        }
    }
    
    /**
     * Actualizar estado de conexión de usuario (sin procedimiento almacenado)
     */
    public function updateConnectionStatus($userEmail, $connected) {
        try {
            $status = $connected ? 'conectado' : 'desconectado';
            app_log("Actualizando estado de conexión de {$userEmail} a {$status}", 'DEBUG');
            
            DatabaseConfig::getInstance()->beginTransaction();
            
            // Actualizar estado de conexión y actividad
            $stmt = $this->db->prepare("
                UPDATE mv_usuarios 
                SET conectado = ?,
                    ultima_actividad = CURRENT_TIMESTAMP,
                    fecha_ultimo_acceso = CASE WHEN ? = 1 THEN CURRENT_TIMESTAMP ELSE fecha_ultimo_acceso END
                WHERE email = ?
            ");
            $stmt->execute([$connected ? 1 : 0, $connected ? 1 : 0, $userEmail]);
            
            // Si es conexión (login), actualizar/crear estadísticas
            if ($connected) {
                $this->updateUserStatistics($userEmail);
            }
            
            DatabaseConfig::getInstance()->commit();
            
            return ['success' => true, 'message' => 'Estado de conexión actualizado'];
            
        } catch (Exception $e) {
            DatabaseConfig::getInstance()->rollback();
            app_log("Error actualizando estado de conexión de {$userEmail}: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error actualizando estado'];
        }
    }
    
    /**
     * Actualizar estadísticas de usuario al conectarse (reemplaza trigger)
     */
    private function updateUserStatistics($userEmail) {
        try {
            // Verificar si existe registro de estadísticas
            $stmt = $this->db->prepare("
                SELECT total_logins FROM mv_estadisticas_usuario 
                WHERE usuario_email = ?
            ");
            $stmt->execute([$userEmail]);
            $stats = $stmt->fetch();
            
            if ($stats) {
                // Incrementar contador de logins
                $stmt = $this->db->prepare("
                    UPDATE mv_estadisticas_usuario 
                    SET total_logins = total_logins + 1, 
                        ultima_actualizacion = CURRENT_TIMESTAMP
                    WHERE usuario_email = ?
                ");
                $stmt->execute([$userEmail]);
            } else {
                // Crear nuevo registro
                $stmt = $this->db->prepare("
                    INSERT INTO mv_estadisticas_usuario (usuario_email, total_logins, ultima_actualizacion)
                    VALUES (?, 1, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$userEmail]);
            }
            
        } catch (Exception $e) {
            app_log("Error actualizando estadísticas de usuario {$userEmail}: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Obtener historial de logs de un usuario específico
     */
    public function getUserLogs($userEmail, $limit = 50, $filters = []) {
        try {
            $whereClause = 'WHERE usuario_email = ?';
            $params = [$userEmail];
            
            // Aplicar filtros adicionales
            if (!empty($filters['accion'])) {
                $whereClause .= ' AND accion = ?';
                $params[] = $filters['accion'];
            }
            
            if (!empty($filters['fecha_desde'])) {
                $whereClause .= ' AND fecha >= ?';
                $params[] = $filters['fecha_desde'] . ' 00:00:00';
            }
            
            if (!empty($filters['fecha_hasta'])) {
                $whereClause .= ' AND fecha <= ?';
                $params[] = $filters['fecha_hasta'] . ' 23:59:59';
            }
            
            if (!empty($filters['ip'])) {
                $whereClause .= ' AND ip_address = ?';
                $params[] = $filters['ip'];
            }
            
            // Usar LIMIT estándar
            $limitClause = $this->getLimitClause($limit);
            
            $stmt = $this->db->prepare("
                SELECT accion, ip_address, user_agent, detalles, fecha, exito
                FROM mv_log_accesos 
                {$whereClause}
                ORDER BY fecha DESC 
                {$limitClause}
            ");
            
            $stmt->execute($params);
            
            $logs = $stmt->fetchAll();
            
            app_log("Obtenidos " . count($logs) . " logs para usuario {$userEmail}", 'DEBUG');
            
            return $logs;
            
        } catch (Exception $e) {
            app_log("Error obteniendo logs de usuario {$userEmail}: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener usuarios conectados en tiempo real
     */
    public function getOnlineUsers() {
        try {
            $stmt = $this->db->prepare("
                SELECT email, nombre, apellido, rol, empresa, 
                       fecha_ultimo_acceso, ultima_actividad
                FROM mv_usuarios 
                WHERE conectado = 1 AND activo = 1
                ORDER BY ultima_actividad DESC
            ");
            
            $stmt->execute();
            $onlineUsers = $stmt->fetchAll();
            
            // Calcular minutos de inactividad en PHP
            foreach ($onlineUsers as &$user) {
                if ($user['ultima_actividad']) {
                    $lastActivity = new DateTime($user['ultima_actividad']);
                    $now = new DateTime();
                    $diff = $now->getTimestamp() - $lastActivity->getTimestamp();
                    $user['minutos_inactivo'] = round($diff / 60);
                } else {
                    $user['minutos_inactivo'] = null;
                }
            }
            
            app_log("Encontrados " . count($onlineUsers) . " usuarios conectados", 'DEBUG');
            
            return $onlineUsers;
            
        } catch (Exception $e) {
            app_log("Error obteniendo usuarios conectados: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de actividad por fecha
     */
    public function getActivityStats($days = 30) {
        try {
            // Calcular fecha límite
            $dateLimit = date('Y-m-d H:i:s', time() - ($days * 86400));
            
            $dateFormat = $this->getDateFormatFunction();
            
            $stmt = $this->db->prepare("
                SELECT 
                    {$dateFormat} as fecha,
                    SUM(CASE WHEN accion = 'login_success' THEN 1 ELSE 0 END) as logins_exitosos,
                    SUM(CASE WHEN accion = 'login_failed' THEN 1 ELSE 0 END) as logins_fallidos,
                    SUM(CASE WHEN accion = 'register_success' THEN 1 ELSE 0 END) as registros,
                    SUM(CASE WHEN accion = 'logout' THEN 1 ELSE 0 END) as logouts,
                    COUNT(DISTINCT usuario_email) as usuarios_unicos
                FROM mv_log_accesos 
                WHERE fecha >= ?
                GROUP BY {$dateFormat}
                ORDER BY fecha DESC
            ");
            
            $stmt->execute([$dateLimit]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            app_log("Error obteniendo estadísticas de actividad: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener usuarios por tiempo de conexión
     */
    public function getUsersByConnectionTime($orderBy = 'DESC', $limit = 20) {
        try {
            $order = $orderBy === 'ASC' ? 'ASC' : 'DESC';
            $limitClause = $this->getLimitClause($limit);
            
            $stmt = $this->db->prepare("
                SELECT u.email, u.nombre, u.apellido, u.rol, u.empresa,
                       COALESCE(s.total_tiempo_conectado, 0) as total_tiempo_conectado, 
                       COALESCE(s.total_logins, 0) as total_logins, 
                       COALESCE(s.promedio_sesion, 0) as promedio_sesion, 
                       COALESCE(s.sesion_mas_larga, 0) as sesion_mas_larga
                FROM mv_usuarios u
                LEFT JOIN mv_estadisticas_usuario s ON u.email = s.usuario_email
                WHERE u.activo = 1
                ORDER BY COALESCE(s.total_tiempo_conectado, 0) {$order}
                {$limitClause}
            ");
            
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            app_log("Error obteniendo usuarios por tiempo de conexión: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener alertas del sistema
     */
    public function getSystemAlerts() {
        $alerts = [];
        
        try {
            // Usuarios bloqueados
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM mv_usuarios 
                WHERE bloqueado_hasta > CURRENT_TIMESTAMP
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            $blocked = $result['count'];
            
            if ($blocked > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Usuarios Bloqueados',
                    'message' => "{$blocked} usuario(s) están temporalmente bloqueados",
                    'count' => $blocked
                ];
            }
            
            // Intentos de login fallidos recientes
            $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM mv_log_accesos 
                WHERE accion = 'login_failed' 
                AND fecha > ?
            ");
            $stmt->execute([$oneHourAgo]);
            $result = $stmt->fetch();
            $failedLogins = $result['count'];
            
            if ($failedLogins > 10) { // Threshold configurable
                $alerts[] = [
                    'type' => 'error',
                    'title' => 'Intentos de Login Fallidos',
                    'message' => "{$failedLogins} intentos fallidos en la última hora",
                    'count' => $failedLogins
                ];
            }
            
            // Usuarios sin verificar por más de 24 horas
            $oneDayAgo = date('Y-m-d H:i:s', time() - 86400);
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM mv_usuarios 
                WHERE email_verificado = 0 
                AND fecha_creacion < ?
            ");
            $stmt->execute([$oneDayAgo]);
            $result = $stmt->fetch();
            $unverified = $result['count'];
            
            if ($unverified > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => 'Usuarios Sin Verificar',
                    'message' => "{$unverified} usuario(s) sin verificar por más de 24h",
                    'count' => $unverified
                ];
            }
            
            // Sesiones activas inusualmente largas (más de 12 horas)
            $twelveHoursAgo = date('Y-m-d H:i:s', time() - 43200);
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM mv_sesiones 
                WHERE activa = 1 
                AND fecha_inicio < ?
            ");
            $stmt->execute([$twelveHoursAgo]);
            $result = $stmt->fetch();
            $longSessions = $result['count'];
            
            if ($longSessions > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Sesiones Largas',
                    'message' => "{$longSessions} sesión(es) activa(s) por más de 12h",
                    'count' => $longSessions
                ];
            }
            
        } catch (Exception $e) {
            app_log("Error obteniendo alertas del sistema: " . $e->getMessage(), 'ERROR');
            $alerts[] = [
                'type' => 'error',
                'title' => 'Error del Sistema',
                'message' => 'Error obteniendo alertas del sistema',
                'count' => 0
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Limpiar datos antiguos y optimizar (sin procedimiento almacenado)
     */
    public function performMaintenance() {
        $results = [];
        
        try {
            DatabaseConfig::getInstance()->beginTransaction();
            
            // Limpiar tokens expirados
            $stmt = $this->db->prepare("DELETE FROM mv_tokens WHERE fecha_expiracion < CURRENT_TIMESTAMP");
            $stmt->execute();
            $tokensDeleted = $stmt->rowCount();
            $results['tokens_deleted'] = "Eliminados {$tokensDeleted} tokens expirados";
            
            // Limpiar sesiones expiradas
            $stmt = $this->db->prepare("
                UPDATE mv_sesiones 
                SET activa = 0, fecha_fin = CURRENT_TIMESTAMP 
                WHERE fecha_expiracion < CURRENT_TIMESTAMP AND activa = 1
            ");
            $stmt->execute();
            $sessionsExpired = $stmt->rowCount();
            $results['sessions_expired'] = "Expiradas {$sessionsExpired} sesiones";
            
            // Limpiar logs antiguos (más de 6 meses)
            $sixMonthsAgo = date('Y-m-d H:i:s', time() - (6 * 30 * 86400));
            $stmt = $this->db->prepare("DELETE FROM mv_log_accesos WHERE fecha < ?");
            $stmt->execute([$sixMonthsAgo]);
            $logsDeleted = $stmt->rowCount();
            $results['logs_deleted'] = "Eliminados {$logsDeleted} logs antiguos";
            
            // Actualizar usuarios desconectados con sesiones expiradas
            $stmt = $this->db->prepare("
                UPDATE mv_usuarios 
                SET conectado = 0 
                WHERE email IN (
                    SELECT DISTINCT usuario_email 
                    FROM mv_sesiones 
                    WHERE fecha_expiracion < CURRENT_TIMESTAMP AND activa = 0
                ) AND conectado = 1
            ");
            $stmt->execute();
            $usersDisconnected = $stmt->rowCount();
            $results['users_disconnected'] = "Desconectados {$usersDisconnected} usuarios sin sesiones activas";
            
            DatabaseConfig::getInstance()->commit();
            
            app_log("Mantenimiento ejecutado: " . json_encode($results), 'INFO');
            
        } catch (Exception $e) {
            DatabaseConfig::getInstance()->rollback();
            app_log("Error en mantenimiento: " . $e->getMessage(), 'ERROR');
            $results['error'] = 'Error ejecutando mantenimiento: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Exportar datos de usuarios (para reportes)
     */
    public function exportUserData($format = 'array', $filters = []) {
        try {
            $users = $this->getAllUsersStatus($filters);
            
            switch ($format) {
                case 'csv':
                    return $this->arrayToCsv($users);
                case 'json':
                    return json_encode($users, JSON_PRETTY_PRINT);
                default:
                    return $users;
            }
            
        } catch (Exception $e) {
            app_log("Error exportando datos de usuarios: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Convertir array a CSV
     */
    private function arrayToCsv($array) {
        if (empty($array)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, array_keys($array[0]));
        
        // Data
        foreach ($array as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    // ================================================================
    // MÉTODOS AUXILIARES PARA COMPATIBILIDAD MULTI-BD
    // ================================================================
    
    /**
     * Obtener función LIKE case-insensitive según el motor
     */
    private function getCaseInsensitiveLike($column) {
        switch ($this->dbType) {
            case 'oracle':
                return "UPPER({$column}) LIKE";
            case 'postgresql':
                return "UPPER({$column}) LIKE";
            case 'sqlite':
                return "UPPER({$column}) LIKE";
            default:
                return "UPPER({$column}) LIKE";
        }
    }
    
    /**
     * Obtener cláusula LIMIT según el motor
     */
    private function getLimitClause($limit) {
        switch ($this->dbType) {
            case 'oracle':
                return "FETCH FIRST {$limit} ROWS ONLY";
            case 'postgresql':
            case 'sqlite':
                return "LIMIT {$limit}";
            default:
                return "LIMIT {$limit}";
        }
    }
    
    /**
     * Obtener función de formato de fecha según el motor
     */
    private function getDateFormatFunction() {
        switch ($this->dbType) {
            case 'oracle':
                return "TO_CHAR(fecha, 'YYYY-MM-DD')";
            case 'postgresql':
                return "TO_CHAR(fecha, 'YYYY-MM-DD')";
            case 'sqlite':
                return "DATE(fecha)";
            default:
                return "DATE(fecha)";
        }
    }
}

// Log de inicialización
app_log("Módulo UserStatus (SQL estándar) cargado", 'INFO');
?>