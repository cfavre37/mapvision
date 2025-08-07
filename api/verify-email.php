<?php
/**
 * MapVision Analytics - Verificación de Email
 * Archivo: api/verify-email.php
 * Descripción: Página para verificar email de usuarios registrados
 */

// Cargar dependencias
require_once __DIR__ . '/../classes/Auth.php';

// Variables para la página
$token = $_GET['token'] ?? '';
$message = '';
$success = false;
$pageTitle = 'Verificación de Email';
$redirectUrl = '';
$redirectDelay = 0;

// Procesar verificación si hay token
if (!empty($token)) {
    try {
        app_log("Intento de verificación de email con token: " . substr($token, 0, 10) . "...", 'INFO');
        
        $auth = new Auth();
        $result = $auth->verifyEmail($token);
        
        if ($result['success']) {
            $success = true;
            $message = $result['message'];
            $redirectUrl = '../index.html#login';
            $redirectDelay = 3000; // 3 segundos
            app_log("Email verificado exitosamente con token: " . substr($token, 0, 10) . "...", 'INFO');
        } else {
            $success = false;
            $message = $result['message'];
            app_log("Verificación de email fallida: " . $result['message'], 'WARNING');
        }
        
    } catch (Exception $e) {
        $success = false;
        $message = 'Error verificando el email. Por favor intenta nuevamente.';
        app_log("Error en verificación de email: " . $e->getMessage(), 'ERROR');
    }
} else {
    $success = false;
    $message = 'Token de verificación no proporcionado o inválido.';
    app_log("Intento de verificación sin token", 'WARNING');
}

// Función para generar nuevo token de verificación
function handleResendVerification() {
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
            
            // Verificar que el usuario existe y no está verificado
            $stmt = DatabaseConfig::getInstance()->getConnection()->prepare("
                SELECT email, email_verificado, nombre 
                FROM mv_usuarios 
                WHERE email = :email
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }
            
            if ($user['EMAIL_VERIFICADO']) {
                return [
                    'success' => false,
                    'message' => 'Este email ya está verificado'
                ];
            }
            
            // Rate limiting para reenvío
            $rateLimitKey = 'resend_verification_' . $email;
            $rateLimitFile = sys_get_temp_dir() . "/{$rateLimitKey}.txt";
            
            if (file_exists($rateLimitFile)) {
                $lastSent = (int)file_get_contents($rateLimitFile);
                if (time() - $lastSent < 300) { // 5 minutos
                    $remaining = 300 - (time() - $lastSent);
                    return [
                        'success' => false,
                        'message' => "Debes esperar {$remaining} segundos antes de solicitar otro email"
                    ];
                }
            }
            
            // Generar nuevo token
            $result = $auth->generateEmailVerificationToken($email);
            
            if ($result['success']) {
                // Registrar tiempo de envío
                file_put_contents($rateLimitFile, time(), LOCK_EX);
                
                return [
                    'success' => true,
                    'message' => 'Email de verificación reenviado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error reenviando el email'
                ];
            }
            
        } catch (Exception $e) {
            app_log("Error reenviando verificación: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Error interno del servidor'
            ];
        }
    }
    
    return null;
}

// Procesar reenvío si es POST
$resendResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resendResult = handleResendVerification();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - MapVision Analytics</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    
    <!-- Meta tags para SEO y redes sociales -->
    <meta name="description" content="Verificación de email para MapVision Analytics - Plataforma de análisis de mapas inteligentes con IA">
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
        
        .verification-container {
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
        
        .verification-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }
        
        .verification-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .verification-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .verification-content {
            padding: 40px 30px;
            text-align: center;
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
        
        .status-message {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 20px;
            line-height: 1.5;
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
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .btn {
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .resend-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
        }
        
        .resend-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-group {
            text-align: left;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #495057;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
        }
        
        .footer-text a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .verification-container {
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }
            
            .verification-header,
            .verification-content {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <!-- Header -->
        <div class="verification-header">
            <h1>📊 MapVision Analytics</h1>
            <p>Verificación de Email</p>
        </div>
        
        <!-- Content -->
        <div class="verification-content">
            <?php if (!empty($token)): ?>
                <!-- Resultado de verificación -->
                <div class="status-icon <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo $success ? '✓' : '✗'; ?>
                </div>
                
                <div class="status-message <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                
                <?php if ($success): ?>
                    <div class="status-description">
                        Tu email ha sido verificado correctamente. Ya puedes iniciar sesión y acceder a todas las funcionalidades de MapVision Analytics.
                    </div>
                    
                    <div class="action-buttons">
                        <a href="../index.html#login" class="btn btn-primary">
                            Iniciar Sesión
                        </a>
                        <a href="../index.html" class="btn btn-secondary">
                            Volver al Inicio
                        </a>
                    </div>
                    
                    <!-- Auto-redirect -->
                    <?php if ($redirectUrl && $redirectDelay > 0): ?>
                        <script>
                            setTimeout(function() {
                                window.location.href = '<?php echo $redirectUrl; ?>';
                            }, <?php echo $redirectDelay; ?>);
                        </script>
                        
                        <div class="footer-text">
                            Serás redirigido automáticamente en <span id="countdown">3</span> segundos...
                        </div>
                        
                        <script>
                            let countdown = 3;
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
                    <div class="status-description">
                        El enlace de verificación puede haber expirado o ya fue utilizado. Si necesitas un nuevo enlace, puedes solicitarlo a continuación.
                    </div>
                    
                    <div class="action-buttons">
                        <a href="#resend" class="btn btn-primary" onclick="showResendForm()">
                            Solicitar Nuevo Enlace
                        </a>
                        <a href="../index.html" class="btn btn-secondary">
                            Volver al Inicio
                        </a>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Sin token - mostrar formulario de reenvío -->
                <div class="status-icon error">
                    ⚠
                </div>
                
                <div class="status-message error">
                    Enlace de Verificación Inválido
                </div>
                
                <div class="status-description">
                    No se proporcionó un token de verificación válido. Si necesitas verificar tu email, puedes solicitar un nuevo enlace de verificación.
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="showResendForm()">
                        Solicitar Enlace de Verificación
                    </button>
                    <a href="../index.html" class="btn btn-secondary">
                        Volver al Inicio
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Sección de reenvío (inicialmente oculta) -->
            <div id="resendSection" class="resend-section" style="display: none;">
                <h3 style="margin-bottom: 20px; color: #495057;">Solicitar Nuevo Enlace</h3>
                
                <?php if ($resendResult): ?>
                    <div class="alert <?php echo $resendResult['success'] ? 'alert-success' : 'alert-error'; ?>">
                        <?php echo htmlspecialchars($resendResult['message']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="resend-form" id="resendForm">
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
                    
                    <button type="submit" class="btn btn-primary" id="resendButton">
                        Enviar Enlace de Verificación
                    </button>
                </form>
            </div>
            
            <!-- Footer -->
            <div class="footer-text">
                ¿Necesitas ayuda? Contacta a nuestro 
                <a href="mailto:support@mapvision.com">equipo de soporte</a>
                <br>
                <a href="../index.html">← Volver a MapVision Analytics</a>
            </div>
        </div>
    </div>
    
    <script>
        // Mostrar formulario de reenvío
        function showResendForm() {
            const resendSection = document.getElementById('resendSection');
            if (resendSection) {
                resendSection.style.display = 'block';
                resendSection.scrollIntoView({ behavior: 'smooth' });
                
                // Enfocar el campo de email
                setTimeout(() => {
                    const emailInput = document.getElementById('email');
                    if (emailInput) {
                        emailInput.focus();
                    }
                }, 300);
            }
        }
        
        // Manejar envío del formulario de reenvío
        document.getElementById('resendForm')?.addEventListener('submit', function(e) {
            const button = document.getElementById('resendButton');
            const form = this;
            
            // Mostrar estado de carga
            button.textContent = 'Enviando...';
            button.disabled = true;
            form.classList.add('loading');
            
            // El formulario se enviará normalmente (POST)
            // El estado de carga se resetea cuando la página se recarga
        });
        
        // Auto-focus en campo de email si no hay token
        <?php if (empty($token)): ?>
        window.addEventListener('load', function() {
            showResendForm();
        });
        <?php endif; ?>
        
        // Logging para debugging (solo en modo debug)
        <?php if (config('debug')): ?>
        console.log('Verification page loaded', {
            hasToken: <?php echo json_encode(!empty($token)); ?>,
            success: <?php echo json_encode($success); ?>,
            message: <?php echo json_encode($message); ?>
        });
        <?php endif; ?>
    </script>
</body>
</html>