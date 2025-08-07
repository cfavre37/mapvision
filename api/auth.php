<?php
/**
 * MapVision Analytics - Clase Principal de Autenticación (SQL Estándar)
 * Archivo: classes/Auth.php
 * Descripción: Orchestador principal del sistema de autenticación compatible con múltiples BD
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/UserValidator.php';
require_once __DIR__ . '/UserStatus.php';

class Auth {
    private $db;
    private $emailService;
    private $sessionManager;
    private $validator;
    private $userStatus;
    
    public function __construct() {
        try {
            $this->db = DatabaseConfig::getInstance()->getConnection();
            $this->emailService = new EmailService();
            $this->sessionManager = new SessionManager();
            $this->validator = new UserValidator();
            $this->userStatus = new UserStatus();
            
            // Iniciar sesión PHP si no está iniciada
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            app_log("Sistema de autenticación inicializado", 'INFO');
            
        } catch (Exception $e) {
            app_log("Error inicializando sistema de autenticación: " . $e->getMessage(), 'CRITICAL');
            throw new Exception("Error crítico en sistema de autenticación");
        }
    }
    
    /**
     * Registrar nuevo usuario
     */
    public function register($userData) {
        try {
            app_log("Iniciando registro de usuario: " . ($userData['email'] ?? 'email_no_proporcionado'), 'INFO');
            
            // Validar datos de entrada
            $validation = $this->validator->validateRegistration($userData);
            if (!$validation['valid']) {
                app_log("Validación fallida en registro: " . $validation['message'], 'WARNING');
                return $validation;
            }
            
            $sanitizedData = $validation['sanitized_data'];
            $email = $sanitizedData['email'];
            
            // Verificar si el email ya existe
            if ($this->emailExists($email)) {
                app_log("Intento de registro con email existente: {$email}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'El email ya está registrado',
                    'code' => 'EMAIL_EXISTS'
                ];
            }
            
            // Verificar límites de registro (si aplicable)
            if (!$this->checkRegistrationLimits()) {
                return [
                    'success' => false,
                    'message' => 'Límite de registros alcanzado temporalmente',
                    'code' => 'RATE_LIMIT'
                ];
            }
            
            DatabaseConfig::getInstance()->beginTransaction();
            
            try {
                // Encriptar contraseña
                $passwordHash = password_hash($sanitizedData['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Insertar usuario con SQL estándar
                $stmt = $this->db->prepare("
                    INSERT INTO mv_usuarios (email, password_hash, nombre, apellido, empresa, telefono, rol) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $success = $stmt->execute([
                    $email,
                    $passwordHash,
                    $sanitizedData['nombre'],
                    $sanitizedData['apellido'],
                    $sanitizedData['empresa'] ?? null,
                    $sanitizedData['telefono'] ?? null,
                    $sanitizedData['rol'] ?? 'Trial'
                ]);
                
                if (!$success) {
                    throw new Exception("Error insertando usuario en base de datos");
                }
                
                // Generar token de verificación
                $verificationResult = $this->generateEmailVerificationToken($email);
                if (!$verificationResult['success']) {
                    throw new Exception("Error generando token de verificación");
                }
                
                DatabaseConfig::getInstance()->commit();
                
                // Enviar email de verificación
                $emailResult = $this->emailService->sendVerificationEmail(
                    $email, 
                    $verificationResult['token'],
                    $sanitizedData['nombre']
                );
                
                // Log del registro exitoso
                $this->logAccess($email, 'register_success', 1, 'Usuario registrado exitosamente');
                
                app_log("Usuario registrado exitosamente: {$email}", 'INFO');
                
                return [
                    'success' => true, 
                    'message' => 'Usuario registrado exitosamente. Por favor verifica tu email.',
                    'email_sent' => $emailResult['success'],
                    'user_id' => $email
                ];
                
            } catch (Exception $e) {
                DatabaseConfig::getInstance()->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            app_log("Error en registro de usuario: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error interno del servidor',
                'code' => 'INTERNAL_ERROR'
            ];
        }
    }
    
    /**
     * Iniciar sesión
     */
    public function login($email, $password, $rememberMe = false) {
        try {
            app_log("Intento de login para: {$email}", 'INFO');
            
            // Validar datos de entrada
            $validation = $this->validator->validateLogin($email, $password);
            if (!$validation['valid']) {
                app_log("Validación de login fallida para {$email}: " . $validation['message'], 'WARNING');
                return $validation;
            }
            
            $sanitizedData = $validation['sanitized_data'];
            $email = $sanitizedData['email'];
            
            // Obtener usuario
            $user = $this->getUserByEmail($email);
            if (!$user) {
                $this->logAccess($email, 'login_failed', 0, 'Usuario no encontrado');
                app_log("Login fallido - Usuario no encontrado: {$email}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Credenciales inválidas',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }
            
            // Verificar si el usuario está bloqueado
            $blockCheck = $this->checkUserBlocked($user);
            if (!$blockCheck['allowed']) {
                app_log("Login bloqueado para {$email}: " . $blockCheck['reason'], 'WARNING');
                return [
                    'success' => false,
                    'message' => $blockCheck['message'],
                    'code' => 'USER_BLOCKED',
                    'blocked_until' => $blockCheck['blocked_until'] ?? null
                ];
            }
            
            // Verificar si está activo
            if (!$user['activo']) {
                $this->logAccess($email, 'login_failed', 0, 'Cuenta desactivada');
                app_log("Login fallido - Cuenta desactivada: {$email}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Cuenta desactivada. Contacta al administrador.',
                    'code' => 'ACCOUNT_DISABLED'
                ];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                $this->incrementFailedAttempts($email);
                $this->logAccess($email, 'login_failed', 0, 'Contraseña incorrecta');
                app_log("Login fallido - Contraseña incorrecta: {$email}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Credenciales inválidas',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }
            
            // Verificar si el email está verificado
            if (config('require_email_verification') && !$user['email_verificado']) {
                app_log("Login fallido - Email no verificado: {$email}", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Debes verificar tu email antes de iniciar sesión',
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'resend_verification' => true
                ];
            }
            
            // Login exitoso - limpiar intentos fallidos y actualizar estadísticas
            $this->resetFailedAttempts($email);
            $this->updateLoginStatistics($email);
            
            // Actualizar estado de conexión
            $this->userStatus->updateConnectionStatus($email, true);
            
            // Crear sesión
            $sessionToken = $this->sessionManager->createSession($email, $rememberMe);
            
            // Log del login exitoso
            $this->logAccess($email, 'login_success', 1, 'Login exitoso');
            
            app_log("Login exitoso para: {$email}", 'INFO');
            
            return [
                'success' => true,
                'message' => 'Login exitoso',
                'user' => [
                    'email' => $user['email'],
                    'nombre' => $user['nombre'],
                    'apellido' => $user['apellido'],
                    'rol' => $user['rol'],
                    'empresa' => $user['empresa']
                ],
                'session_token' => $sessionToken,
                'email_verificado' => $user['email_verificado'],
                'remember_me' => $rememberMe
            ];
            
        } catch (Exception $e) {
            app_log("Error en login para {$email}: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error interno del servidor',
                'code' => 'INTERNAL_ERROR'
            ];
        }
    }
    
    /**
     * Verificar sesión activa
     */
    public function verifySession($token = null) {
        try {
            $user = $this->sessionManager->verifySession($token);
            
            if ($user) {
                app_log("Sesión verificada para: " . $user['USUARIO_EMAIL'], 'DEBUG');
                return $user;
            }
            
            app_log("Sesión inválida o expirada", 'DEBUG');
            return false;
            
        } catch (Exception $e) {
            app_log("Error verificando sesión: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Cerrar sesión
     */
    public function logout($token = null) {
        try {
            // Obtener información del usuario antes de cerrar sesión
            $user = $this->sessionManager->verifySession($token);
            
            if ($user) {
                $email = $user['USUARIO_EMAIL'];
                
                // Actualizar duración de sesión y estadísticas
                $this->updateLogoutStatistics($email);
                
                // Actualizar estado de conexión
                $this->userStatus->updateConnectionStatus($email, false);
                
                // Log del logout
                $this->logAccess($email, 'logout', 1, 'Logout exitoso');
                
                app_log("Logout exitoso para: {$email}", 'INFO');
            }
            
            // Destruir sesión
            $this->sessionManager->destroySession($token);
            
            return [
                'success' => true, 
                'message' => 'Sesión cerrada exitosamente'
            ];
            
        } catch (Exception $e) {
            app_log("Error en logout: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error cerrando sesión'
            ];
        }
    }
    
    /**
     * Generar token de recuperación de contraseña
     */
    public function generatePasswordResetToken($email) {
        try {
            $email = $this->validator->sanitizeEmail($email);
            
            if (!$this->emailExists($email)) {
                app_log("Solicitud de reset para email inexistente: {$email}", 'WARNING');
                // Por seguridad, no revelar que el email no existe
                return [
                    'success' => true, 
                    'message' => 'Si el email existe, recibirás instrucciones para recuperar tu contraseña'
                ];
            }
            
            // Verificar rate limiting para reset de contraseña
            if (!$this->checkPasswordResetRateLimit($email)) {
                return [
                    'success' => false,
                    'message' => 'Demasiadas solicitudes. Intenta nuevamente en 15 minutos.',
                    'code' => 'RATE_LIMIT'
                ];
            }
            
            $token = bin2hex(random_bytes(32));
            $expiration = $this->formatTimestamp(time() + config('password_reset_expiry', 3600));
            
            // Invalidar tokens anteriores del usuario
            $stmt = $this->db->prepare("
                UPDATE mv_tokens 
                SET usado = 1 
                WHERE usuario_email = ? AND tipo = 'password_reset' AND usado = 0
            ");
            $stmt->execute([$email]);
            
            // Insertar nuevo token
            $stmt = $this->db->prepare("
                INSERT INTO mv_tokens (usuario_email, token, tipo, fecha_expiracion) 
                VALUES (?, ?, 'password_reset', ?)
            ");
            
            $stmt->execute([
                $email,
                $token,
                $expiration
            ]);
            
            // Obtener nombre del usuario para personalizar el email
            $user = $this->getUserByEmail($email);
            $userName = $user ? $user['nombre'] : '';
            
            // Enviar email
            $emailResult = $this->emailService->sendPasswordResetEmail($email, $token, $userName);
            
            // Log de la solicitud
            $this->logAccess($email, 'password_reset_requested', 1, 'Token de reset generado');
            
            app_log("Token de reset generado para: {$email}", 'INFO');
            
            return [
                'success' => true, 
                'message' => 'Instrucciones enviadas a tu email',
                'email_sent' => $emailResult['success']
            ];
            
        } catch (Exception $e) {
            app_log("Error generando token de reset para {$email}: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error interno del servidor'
            ];
        }
    }
    
    /**
     * Cambiar contraseña con token
     */
    public function resetPassword($token, $newPassword) {
        try {
            app_log("Intento de reset de contraseña con token", 'INFO');
            
            // Validar token
            $tokenValidation = $this->validator->validateToken($token);
            if (!$tokenValidation['valid']) {
                return $tokenValidation;
            }
            
            // Validar nueva contraseña
            $passwordValidation = $this->validator->validatePassword($newPassword);
            if (!$passwordValidation['valid']) {
                return $passwordValidation;
            }
            
            // Verificar token en base de datos
            $tokenData = $this->getValidToken($token, 'password_reset');
            if (!$tokenData) {
                app_log("Token de reset inválido o expirado: {$token}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Token inválido o expirado',
                    'code' => 'INVALID_TOKEN'
                ];
            }
            
            $email = $tokenData['usuario_email'];
            
            DatabaseConfig::getInstance()->beginTransaction();
            
            try {
                // Actualizar contraseña
                $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                
                $stmt = $this->db->prepare("
                    UPDATE mv_usuarios 
                    SET password_hash = ?, intentos_fallidos = 0, bloqueado_hasta = NULL 
                    WHERE email = ?
                ");
                $stmt->execute([$passwordHash, $email]);
                
                // Marcar token como usado
                $this->markTokenAsUsed($token);
                
                // Cerrar todas las sesiones del usuario
                $this->sessionManager->destroyAllUserSessions($email);
                
                DatabaseConfig::getInstance()->commit();
                
                // Log del cambio de contraseña
                $this->logAccess($email, 'password_reset_completed', 1, 'Contraseña cambiada via token');
                
                app_log("Contraseña cambiada exitosamente para: {$email}", 'INFO');
                
                return [
                    'success' => true, 
                    'message' => 'Contraseña actualizada exitosamente'
                ];
                
            } catch (Exception $e) {
                DatabaseConfig::getInstance()->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            app_log("Error en reset de contraseña: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error interno del servidor'
            ];
        }
    }
    
    /**
     * Cambiar contraseña (usuario autenticado)
     */
    public function changePassword($userEmail, $currentPassword, $newPassword) {
        try {
            app_log("Cambio de contraseña solicitado para: {$userEmail}", 'INFO');
            
            // Validar nueva contraseña
            $passwordValidation = $this->validator->validatePassword($newPassword);
            if (!$passwordValidation['valid']) {
                return $passwordValidation;
            }
            
            // Verificar contraseña actual
            $user = $this->getUserByEmail($userEmail);
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                app_log("Contraseña actual incorrecta para {$userEmail}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Contraseña actual incorrecta',
                    'code' => 'INVALID_CURRENT_PASSWORD'
                ];
            }
            
            // Verificar que la nueva contraseña sea diferente
            if (password_verify($newPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'La nueva contraseña debe ser diferente a la actual',
                    'code' => 'SAME_PASSWORD'
                ];
            }
            
            // Actualizar contraseña
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $this->db->prepare("
                UPDATE mv_usuarios 
                SET password_hash = ? 
                WHERE email = ?
            ");
            $stmt->execute([$passwordHash, $userEmail]);
            
            // Log del cambio
            $this->logAccess($userEmail, 'password_changed', 1, 'Contraseña cambiada por usuario');
            
            app_log("Contraseña cambiada exitosamente para: {$userEmail}", 'INFO');
            
            return [
                'success' => true, 
                'message' => 'Contraseña actualizada exitosamente'
            ];
            
        } catch (Exception $e) {
            app_log("Error cambiando contraseña para {$userEmail}: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error interno del servidor'
            ];
        }
    }
    
    /**
     * Verificar email con token
     */
    public function verifyEmail($token) {
        try {
            app_log("Verificando email con token", 'INFO');
            
            // Validar token
            $tokenValidation = $this->validator->validateToken($token);
            if (!$tokenValidation['valid']) {
                return $tokenValidation;
            }
            
            // Verificar token en base de datos
            $tokenData = $this->getValidToken($token, 'email_verification');
            if (!$tokenData) {
                app_log("Token de verificación inválido: {$token}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Token de verificación inválido o expirado'
                ];
            }
            
            $email = $tokenData['usuario_email'];
            
            DatabaseConfig::getInstance()->beginTransaction();
            
            try {
                // Marcar email como verificado
                $stmt = $this->db->prepare("
                    UPDATE mv_usuarios 
                    SET email_verificado = 1 
                    WHERE email = ?
                ");
                $stmt->execute([$email]);
                
                // Marcar token como usado
                $this->markTokenAsUsed($token);
                
                DatabaseConfig::getInstance()->commit();
                
                // Enviar email de bienvenida
                $user = $this->getUserByEmail($email);
                if ($user) {
                    $this->emailService->sendWelcomeEmail($email, $user['nombre']);
                }
                
                // Log de verificación
                $this->logAccess($email, 'email_verified', 1, 'Email verificado exitosamente');
                
                app_log("Email verificado exitosamente: {$email}", 'INFO');
                
                return [
                    'success' => true, 
                    'message' => 'Email verificado exitosamente'
                ];
                
            } catch (Exception $e) {
                DatabaseConfig::getInstance()->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            app_log("Error verificando email: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false, 
                'message' => 'Error verificando email'
            ];
        }
    }
    
    // ================================================================
    // MÉTODOS AUXILIARES PRIVADOS - SQL ESTÁNDAR
    // ================================================================
    
    /**
     * Formatear timestamp según el motor de BD
     */
    private function formatTimestamp($timestamp) {
        $dbType = DatabaseConfig::getInstance()->getConnectionStats()['driver'];
        
        switch ($dbType) {
            case 'oracle':
                return date('Y-m-d H:i:s', $timestamp);
            case 'postgresql':
            case 'sqlite':
                return date('Y-m-d H:i:s', $timestamp);
            default:
                return date('Y-m-d H:i:s', $timestamp);
        }
    }
    
    /**
     * Verificar si email existe
     */
    private function emailExists($email) {
        $stmt = $this->db->prepare("SELECT 1 FROM mv_usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Obtener usuario por email
     */
    private function getUserByEmail($email) {
        $stmt = $this->db->prepare("
            SELECT email, password_hash, nombre, apellido, empresa, rol, activo, 
                   intentos_fallidos, bloqueado_hasta, email_verificado, fecha_creacion
            FROM mv_usuarios WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    /**
     * Verificar si usuario está bloqueado
     */
    private function checkUserBlocked($user) {
        if ($user['bloqueado_hasta'] && new DateTime($user['bloqueado_hasta']) > new DateTime()) {
            return [
                'allowed' => false,
                'reason' => 'blocked_temporarily',
                'message' => 'Cuenta temporalmente bloqueada por múltiples intentos fallidos',
                'blocked_until' => $user['bloqueado_hasta']
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Incrementar intentos fallidos con SQL estándar
     */
    private function incrementFailedAttempts($email) {
        $maxAttempts = config('max_login_attempts', 5);
        $lockoutDuration = config('lockout_duration', 900); // 15 minutos
        
        // Obtener intentos actuales
        $stmt = $this->db->prepare("SELECT intentos_fallidos FROM mv_usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $newAttempts = $user['intentos_fallidos'] + 1;
            $blockedUntil = null;
            
            // Si excede máximo, bloquear temporalmente
            if ($newAttempts >= $maxAttempts) {
                $blockedUntil = $this->formatTimestamp(time() + $lockoutDuration);
            }
            
            // Actualizar intentos y posible bloqueo
            $stmt = $this->db->prepare("
                UPDATE mv_usuarios 
                SET intentos_fallidos = ?, bloqueado_hasta = ?
                WHERE email = ?
            ");
            $stmt->execute([$newAttempts, $blockedUntil, $email]);
            
            // Log si se bloquea la cuenta
            if ($newAttempts >= $maxAttempts) {
                $this->logAccess($email, 'account_locked', 1, "Cuenta bloqueada después de {$newAttempts} intentos fallidos");
                app_log("Cuenta bloqueada por intentos fallidos: {$email}", 'WARNING');
            }
        }
    }
    
    /**
     * Resetear intentos fallidos
     */
    private function resetFailedAttempts($email) {
        $stmt = $this->db->prepare("
            UPDATE mv_usuarios 
            SET intentos_fallidos = 0, bloqueado_hasta = NULL 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    /**
     * Actualizar estadísticas de login (reemplaza trigger)
     */
    private function updateLoginStatistics($email) {
        try {
            // Verificar si ya existe registro de estadísticas
            $stmt = $this->db->prepare("
                SELECT total_logins FROM mv_estadisticas_usuario 
                WHERE usuario_email = ?
            ");
            $stmt->execute([$email]);
            $stats = $stmt->fetch();
            
            if ($stats) {
                // Actualizar existente
                $stmt = $this->db->prepare("
                    UPDATE mv_estadisticas_usuario 
                    SET total_logins = total_logins + 1,
                        ultima_actualizacion = CURRENT_TIMESTAMP
                    WHERE usuario_email = ?
                ");
                $stmt->execute([$email]);
            } else {
                // Crear nuevo registro
                $stmt = $this->db->prepare("
                    INSERT INTO mv_estadisticas_usuario (usuario_email, total_logins, ultima_actualizacion)
                    VALUES (?, 1, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$email]);
            }
            
        } catch (Exception $e) {
            app_log("Error actualizando estadísticas de login: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Actualizar estadísticas de logout (reemplaza trigger)
     */
    private function updateLogoutStatistics($email) {
        try {
            // Obtener sesión activa más reciente
            $stmt = $this->db->prepare("
                SELECT fecha_inicio 
                FROM mv_sesiones 
                WHERE usuario_email = ? AND activa = 1 
                ORDER BY fecha_inicio DESC 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $session = $stmt->fetch();
            
            if ($session) {
                // Calcular duración en minutos
                $sessionStart = new DateTime($session['fecha_inicio']);
                $sessionEnd = new DateTime();
                $durationMinutes = $sessionEnd->getTimestamp() - $sessionStart->getTimestamp();
                $durationMinutes = round($durationMinutes / 60);
                
                // Actualizar estadísticas
                $stmt = $this->db->prepare("
                    SELECT total_tiempo_conectado, sesion_mas_larga, total_logins 
                    FROM mv_estadisticas_usuario 
                    WHERE usuario_email = ?
                ");
                $stmt->execute([$email]);
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
                    $stmt->execute([$newTotalTime, $newLongestSession, $newAverage, $email]);
                }
            }
            
        } catch (Exception $e) {
            app_log("Error actualizando estadísticas de logout: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Generar token de verificación de email
     */
    private function generateEmailVerificationToken($email) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiration = $this->formatTimestamp(time() + config('email_verification_expiry', 86400));
            
            $stmt = $this->db->prepare("
                INSERT INTO mv_tokens (usuario_email, token, tipo, fecha_expiracion) 
                VALUES (?, ?, 'email_verification', ?)
            ");
            
            $stmt->execute([$email, $token, $expiration]);
            
            return ['success' => true, 'token' => $token];
            
        } catch (Exception $e) {
            app_log("Error generando token de verificación para {$email}: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error generando token'];
        }
    }
    
    /**
     * Obtener token válido
     */
    private function getValidToken($token, $type) {
        $stmt = $this->db->prepare("
            SELECT usuario_email 
            FROM mv_tokens 
            WHERE token = ? 
            AND tipo = ? 
            AND usado = 0 
            AND fecha_expiracion > CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([$token, $type]);
        return $stmt->fetch();
    }
    
    /**
     * Marcar token como usado
     */
    private function markTokenAsUsed($token) {
        $stmt = $this->db->prepare("UPDATE mv_tokens SET usado = 1 WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    /**
     * Registrar acceso en log
     */
    private function logAccess($email, $action, $success = 1, $details = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mv_log_accesos (usuario_email, accion, ip_address, user_agent, detalles, exito) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $email,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $details,
                $success
            ]);
            
        } catch (Exception $e) {
            app_log("Error logging access: " . $e->getMessage(), 'WARNING');
        }
    }
    
    /**
     * Verificar límites de registro
     */
    private function checkRegistrationLimits() {
        // Verificar límites de registro por IP (opcional)
        $registrationLimit = config('registration_limit_per_ip_per_hour', 5);
        
        if ($registrationLimit > 0) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Calcular timestamp de hace 1 hora
            $oneHourAgo = $this->formatTimestamp(time() - 3600);
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM mv_log_accesos 
                WHERE accion = 'register_success' 
                AND ip_address = ? 
                AND fecha > ?
            ");
            $stmt->execute([$ip, $oneHourAgo]);
            $result = $stmt->fetch();
            $count = $result['count'];
            
            if ($count >= $registrationLimit) {
                app_log("Límite de registro alcanzado para IP: {$ip}", 'WARNING');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verificar rate limit para reset de contraseña
     */
    private function checkPasswordResetRateLimit($email) {
        // Calcular timestamp de hace 15 minutos
        $fifteenMinutesAgo = $this->formatTimestamp(time() - 900);
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM mv_log_accesos 
            WHERE accion = 'password_reset_requested' 
            AND usuario_email = ? 
            AND fecha > ?
        ");
        $stmt->execute([$email, $fifteenMinutesAgo]);
        $result = $stmt->fetch();
        
        return $result['count'] < 3; // Máximo 3 solicitudes cada 15 minutos
    }
}

// Log de inicialización
app_log("Clase Auth (SQL estándar) cargada exitosamente", 'INFO');
?>