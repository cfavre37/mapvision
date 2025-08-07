<?php
/**
 * MapVision Analytics - Reset de Contraseña
 * Archivo: reset-password.php
 * Descripción: Página para restablecer contraseña con token
 */

// Cargar dependencias
require_once __DIR__ . '/classes/Auth.php';

// Variables para la página
$token = $_GET['token'] ?? '';
$message = '';
$success = false;
$showForm = false;
$pageTitle = 'Restablecer Contraseña';
$redirectUrl = '';
$redirectDelay = 0;

// Verificar token si se proporciona
if (!empty($token)) {
    try {
        $auth = new Auth();
        
        // Validar token básico
        $tokenValidation = (new UserValidator())->validateToken($token);
        if (!$tokenValidation['valid']) {
            $success = false;
            $message = $tokenValidation['message'];
        } else {
            // Verificar que el token existe y no ha expirado
            $db = DatabaseConfig::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT usuario_email, fecha_expiracion 
                FROM mv_tokens 
                WHERE token = :token 
                AND tipo = 'password_reset' 
                AND usado = 0 
                AND fecha_expiracion > CURRENT_TIMESTAMP
            ");
            $stmt->execute([':token' => $token]);
            $tokenData = $stmt->fetch();
            
            if ($tokenData) {
                $showForm = true;
                app_log("Token de reset válido accedido: " . substr($token, 0, 10) . "...", 'INFO');
            } else {
                $success = false;
                $message = 'El enlace de restablecimiento ha expirado o ya fue utilizado.';
                app_log("Token de reset inválido o expirado: " . substr($token, 0, 10) . "...", 'WARNING');
            }
        }
        
    } catch (Exception $e) {
        $success = false;
        $message = 'Error verificando el enlace. Por favor intenta nuevamente.';
        app_log("Error verificando token de reset: " . $e->getMessage(), 'ERROR');
    }
} else {
    $success = false;
    $message = 'Enlace de restablecimiento no válido.';
    app_log("Acceso a reset-password sin token", 'WARNING');
}

// Procesar formulario de reset si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    try {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validaciones básicas
        if (empty($password) || empty($confirmPassword)) {
            throw new Exception('Todos los campos son requeridos');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        if (strlen($password) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres');
        }
        
        // Procesar reset con la clase Auth
        $auth = new Auth();
        $result = $auth->resetPassword($token, $password);
        
        if ($result['success']) {
            $success = true;
            $message = $result['message'];
            $showForm = false;
            $redirectUrl = 'index.html#login';
            $redirectDelay = 5000; // 5 segundos
            app_log("Contraseña restablecida exitosamente con token: " . substr($token, 0, 10) . "...", 'INFO');
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e) {
        $success = false;
        $message = $e->getMessage();
        app_log("Error en reset de contraseña: " . $e->getMessage(), 'ERROR');
    }
}

// Función para generar nueva solicitud de reset
function handleNewResetRequest() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
        try {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Formato de email inválido'
                ];
            }
            
            $auth = new Auth();
            $result = $auth->generatePasswordResetToken($email);
            
            return $result;
            
        } catch (Exception $e) {
            app_log("Error en nueva solicitud de reset: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Error interno del servidor'
            ];
        }
    }
    
    return null;
}

// Procesar nueva solicitud si es POST sin token válido
$newRequestResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$showForm) {
    $newRequestResult = handleNewResetRequest();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - MapVision Analytics</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Meta tags para SEO y redes sociales -->
    <meta name="description" content="Restablece tu contraseña de MapVision Analytics de forma segura">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        
        .reset-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .reset-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .reset-content {
            padding: 40px 30px;
        }
        
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
        }
        
        .status-icon.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-icon.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-icon.form {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-message {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 20px;
            line-height: 1.5;
            text-align: center;
        }
        
        .status-message.success {
            color: #155724;
        }
        
        .status-message.error {
            color: #721c24;
        }
        
        .status-description {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
            text-align: center;
        }
        
        .reset-form {
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #495057;
        }
        
        .form-input {
            width: 100%;
            padding: 15px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-input.error {
            border-color: #dc3545;
        }
        
        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
            background: linear-gradient(90deg, #dc3545, #ffc107, #28a745);
        }
        
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
            line-height: 1.4;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 2px;
        }
        
        .requirement.met {
            color: #28a745;
        }
        
        .btn {
            width: 100%;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .new-request-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
        }
        
        .new-request-section h3 {
            margin-bottom: 15px;
            color: #495057;
            text-align: center;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .footer-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 30px;
            line-height: 1.5;
            text-align: center;
        }
        
        .footer-text a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .reset-container {
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }
            
            .reset-header,
            .reset-content {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <!-- Header -->
        <div class="reset-header">
            <h1>🔑 MapVision Analytics</h1>
            <p>Restablecer Contraseña</p>
        </div>
        
        <!-- Content -->
        <div class="reset-content">
            <?php if ($showForm): ?>
                <!-- Formulario de nueva contraseña -->
                <div class="status-icon form">
                    🔐
                </div>
                
                <div class="status-message">
                    Ingresa tu nueva contraseña
                </div>
                
                <div class="status-description">
                    Elige una contraseña segura que no hayas usado antes en esta cuenta.
                </div>
                
                <form method="POST" class="reset-form" id="resetForm">
                    <div class="form-group">
                        <label for="password" class="form-label">Nueva Contraseña:</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Mínimo 8 caracteres"
                            required
                            autocomplete="new-password"
                            oninput="checkPasswordStrength()"
                        >
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="password-requirements" id="requirements">
                            <div class="requirement" id="lengthReq">
                                <span>○</span> Al menos 8 caracteres
                            </div>
                            <div class="requirement" id="upperReq">
                                <span>○</span> Una letra mayúscula
                            </div>
                            <div class="requirement" id="lowerReq">
                                <span>○</span> Una letra minúscula
                            </div>
                            <div class="requirement" id="numberReq">
                                <span>○</span> Un número
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña:</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Repite tu nueva contraseña"
                            required
                            autocomplete="new-password"
                            oninput="checkPasswordMatch()"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="resetButton">
                        Actualizar Contraseña
                    </button>
                </form>
                
            <?php elseif ($success): ?>
                <!-- Éxito -->
                <div class="status-icon success">
                    ✅
                </div>
                
                <div class="status-message success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                
                <div class="status-description">
                    Tu contraseña ha sido actualizada correctamente. Ya puedes iniciar sesión con tu nueva contraseña.
                </div>
                
                <a href="index.html#login" class="btn btn-primary">
                    Iniciar Sesión
                </a>
                
                <a href="index.html" class="btn btn-secondary">
                    Volver al Inicio
                </a>
                
                <?php if ($redirectUrl && $redirectDelay > 0): ?>
                    <script>
                        setTimeout(function() {
                            window.location.href = '<?php echo $redirectUrl; ?>';
                        }, <?php echo $redirectDelay; ?>);
                    </script>
                    
                    <div class="footer-text">
                        Serás redirigido automáticamente en <span id="countdown">5</span> segundos...
                    </div>
                    
                    <script>
                        let countdown = 5;
                        const countdownElement = document.getElementById('countdown');
                        const timer = setInterval(function() {
                            countdown--;
                            if (countdownElement) {
                                countdownElement.textContent = countdown;
                            }
                            if (countdown <= 0) {
                                clearInterval(timer);
                            }
                        }, 1000);
                    </script>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Error o token inválido -->
                <div class="status-icon error">
                    ❌
                </div>
                
                <div class="status-message error">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                
                <div class="status-description">
                    El enlace puede haber expirado o ya fue utilizado. Puedes solicitar un nuevo enlace de restablecimiento.
                </div>
                
                <a href="index.html#login" class="btn btn-secondary">
                    Volver al Login
                </a>
                
                <!-- Sección para nueva solicitud -->
                <div class="new-request-section">
                    <h3>Solicitar Nuevo Enlace</h3>
                    
                    <?php if ($newRequestResult): ?>
                        <div class="alert <?php echo $newRequestResult['success'] ? 'alert-success' : 'alert-error'; ?>">
                            <?php echo htmlspecialchars($newRequestResult['message']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="reset-form" id="newRequestForm">
                        <div class="form-group">
                            <label for="email" class="form-label">Email:</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input" 
                                placeholder="tu@email.com"
                                required
                                autocomplete="email"
                            >
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="newRequestButton">
                            Enviar Nuevo Enlace
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="footer-text">
                ¿Necesitas ayuda? Contacta a nuestro 
                <a href="mailto:support@mapvision.com">equipo de soporte</a>
                <br>
                <a href="index.html">← Volver a MapVision Analytics</a>
            </div>
        </div>
    </div>
    
    <script>
        // Verificar fortaleza de contraseña
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            
            let score = 0;
            const requirements = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password)
            };
            
            // Calcular puntuación
            if (requirements.length) score += 25;
            if (requirements.upper) score += 25;
            if (requirements.lower) score += 25;
            if (requirements.number) score += 25;
            
            // Actualizar barra visual
            strengthBar.style.width = score + '%';
            
            // Actualizar requisitos visuales
            updateRequirement('lengthReq', requirements.length);
            updateRequirement('upperReq', requirements.upper);
            updateRequirement('lowerReq', requirements.lower);
            updateRequirement('numberReq', requirements.number);
            
            // Habilitar/deshabilitar botón
            const resetButton = document.getElementById('resetButton');
            if (resetButton) {
                resetButton.disabled = score < 75;
            }
            
            return score;
        }
        
        // Actualizar estado visual de requisito
        function updateRequirement(elementId, met) {
            const element = document.getElementById(elementId);
            if (element) {
                element.classList.toggle('met', met);
                const icon = element.querySelector('span');
                if (icon) {
                    icon.textContent = met ? '✓' : '○';
                }
            }
        }
        
        // Verificar coincidencia de contraseñas
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    confirmInput.classList.remove('error');
                } else {
                    confirmInput.classList.add('error');
                }
            } else {
                confirmInput.classList.remove('error');
            }
        }
        
        // Manejar envío del formulario
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const button = document.getElementById('resetButton');
            
            // Validaciones finales
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return;
            }
            
            if (checkPasswordStrength() < 75) {
                e.preventDefault();
                alert('La contraseña no cumple con los requisitos mínimos');
                return;
            }
            
            // Mostrar estado de carga
            button.textContent = 'Actualizando...';
            button.disabled = true;
            this.classList.add('loading');
        });
        
        // Manejar envío de nueva solicitud
        document.getElementById('newRequestForm')?.addEventListener('submit', function(e) {
            const button = document.getElementById('newRequestButton');
            
            button.textContent = 'Enviando...';
            button.disabled = true;
            this.classList.add('loading');
        });
        
        // Auto-focus en primer campo
        window.addEventListener('load', function() {
            const firstInput = document.querySelector('.form-input');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // Logging para debugging (solo en modo debug)
        <?php if (config('debug')): ?>
        console.log('Reset password page loaded', {
            hasToken: <?php echo json_encode(!empty($token)); ?>,
            showForm: <?php echo json_encode($showForm); ?>,
            success: <?php echo json_encode($success); ?>
        });
        <?php endif; ?>
    </script>
</body>
</html>