<?php
/**
 * MapVision Analytics - Configuración de Email
 * Archivo: config/email.php
 * Descripción: Configuración de SMTP y templates de email
 */

require_once __DIR__ . '/app.php';

class EmailConfig {
    // 🔧 CONFIGURACIÓN SMTP - EDITAR ESTAS LÍNEAS
    private static $config = [
        // Configuración del servidor SMTP
        'smtp_host' => 'smtp.gmail.com',           // 🔧 CAMBIAR: Tu servidor SMTP
        'smtp_port' => 587,                        // Puerto SMTP (587 para TLS, 465 para SSL)
        'smtp_encryption' => 'tls',                // 'tls', 'ssl' o '' para sin encriptación
        'smtp_username' => 'tu_email@gmail.com',   // 🔧 CAMBIAR: Tu email SMTP
        'smtp_password' => 'tu_app_password',      // 🔧 CAMBIAR: Tu contraseña de aplicación
        'smtp_timeout' => 30,                      // Timeout en segundos
        
        // Configuración de emails
        'from_email' => 'noreply@mapvision.com',   // 🔧 CAMBIAR: Email remitente
        'from_name' => 'MapVision Analytics',      // Nombre del remitente
        'reply_to' => 'support@mapvision.com',     // 🔧 CAMBIAR: Email de respuesta
        'bounce_email' => 'bounce@mapvision.com',  // Email para rebotes
        
        // Configuración de plantillas
        'template_dir' => __DIR__ . '/../templates/email/',
        'default_language' => 'es',
        'charset' => 'UTF-8',
        
        // Configuración de límites
        'max_recipients' => 50,                    // Máximo destinatarios por email
        'rate_limit_per_hour' => 100,            // Máximo emails por hora
        'retry_attempts' => 3,                    // Intentos de reenvío
        'retry_delay' => 300,                     // Delay entre intentos (segundos)
        
        // URLs para templates
        'logo_url' => 'https://tudominio.com/img/logo.png',  // 🔧 CAMBIAR: URL del logo
        'website_url' => 'https://tudominio.com',            // 🔧 CAMBIAR: URL del sitio
        'support_url' => 'mailto:support@mapvision.com',     // URL de soporte
        'unsubscribe_url' => 'https://tudominio.com/unsubscribe', // URL para darse de baja
        
        // Configuración adicional
        'enable_tracking' => false,               // Habilitar tracking de emails
        'enable_dkim' => false,                   // Habilitar firma DKIM
        'debug_mode' => false                     // Modo debug (logs detallados)
    ];
    
    /**
     * Obtener configuración
     */
    public static function get($key = null) {
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? null;
    }
    
    /**
     * Establecer configuración
     */
    public static function set($key, $value) {
        self::$config[$key] = $value;
    }
    
    /**
     * Verificar configuración SMTP
     */
    public static function validateSMTPConfig() {
        $required = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'from_email'];
        $missing = [];
        
        foreach ($required as $field) {
            if (empty(self::get($field))) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("Configuración SMTP incompleta. Faltan: " . implode(', ', $missing));
        }
        
        // Validar email addresses
        if (!filter_var(self::get('from_email'), FILTER_VALIDATE_EMAIL)) {
            throw new Exception("from_email no es una dirección válida: " . self::get('from_email'));
        }
        
        if (!filter_var(self::get('reply_to'), FILTER_VALIDATE_EMAIL)) {
            throw new Exception("reply_to no es una dirección válida: " . self::get('reply_to'));
        }
        
        return true;
    }
    
    /**
     * Probar conexión SMTP
     */
    public static function testSMTPConnection() {
        try {
            self::validateSMTPConfig();
            
            $host = self::get('smtp_host');
            $port = self::get('smtp_port');
            $timeout = self::get('smtp_timeout');
            
            app_log("Probando conexión SMTP a {$host}:{$port}", 'INFO');
            
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            
            if (!$socket) {
                throw new Exception("No se puede conectar a {$host}:{$port} - {$errstr} ({$errno})");
            }
            
            fclose($socket);
            app_log("Conexión SMTP exitosa", 'INFO');
            return true;
            
        } catch (Exception $e) {
            app_log("Error en conexión SMTP: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Obtener headers estándar para emails
     */
    public static function getDefaultHeaders($additionalHeaders = []) {
        $headers = [
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=' . self::get('charset'),
            'From' => self::get('from_name') . ' <' . self::get('from_email') . '>',
            'Reply-To' => self::get('reply_to'),
            'Return-Path' => self::get('bounce_email') ?: self::get('from_email'),
            'X-Mailer' => 'MapVision Analytics Mailer v' . config('app_version'),
            'X-Priority' => '3',
            'Date' => date('r')
        ];
        
        // Agregar headers adicionales
        if (!empty($additionalHeaders)) {
            $headers = array_merge($headers, $additionalHeaders);
        }
        
        // Convertir a string para mail()
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }
        
        return rtrim($headerString, "\r\n");
    }
    
    /**
     * Validar dirección de email
     */
    public static function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Verificaciones adicionales
        $domain = substr(strrchr($email, "@"), 1);
        
        // Verificar que el dominio tenga al menos un punto
        if (strpos($domain, '.') === false) {
            return false;
        }
        
        // Verificar longitud
        if (strlen($email) > 254) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener plantilla base para emails
     */
    public static function getBaseTemplate() {
        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="' . self::get('charset') . '">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{subject}}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .email-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-content {
            padding: 30px;
        }
        .email-content h2 {
            color: #667eea;
            margin-top: 0;
        }
        .button {
            display: inline-block;
            padding: 15px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: #5a6fd8;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .divider {
            height: 1px;
            background: #e9ecef;
            margin: 30px 0;
        }
        @media (max-width: 600px) {
            body { padding: 10px; }
            .email-content { padding: 20px; }
            .email-header { padding: 20px 15px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>' . self::get('from_name') . '</h1>
        </div>
        <div class="email-content">
            {{content}}
        </div>
        <div class="footer">
            <p>Este email fue enviado por <a href="' . self::get('website_url') . '">' . self::get('from_name') . '</a></p>
            <p>
                <a href="' . self::get('website_url') . '">Sitio Web</a> |
                <a href="' . self::get('support_url') . '">Soporte</a> |
                <a href="' . self::get('unsubscribe_url') . '">Darse de Baja</a>
            </p>
            <p>&copy; ' . date('Y') . ' ' . self::get('from_name') . '. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Renderizar plantilla con variables
     */
    public static function renderTemplate($template, $variables = []) {
        // Variables por defecto
        $defaultVars = [
            'app_name' => config('app_name'),
            'app_url' => config('app_url'),
            'logo_url' => self::get('logo_url'),
            'website_url' => self::get('website_url'),
            'support_url' => self::get('support_url'),
            'year' => date('Y')
        ];
        
        $variables = array_merge($defaultVars, $variables);
        
        // Reemplazar variables
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Obtener plantilla de verificación de email
     */
    public static function getVerificationTemplate($verifyUrl, $userName = '') {
        $content = '
            <h2>¡Bienvenido' . ($userName ? ', ' . $userName : '') . '!</h2>
            <p>Gracias por registrarte en MapVision Analytics. Para activar tu cuenta y comenzar a crear mapas inteligentes, necesitas verificar tu dirección de email.</p>
            
            <div style="text-align: center;">
                <a href="' . $verifyUrl . '" class="button">Verificar Email</a>
            </div>
            
            <p>Una vez verificado tu email, podrás acceder a todas las funcionalidades de tu cuenta.</p>
            
            <div class="divider"></div>
            
            <p style="font-size: 12px; color: #6c757d;">
                Si tienes problemas haciendo clic en el botón, copia y pega este enlace en tu navegador:<br>
                <a href="' . $verifyUrl . '">' . $verifyUrl . '</a>
            </p>
        ';
        
        return self::renderTemplate(self::getBaseTemplate(), [
            'subject' => 'Verifica tu cuenta - ' . config('app_name'),
            'content' => $content
        ]);
    }
    
    /**
     * Obtener plantilla de recuperación de contraseña
     */
    public static function getPasswordResetTemplate($resetUrl, $userName = '') {
        $content = '
            <h2>Recuperación de contraseña</h2>
            <p>Hola' . ($userName ? ' ' . $userName : '') . ',</p>
            <p>Has solicitado restablecer tu contraseña en MapVision Analytics.</p>
            
            <div style="text-align: center;">
                <a href="' . resetUrl . '" class="button">Restablecer Contraseña</a>
            </div>
            
            <p><strong>Este enlace expira en 1 hora.</strong></p>
            <p>Si no solicitaste este cambio, ignora este email y tu contraseña permanecerá sin cambios.</p>
            
            <div class="divider"></div>
            
            <p style="font-size: 12px; color: #6c757d;">
                Si tienes problemas haciendo clic en el botón, copia y pega este enlace en tu navegador:<br>
                <a href="' . $resetUrl . '">' . $resetUrl . '</a>
            </p>
        ';
        
        return self::renderTemplate(self::getBaseTemplate(), [
            'subject' => 'Recuperación de contraseña - ' . config('app_name'),
            'content' => $content
        ]);
    }
    
    /**
     * Obtener plantilla de bienvenida
     */
    public static function getWelcomeTemplate($userName, $dashboardUrl) {
        $content = '
            <h2>¡Bienvenido a MapVision Analytics, ' . $userName . '!</h2>
            <p>Tu cuenta ha sido verificada exitosamente. Ya puedes comenzar a crear mapas inteligentes con IA.</p>
            
            <div style="text-align: center;">
                <a href="' . $dashboardUrl . '" class="button">Ir al Dashboard</a>
            </div>
            
            <h3>¿Qué puedes hacer ahora?</h3>
            <ul>
                <li>✅ Crear mapas de desviaciones bidimensionales</li>
                <li>🤖 Usar análisis automático con IA</li>
                <li>📊 Visualizar datos con codificación de colores</li>
                <li>🔍 Realizar drilldown inteligente</li>
            </ul>
            
            <p>Si tienes alguna pregunta, no dudes en contactar a nuestro equipo de soporte.</p>
        ';
        
        return self::renderTemplate(self::getBaseTemplate(), [
            'subject' => '¡Bienvenido a ' . config('app_name') . '!',
            'content' => $content
        ]);
    }
    
    /**
     * Log de configuración
     */
    public static function logConfiguration() {
        $safeConfig = self::$config;
        // Ocultar información sensible en logs
        $safeConfig['smtp_password'] = '***';
        
        app_log("Configuración de email cargada: " . json_encode($safeConfig, JSON_PRETTY_PRINT), 'DEBUG');
    }
}

// Función helper para obtener configuración de email
if (!function_exists('email_config')) {
    function email_config($key = null) {
        return EmailConfig::get($key);
    }
}

// Log de inicialización
app_log("Módulo de email cargado", 'INFO');

// Log de configuración en modo debug
if (config('debug')) {
    EmailConfig::logConfiguration();
}
?>