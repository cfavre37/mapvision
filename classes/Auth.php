<?php
/**
 * MapVision Analytics - Clase Principal de Autenticación
 * Archivo: classes/Auth.php
 * Descripción: Orchestador principal del sistema de autenticación
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
            
            $this->db->beginTransaction();
            
            try {
                // Encriptar contraseña
                $passwordHash = password_hash($sanitizedData['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Insertar usuario
                $stmt = $this->db->prepare("
                    INSERT INTO mv_usuarios (email, password_hash, nombre, apellido, empresa, telefono, rol) 
                    VALUES (:email, :password_hash, :nombre, :apellido, :empresa, :telefono, :rol)
                ");
                
                $success = $stmt->execute([
                    ':email' => $email,
                    ':password_hash' => $passwordHash,
                    ':nombre' => $sanitizedData['nombre'],
                    ':apellido' => $sanitizedData['apellido'],
                    ':empresa' => $sanitizedData['empresa'] ?? null,
                    ':telefono' => $sanitizedData['telefono'] ?? null,
                    ':rol' => $sanitizedData['rol'] ?? 'Trial'
                ]);
                
                if (!$success) {
                    throw new Exception("Error insertando usuario en base de datos");
                }
                
                // Generar token de verificación
                $verificationResult = $this->generateEmailVerificationToken($email);
                if (!$verificationResult['success']) {
                    throw new Exception("Error generando token de verificación");
                }
                
                $this->db->commit();
                
                // Enviar email de verificación
                $emailResult = $this->emailService->sendVerificationEmail(
                    $email, 
                    $verificationResult['token'],
                    $sanitizedData['nombre']
                );
                
                // Log del registro exitoso
                $this->logAccess($email, 'register_success', null, 'Usuario registrado exitosamente');
                
                app_log("Usuario registrado exitosamente: {$email}", 'INFO');
                
                return [
                    'success' => true, 
                    'message' => 'Usuario registrado exitosamente. Por favor verifica tu email.',
                    'email_sent' => $emailResult['success'],
                    'user_id' => $email
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
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
                $this->logAccess($email, 'login_failed', null, 'Usuario no encontrado');
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
            if (!$user['ACTIVO']) {
                $this->logAccess($email, 'login_failed', null, 'Cuenta desactivada');
                app_log("Login fallido - Cuenta desactivada: {$email}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Cuenta desactivada. Contacta al administrador.',
                    'code' => 'ACCOUNT_DISABLED'
                ];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['PASSWORD_HASH'])) {
                $this->incrementFailedAttempts($email);
                $this->logAccess($email, 'login_failed', null, 'Contraseña incorrecta');
                app_log("Login fallido - Contraseña incorrecta: {$email}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Credenciales inválidas',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }
            
            // Verificar si el email está verificado
            if (config('require_email_verification') && !$user['EMAIL_VERIFICADO']) {
                app_log("Login fallido - Email no verificado: {$email}", 'WARNING');
                return [
                    'success' => false,
                    'message' => 'Debes verificar tu email antes de iniciar sesión',
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'resend_verification' => true
                ];
            }
            
            // Login exitoso - limpiar intentos fallidos
            $this->resetFailedAttempts($email);
            
            // Actualizar estado de conexión
            $this->userStatus->updateConnectionStatus($email, true);
            
            // Crear sesión
            $sessionToken = $this->sessionManager->createSession($email, $rememberMe);
            
            // Log del login exitoso
            $this->logAccess($email, 'login_success', null, 'Login exitoso');
            
            app_log("Login exitoso para: {$email}", 'INFO');
            
            return [
                'success' => true,
                'message' => 'Login exitoso',
                'user' => [
                    'email' => $user['EMAIL'],
                    'nombre' => $user['NOMBRE'],
                    'apellido' => $user['APELLIDO'],
                    'rol' => $user['ROL'],
                    'empresa' => $user['EMPRESA']
                ],
                'session_token' => $sessionToken,
                'email_verificado' => $user['EMAIL_VERIFICADO'],
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
                
                // Actualizar estado de conexión
                $this->userStatus->updateConnectionStatus($email, false);
                
                // Log del logout
                $this->logAccess($email, 'logout', null, 'Logout exitoso');
                
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
            $expiration = date('Y-m-d H:i:s', time() + config('password_reset_expiry', 3600));
            
            // Invalidar tokens anteriores del usuario
            $stmt = $this->db->prepare("
                UPDATE mv_tokens 
                SET usado = 1 
                WHERE usuario_email = :email AND tipo = 'password_reset' AND usado = 0
            ");
            $stmt->execute([':email' => $email]);
            
            // Insertar nuevo token
            $stmt = $this->db->prepare("
                INSERT INTO mv_tokens (usuario_email, token, tipo, fecha_expiracion) 
                VALUES (:email, :token, 'password_reset', TO_TIMESTAMP(:expiration, 'YYYY-MM-DD HH24:MI:SS'))
            ");
            
            $stmt->execute([
                ':email' => $email,
                ':token' => $token,
                ':expiration' => $expiration
            ]);
            
            // Obtener nombre del usuario para personalizar el email
            $user = $this->getUserByEmail($email);
            $userName = $user ? $user['NOMBRE'] : '';
            
            // Enviar email
            $emailResult = $this->emailService->sendPasswordResetEmail($email, $token, $userName);
            
            // Log de la solicitud
            $this->logAccess($email, 'password_reset_requested', null, 'Token de reset generado');
            
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
            
            $email = $tokenData['USUARIO_EMAIL'];
            
            $this->db->beginTransaction();
            
            try {
                // Actualizar contraseña
                $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                
                $stmt = $this->db->prepare("
                    UPDATE mv_usuarios 
                    SET password_hash = :hash, intentos_fallidos = 0, bloqueado_hasta = NULL 
                    WHERE email = :email
                ");
                $stmt->execute([
                    ':hash' => $passwordHash,
                    ':email' => $email
                ]);
                
                // Marcar token como usado
                $this->markTokenAsUsed($token);
                
                // Cerrar todas las sesiones del usuario
                $this->sessionManager->destroyAllUserSessions($email);
                
                $this->db->commit();
                
                // Log del cambio de contraseña
                $this->logAccess($email, 'password_reset_completed', null, 'Contraseña cambiada via token');
                
                app_log("Contraseña cambiada exitosamente para: {$email}", 'INFO');
                
                return [
                    'success' => true, 
                    'message' => 'Contraseña actualizada exitosamente'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
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
            if (!$user || !password_verify($currentPassword, $user['PASSWORD_HASH'])) {
                app_log("Contraseña actual incorrecta para {$userEmail}", 'WARNING');
                return [
                    'success' => false, 
                    'message' => 'Contraseña actual incorrecta',
                    'code' => 'INVALID_CURRENT_PASSWORD'
                ];
            }
            
            // Verificar que la nueva contraseña sea diferente
            if (password_verify($newPassword, $user['PASSWORD_HASH'])) {
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
                SET password_hash = :hash 
                WHERE email = :email
            ");
            $stmt->execute([
                ':hash' => $passwordHash,
                ':email' => $userEmail
            ]);
            
            // Log del cambio
            $this->logAccess($userEmail, 'password_changed', null, 'Contraseña cambiada por usuario');
            
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
            
            $email = $tokenData['USUARIO_EMAIL'];
            
            $this->db->beginTransaction();
            
            try {
                // Marcar email como verificado
                $stmt = $this->db->prepare("
                    UPDATE mv_usuarios 
                    SET email_verificado = 1 
                    WHERE email = :email
                ");
                $stmt->execute([':email' => $email]);
                
                // Marcar token como usado
                $this->markTokenAsUsed($token);
                
                $this->db->commit();
                
                // Enviar email de bienvenida
                $user = $this->getUserByEmail($email);
                if ($user) {
                    $this->emailService->sendWelcomeEmail($email, $user['NOMBRE']);
                }
                
                // Log de verificación
                $this->logAccess($email, 'email_verified', null, 'Email verificado exitosamente');
                
                app_log("Email verificado exitosamente: {$email}", 'INFO');
                
                return [
                    'success' => true, 
                    'message' => 'Email verificado exitosamente'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
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
    
    // Métodos auxiliares privados
    
    private function emailExists($email) {
        $stmt = $this->db->prepare("SELECT 1 FROM mv_usuarios WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->rowCount() > 0;
    }
    
    private function getUserByEmail($email) {
        $stmt = $this->db->prepare("
            SELECT email, password_hash, nombre, apellido, empresa, rol, activo, 
                   intentos_fallidos, bloqueado_hasta, email_verificado, fecha_creacion
            FROM mv_usuarios WHERE email = :email
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }
    
    private function checkUserBlocked($user) {
        if ($user['BLOQUEADO_HASTA'] && new DateTime($user['BLOQUEADO_HASTA']) > new DateTime()) {
            return [
                'allowed' => false,
                'reason' => 'blocked_temporarily',
                'message' => 'Cuenta temporalmente bloqueada por múltiples intentos fallidos',
                'blocked_until' => $user['BLOQUEADO_HASTA']
            ];
        }
        
        return ['allowed' => true];
    }
    
    private function incrementFailedAttempts($email) {
        $maxAttempts = config('max_login_attempts', 5);
        $lockoutDuration = config('lockout_duration', 900); // 15 minutos
        
        $stmt = $this->db->prepare("
            UPDATE mv_usuarios 
            SET intentos_fallidos = intentos_fallidos + 1,
                bloqueado_hasta = CASE 
                    WHEN intentos_fallidos + 1 >= :max_attempts 
                    THEN CURRENT_TIMESTAMP + INTERVAL ':lockout' SECOND
                    ELSE bloqueado_hasta 
                END
            WHERE email = :email
        ");
        
        $stmt->execute([
            ':email' => $email,
            ':max_attempts' => $maxAttempts,
            ':lockout' => $lockoutDuration
        ]);
        
        // Log si se bloquea la cuenta
        $stmt2 = $this->db->prepare("SELECT intentos_fallidos FROM mv_usuarios WHERE email = :email");
        $stmt2->execute([':email' => $email]);
        $attempts = $stmt2->fetch()['INTENTOS_FALLIDOS'];
        
        if ($attempts >= $maxAttempts) {
            $this->logAccess($email, 'account_locked', null, "Cuenta bloqueada después de {$attempts} intentos fallidos");
            app_log("Cuenta bloqueada por intentos fallidos: {$email}", 'WARNING');
        }
    }
    
    private function resetFailedAttempts($email) {
        $stmt = $this->db->prepare("
            UPDATE mv_usuarios 
            SET intentos_fallidos = 0, bloqueado_hasta = NULL 
            WHERE email = :email
        ");
        $stmt->execute([':email' => $email]);
    }
    
    private function generateEmailVerificationToken($email) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiration = date('Y-m-d H:i:s', time() + config('email_verification_expiry', 86400));
            
            $stmt = $this->db->prepare("
                INSERT INTO mv_tokens (usuario_email, token, tipo, fecha_expiracion) 
                VALUES (:email, :token, 'email_verification', TO_TIMESTAMP(:expiration, 'YYYY-MM-DD HH24:MI:SS'))
            ");
            
            $stmt->execute([
                ':email' => $email,
                ':token' => $token,
                ':expiration' => $expiration
            ]);
            
            return ['success' => true, 'token' => $token];
            
        } catch (Exception $e) {
            app_log("Error generando token de verificación para {$email}: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error generando token'];
        }
    }
    
    private function getValidToken($token, $type) {
        $stmt = $this->db->prepare("
            SELECT usuario_email 
            FROM mv_tokens 
            WHERE token = :token 
            AND tipo = :type 
            AND usado = 0 
            AND fecha_expiracion > CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            ':token' => $token,
            ':type' => $type
        ]);
        
        return $stmt->fetch();
    }
    
    private function markTokenAsUsed($token) {
        $stmt = $this->db->prepare("UPDATE mv_tokens SET usado = 1 WHERE token = :token");
        $stmt->execute([':token' => $token]);
    }
    
    private function logAccess($email, $action, $success = 1, $details = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mv_log_accesos (usuario_email, accion, ip_address, user_agent, detalles, exito) 
                VALUES (:email, :action, :ip, :user_agent, :details, :exito)
            ");
            
            $stmt->execute([
                ':email' => $email,
                ':action' => $action,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ':details' => $details,
                ':exito' => $success
            ]);
            
        } catch (Exception $e) {
            app_log("Error logging access: " . $e->getMessage(), 'WARNING');
        }
    }
    
    private function checkRegistrationLimits() {
        // Verificar límites de registro por IP (opcional)
        $registrationLimit = config('registration_limit_per_ip_per_hour', 5);
        
        if ($registrationLimit > 0) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM mv_log_accesos 
                WHERE accion = 'register_success' 
                AND ip_address = :ip 
                AND fecha > CURRENT_TIMESTAMP - INTERVAL '1' HOUR
            ");
            $stmt->execute([':ip' => $ip]);
            $count = $stmt->fetch()['COUNT'];
            
            if ($count >= $registrationLimit) {
                app_log("Límite de registro alcanzado para IP: {$ip}", 'WARNING');
                return false;
            }
        }
        
        return true;
    }
    
    private function checkPasswordResetRateLimit($email) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM mv_log_accesos 
            WHERE accion = 'password_reset_requested' 
            AND usuario_email = :email 
            AND fecha > CURRENT_TIMESTAMP - INTERVAL '15' MINUTE
        ");
        $stmt->execute([':email' => $email]);
        $count = $stmt->fetch()['COUNT'];
        
        return $count < 3; // Máximo 3 solicitudes cada 15 minutos
    }
}

// Log de inicialización
app_log("Clase Auth (principal) cargada exitosamente", 'INFO');
?>