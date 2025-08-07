<?php
/**
 * MapVision Analytics - Gestor de Sesiones (SQL Estándar)
 * Archivo: classes/SessionManager.php
 * Descripción: Gestión segura de sesiones - Compatible con múltiples BD
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

class SessionManager {
    private $db;
    private $dbType;
    private $sessionDuration;
    private $cookieName = 'mv_session';
    private $secureMode;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        $this->dbType = DatabaseConfig::getInstance()->getConnectionStats()['driver'];
        $this->sessionDuration = config('session_duration', 86400); // 24 horas por defecto
        $this->secureMode = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        
        // Configurar sesiones PHP
        $this->configurePhpSessions();
        
        app_log("SessionManager inicializado con motor: {$this->dbType}", 'DEBUG');
    }
    
    /**
     * Configurar sesiones PHP de forma segura
     */
    private function configurePhpSessions() {
        // Solo configurar si no se ha iniciado una sesión
        if (session_status() === PHP_SESSION_NONE) {
            // Configuración de seguridad para sesiones
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', $this->secureMode ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_only_cookies', 1);
            ini_set('session.entropy_length', 32);
            ini_set('session.hash_function', 'sha256');
            
            // Configurar tiempo de vida
            ini_set('session.gc_maxlifetime', $this->sessionDuration);
            ini_set('session.cookie_lifetime', $this->sessionDuration);
            
            // Generar nombre de sesión único para la aplicación
            session_name('MVSESS_' . substr(md5(config('app_name')), 0, 8));
        }
    }
    
    /**
     * Crear nueva sesión de usuario
     */
    public function createSession($userEmail, $rememberMe = false) {
        try {
            app_log("Creando sesión para usuario: {$userEmail}", 'INFO');
            
            // Generar token único y seguro
            $sessionToken = $this->generateSecureToken();
            $ipAddress = $this->getClientIP();
            $userAgent = $this->getUserAgent();
            
            // Calcular expiración
            $duration = $rememberMe ? config('remember_me_duration', 2592000) : $this->sessionDuration;
            $expiration = date('Y-m-d H:i:s', time() + $duration);
            
            // Limpiar sesiones expiradas del usuario
            $this->cleanupExpiredSessions($userEmail);
            
            // Insertar nueva sesión en BD usando SQL estándar
            $stmt = $this->db->prepare("
                INSERT INTO mv_sesiones (usuario_email, session_token, ip_address, user_agent, fecha_expiracion) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $success = $stmt->execute([
                $userEmail,
                $sessionToken,
                $ipAddress,
                $userAgent,
                $expiration
            ]);
            
            if (!$success) {
                throw new Exception("Error insertando sesión en BD");
            }
            
            // Establecer cookie segura
            $this->setSessionCookie($sessionToken, $duration);
            
            // Iniciar sesión PHP si no está iniciada
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Guardar datos básicos en sesión PHP
            $_SESSION['user_email'] = $userEmail;
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            app_log("Sesión creada exitosamente para {$userEmail}: {$sessionToken}", 'INFO');
            
            return $sessionToken;
            
        } catch (Exception $e) {
            app_log("Error creando sesión para {$userEmail}: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error creando sesión de usuario");
        }
    }
    
    /**
     * Verificar y validar sesión existente
     */
    public function verifySession($token = null) {
        try {
            // Obtener token de cookie si no se proporciona
            if (!$token) {
                $token = $_COOKIE[$this->cookieName] ?? null;
            }
            
            if (!$token) {
                app_log("No se encontró token de sesión", 'DEBUG');
                return false;
            }
            
            // Buscar sesión en BD usando SQL estándar
            $stmt = $this->db->prepare("
                SELECT s.usuario_email, s.fecha_inicio, s.ip_address, s.user_agent,
                       u.nombre, u.apellido, u.rol, u.empresa, u.activo, u.email_verificado
                FROM mv_sesiones s
                JOIN mv_usuarios u ON s.usuario_email = u.email
                WHERE s.session_token = ? 
                AND s.fecha_expiracion > CURRENT_TIMESTAMP 
                AND s.activa = 1
                AND u.activo = 1
            ");
            
            $stmt->execute([$token]);
            $sessionData = $stmt->fetch();
            
            if (!$sessionData) {
                app_log("Sesión inválida o expirada: {$token}", 'DEBUG');
                $this->clearSessionCookie();
                return false;
            }
            
            // Verificaciones adicionales de seguridad
            $securityCheck = $this->performSecurityChecks($sessionData, $token);
            if (!$securityCheck) {
                return false;
            }
            
            // Actualizar última actividad
            $this->updateLastActivity($sessionData['usuario_email'], $token);
            
            // Actualizar sesión PHP
            $this->updatePhpSession($sessionData);
            
            app_log("Sesión verificada para: {$sessionData['usuario_email']}", 'DEBUG');
            
            // Retornar datos del usuario
            return [
                'USUARIO_EMAIL' => $sessionData['usuario_email'],
                'EMAIL' => $sessionData['usuario_email'], // Alias para compatibilidad
                'NOMBRE' => $sessionData['nombre'],
                'APELLIDO' => $sessionData['apellido'],
                'ROL' => $sessionData['rol'],
                'EMPRESA' => $sessionData['empresa'],
                'EMAIL_VERIFICADO' => $sessionData['email_verificado']
            ];
            
        } catch (Exception $e) {
            app_log("Error verificando sesión: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Realizar verificaciones de seguridad adicionales
     */
    private function performSecurityChecks($sessionData, $token) {
        $currentIP = $this->getClientIP();
        $currentUserAgent = $this->getUserAgent();
        $sessionIP = $sessionData['ip_address'];
        $sessionUserAgent = $sessionData['user_agent'];
        
        // Verificar IP (permitir cambios en la misma subred)
        if (config('strict_ip_checking', false)) {
            if ($currentIP !== $sessionIP) {
                app_log("IP cambió para sesión {$token}: {$sessionIP} -> {$currentIP}", 'WARNING');
                $this->destroySession($token);
                return false;
            }
        } else {
            // Verificación flexible: misma subred /24
            $sessionSubnet = substr($sessionIP, 0, strrpos($sessionIP, '.'));
            $currentSubnet = substr($currentIP, 0, strrpos($currentIP, '.'));
            
            if ($sessionSubnet !== $currentSubnet) {
                app_log("Subred cambió para sesión {$token}: {$sessionSubnet}.x -> {$currentSubnet}.x", 'WARNING');
                // No destruir sesión pero registrar el evento
            }
        }
        
        // Verificar User-Agent (cambios menores permitidos)
        if (config('strict_user_agent_checking', false)) {
            $agentSimilarity = similar_text($sessionUserAgent, $currentUserAgent, $percent);
            if ($percent < 90) {
                app_log("User-Agent cambió significativamente para sesión {$token}", 'WARNING');
                // Solo log, no destruir sesión (browsers actualizan automáticamente)
            }
        }
        
        return true;
    }
    
    /**
     * Actualizar última actividad usando SQL estándar
     */
    private function updateLastActivity($userEmail, $token) {
        try {
            // Calcular nueva expiración
            $newExpiration = date('Y-m-d H:i:s', time() + $this->sessionDuration);
            
            // Actualizar timestamp en sesión
            $stmt = $this->db->prepare("
                UPDATE mv_sesiones 
                SET fecha_expiracion = ?
                WHERE session_token = ? AND activa = 1
            ");
            $stmt->execute([$newExpiration, $token]);
            
            // Actualizar última actividad del usuario
            $stmt2 = $this->db->prepare("
                UPDATE mv_usuarios 
                SET ultima_actividad = CURRENT_TIMESTAMP 
                WHERE email = ?
            ");
            $stmt2->execute([$userEmail]);
            
            // Actualizar sesión PHP
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['last_activity'] = time();
            }
            
        } catch (Exception $e) {
            app_log("Error actualizando actividad: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Actualizar datos en sesión PHP
     */
    private function updatePhpSession($sessionData) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_email'] = $sessionData['usuario_email'];
        $_SESSION['user_name'] = $sessionData['nombre'] . ' ' . $sessionData['apellido'];
        $_SESSION['user_role'] = $sessionData['rol'];
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Destruir sesión específica
     */
    public function destroySession($token = null) {
        try {
            if (!$token) {
                $token = $_COOKIE[$this->cookieName] ?? null;
            }
            
            if ($token) {
                // Obtener información de la sesión antes de destruirla
                $stmt = $this->db->prepare("
                    SELECT usuario_email, fecha_inicio FROM mv_sesiones 
                    WHERE session_token = ? AND activa = 1
                ");
                $stmt->execute([$token]);
                $session = $stmt->fetch();
                
                if ($session) {
                    app_log("Destruyendo sesión para: {$session['usuario_email']}", 'INFO');
                    
                    // Calcular duración de la sesión en minutos
                    $sessionStart = new DateTime($session['fecha_inicio']);
                    $sessionEnd = new DateTime();
                    $durationMinutes = round(($sessionEnd->getTimestamp() - $sessionStart->getTimestamp()) / 60);
                    
                    // Marcar sesión como inactiva y establecer fecha/duración
                    $stmt = $this->db->prepare("
                        UPDATE mv_sesiones 
                        SET activa = 0, fecha_fin = CURRENT_TIMESTAMP, duracion_minutos = ?
                        WHERE session_token = ?
                    ");
                    $stmt->execute([$durationMinutes, $token]);
                    
                    // Actualizar estadísticas del usuario
                    $this->updateSessionStatistics($session['usuario_email'], $durationMinutes);
                }
            }
            
            // Limpiar cookie
            $this->clearSessionCookie();
            
            // Destruir sesión PHP
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }
            
            app_log("Sesión destruida exitosamente", 'DEBUG');
            
        } catch (Exception $e) {
            app_log("Error destruyendo sesión: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Actualizar estadísticas de sesión (reemplaza trigger)
     */
    private function updateSessionStatistics($userEmail, $durationMinutes) {
        try {
            // Obtener estadísticas actuales
            $stmt = $this->db->prepare("
                SELECT total_tiempo_conectado, sesion_mas_larga, total_logins 
                FROM mv_estadisticas_usuario 
                WHERE usuario_email = ?
            ");
            $stmt->execute([$userEmail]);
            $stats = $stmt->fetch();
            
            if ($stats) {
                $newTotalTime = $stats['total_tiempo_conectado'] + $durationMinutes;
                $newLongestSession = max($stats['sesion_mas_larga'], $durationMinutes);
                $newAverage = $stats['total_logins'] > 0 ? round($newTotalTime / $stats['total_logins']) : 0;
                
                $stmt = $this->db->prepare("
                    UPDATE mv_estadisticas_usuario 
                    SET total_tiempo_conectado = ?,
                        sesion_mas_larga = ?,
                        promedio_sesion = ?,
                        ultima_actualizacion = CURRENT_TIMESTAMP
                    WHERE usuario_email = ?
                ");
                $stmt->execute([$newTotalTime, $newLongestSession, $newAverage, $userEmail]);
            } else {
                // Crear registro si no existe
                $stmt = $this->db->prepare("
                    INSERT INTO mv_estadisticas_usuario 
                    (usuario_email, total_tiempo_conectado, sesion_mas_larga, promedio_sesion, ultima_actualizacion)
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$userEmail, $durationMinutes, $durationMinutes, $durationMinutes]);
            }
            
        } catch (Exception $e) {
            app_log("Error actualizando estadísticas de sesión: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Destruir todas las sesiones de un usuario
     */
    public function destroyAllUserSessions($userEmail) {
        try {
            app_log("Destruyendo todas las sesiones para: {$userEmail}", 'INFO');
            
            // Obtener todas las sesiones activas del usuario para calcular duraciones
            $stmt = $this->db->prepare("
                SELECT session_token, fecha_inicio FROM mv_sesiones 
                WHERE usuario_email = ? AND activa = 1
            ");
            $stmt->execute([$userEmail]);
            $sessions = $stmt->fetchAll();
            
            $totalDuration = 0;
            $sessionCount = count($sessions);
            
            // Calcular duración total y actualizar cada sesión
            foreach ($sessions as $session) {
                $sessionStart = new DateTime($session['fecha_inicio']);
                $sessionEnd = new DateTime();
                $durationMinutes = round(($sessionEnd->getTimestamp() - $sessionStart->getTimestamp()) / 60);
                $totalDuration += $durationMinutes;
                
                // Actualizar la sesión individual
                $stmt = $this->db->prepare("
                    UPDATE mv_sesiones 
                    SET activa = 0, fecha_fin = CURRENT_TIMESTAMP, duracion_minutos = ?
                    WHERE session_token = ?
                ");
                $stmt->execute([$durationMinutes, $session['session_token']]);
            }
            
            // Actualizar estadísticas del usuario si había sesiones
            if ($sessionCount > 0) {
                $this->updateSessionStatistics($userEmail, $totalDuration);
            }
            
            app_log("Destruidas {$sessionCount} sesiones para {$userEmail}", 'INFO');
            
        } catch (Exception $e) {
            app_log("Error destruyendo sesiones de usuario {$userEmail}: " . $e->getMessage(), 'ERROR');
            throw new Exception("Error cerrando sesiones de usuario");
        }
    }
    
    /**
     * Limpiar sesiones expiradas
     */
    public function cleanupExpiredSessions($userEmail = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($userEmail) {
                $whereClause = ' AND usuario_email = ?';
                $params[] = $userEmail;
            }
            
            // Primero obtener las sesiones que van a ser limpiadas para actualizar estadísticas
            $stmt = $this->db->prepare("
                SELECT usuario_email, fecha_inicio 
                FROM mv_sesiones 
                WHERE fecha_expiracion < CURRENT_TIMESTAMP 
                AND activa = 1 
                {$whereClause}
            ");
            $stmt->execute($params);
            $expiredSessions = $stmt->fetchAll();
            
            // Actualizar estadísticas por cada sesión expirada
            foreach ($expiredSessions as $session) {
                $sessionStart = new DateTime($session['fecha_inicio']);
                $sessionEnd = new DateTime();
                $durationMinutes = round(($sessionEnd->getTimestamp() - $sessionStart->getTimestamp()) / 60);
                $this->updateSessionStatistics($session['usuario_email'], $durationMinutes);
            }
            
            // Marcar sesiones como inactivas
            $stmt = $this->db->prepare("
                UPDATE mv_sesiones 
                SET activa = 0, fecha_fin = CURRENT_TIMESTAMP 
                WHERE fecha_expiracion < CURRENT_TIMESTAMP 
                AND activa = 1 
                {$whereClause}
            ");
            
            $stmt->execute($params);
            $cleanedUp = $stmt->rowCount();
            
            if ($cleanedUp > 0) {
                app_log("Limpiadas {$cleanedUp} sesiones expiradas" . ($userEmail ? " para {$userEmail}" : ""), 'INFO');
            }
            
        } catch (Exception $e) {
            app_log("Error limpiando sesiones expiradas: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Obtener sesiones activas de un usuario
     */
    public function getUserActiveSessions($userEmail) {
        try {
            $stmt = $this->db->prepare("
                SELECT session_token, ip_address, user_agent, fecha_inicio, fecha_expiracion
                FROM mv_sesiones 
                WHERE usuario_email = ? 
                AND activa = 1 
                AND fecha_expiracion > CURRENT_TIMESTAMP
                ORDER BY fecha_inicio DESC
            ");
            
            $stmt->execute([$userEmail]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            app_log("Error obteniendo sesiones activas para {$userEmail}: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Generar token seguro
     */
    private function generateSecureToken() {
        // Usar random_bytes para máxima seguridad
        $randomBytes = random_bytes(32);
        return bin2hex($randomBytes);
    }
    
    /**
     * Establecer cookie de sesión
     */
    private function setSessionCookie($token, $duration) {
        $options = [
            'expires' => time() + $duration,
            'path' => '/',
            'domain' => '', // Usar dominio actual
            'secure' => $this->secureMode,
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        $success = setcookie($this->cookieName, $token, $options);
        
        if (!$success) {
            app_log("Error estableciendo cookie de sesión", 'WARNING');
        }
    }
    
    /**
     * Limpiar cookie de sesión
     */
    private function clearSessionCookie() {
        if (isset($_COOKIE[$this->cookieName])) {
            $options = [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => $this->secureMode,
                'httponly' => true,
                'samesite' => 'Strict'
            ];
            
            setcookie($this->cookieName, '', $options);
            unset($_COOKIE[$this->cookieName]);
        }
    }
    
    /**
     * Obtener IP del cliente
     */
    private function getClientIP() {
        // Verificar diferentes headers para obtener la IP real
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy/Load Balancer
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'               // Directo
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Si hay múltiples IPs, tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback a REMOTE_ADDR aunque sea privada
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Obtener User-Agent del cliente
     */
    private function getUserAgent() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Truncar si es muy largo
        if (strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }
        
        return $userAgent;
    }
    
    /**
     * Verificar si la sesión actual es válida
     */
    public function isValid() {
        return $this->verifySession() !== false;
    }
    
    /**
     * Obtener tiempo restante de sesión en segundos
     */
    public function getTimeRemaining() {
        if (!isset($_SESSION['last_activity'])) {
            return 0;
        }
        
        $elapsed = time() - $_SESSION['last_activity'];
        $remaining = $this->sessionDuration - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Renovar sesión (cambiar token por seguridad)
     */
    public function renewSession() {
        $currentUser = $this->verifySession();
        if ($currentUser) {
            // Destruir sesión actual
            $this->destroySession();
            
            // Crear nueva sesión
            return $this->createSession($currentUser['USUARIO_EMAIL']);
        }
        
        return false;
    }
}

// Log de inicialización
app_log("Módulo SessionManager (SQL estándar) cargado", 'INFO');
?>