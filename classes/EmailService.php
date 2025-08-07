<?php
/**
 * MapVision Analytics - Servicio de Email
 * Archivo: classes/EmailService.php
 * Descripción: Envío de emails con templates y configuración SMTP
 */

require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/app.php';

class EmailService {
    private $config;
    private $rateLimitFile;
    private $emailQueue = [];
    
    public function __construct() {
        $this->config = EmailConfig::get();
        $this->rateLimitFile = sys_get_temp_dir() . '/mv_email_rate_limit.json';
        
        // Validar configuración al inicializar
        try {
            EmailConfig::validateSMTPConfig();
            app_log("EmailService inicializado correctamente", 'INFO');
        } catch (Exception $e) {
            app_log("Error en configuración SMTP: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Enviar email de verificación de cuenta
     */
    public function sendVerificationEmail($email, $token, $userName = '') {
        try {
            $verifyUrl = config('app_url') . "/api/verify-email.php?token=" . $token;
            
            $subject = "Verifica tu cuenta - " . config('app_name');
            $htmlContent = EmailConfig::getVerificationTemplate($verifyUrl, $userName);
            $textContent = $this->htmlToText($htmlContent);
            
            $result = $this->sendEmail($email, $subject, $htmlContent, $textContent);
            
            if ($result['success']) {
                app_log("Email de verificación enviado a: {$email}", 'INFO');
            } else {
                app_log("Error enviando email de verificación a {$email}: {$result['message']}", 'ERROR');
            }
            
            return $result;
            
        } catch (Exception $e) {
            app_log("Excepción enviando email de verificación: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error enviando email de verificación'];
        }
    }
    
    /**
     * Enviar email de recuperación de contraseña
     */
    public function sendPasswordResetEmail($email, $token, $userName = '') {
        try {
            $resetUrl = config('app_url') . "/reset-password.php?token=" . $token;
            
            $subject = "Recuperación de contraseña - " . config('app_name');
            $htmlContent = EmailConfig::getPasswordResetTemplate($resetUrl, $userName);
            $textContent = $this->htmlToText($htmlContent);
            
            $result = $this->sendEmail($email, $subject, $htmlContent, $textContent);
            
            if ($result['success']) {
                app_log("Email de recuperación enviado a: {$email}", 'INFO');
            } else {
                app_log("Error enviando email de recuperación a {$email}: {$result['message']}", 'ERROR');
            }
            
            return $result;
            
        } catch (Exception $e) {
            app_log("Excepción enviando email de recuperación: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error enviando email de recuperación'];
        }
    }
    
    /**
     * Enviar email de bienvenida
     */
    public function sendWelcomeEmail($email, $userName) {
        try {
            $dashboardUrl = config('app_url') . "/dashboard.html";
            
            $subject = "¡Bienvenido a " . config('app_name') . "!";
            $htmlContent = EmailConfig::getWelcomeTemplate($userName, $dashboardUrl);
            $textContent = $this->htmlToText($htmlContent);
            
            $result = $this->sendEmail($email, $subject, $htmlContent, $textContent);
            
            if ($result['success']) {
                app_log("Email de bienvenida enviado a: {$email}", 'INFO');
            } else {
                app_log("Error enviando email de bienvenida a {$email}: {$result['message']}", 'ERROR');
            }
            
            return $result;
            
        } catch (Exception $e) {
            app_log("Excepción enviando email de bienvenida: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error enviando email de bienvenida'];
        }
    }
    
    /**
     * Enviar email personalizado
     */
    public function sendCustomEmail($email, $subject, $content, $variables = []) {
        try {
            // Renderizar contenido con variables
            $htmlContent = EmailConfig::renderTemplate(
                EmailConfig::getBaseTemplate(),
                array_merge($variables, [
                    'subject' => $subject,
                    'content' => $content
                ])
            );
            
            $textContent = $this->htmlToText($htmlContent);
            
            return $this->sendEmail($email, $subject, $htmlContent, $textContent);
            
        } catch (Exception $e) {
            app_log("Error enviando email personalizado: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error enviando email'];
        }
    }
    
    /**
     * Método principal para enviar emails
     */
    private function sendEmail($to, $subject, $htmlContent, $textContent = '') {
        try {
            // Verificar rate limiting
            if (!$this->checkRateLimit()) {
                return [
                    'success' => false, 
                    'message' => 'Límite de emails por hora alcanzado'
                ];
            }
            
            // Validar dirección de destino
            if (!EmailConfig::validateEmail($to)) {
                return ['success' => false, 'message' => 'Dirección de email inválida'];
            }
            
            // Preparar headers
            $headers = $this->prepareHeaders($htmlContent, $textContent);
            
            // Preparar contenido final
            $finalContent = $this->prepareContent($htmlContent, $textContent);
            
            // Intentar envío con reintentos
            $result = $this->attemptSend($to, $subject, $finalContent, $headers);
            
            // Registrar el envío en rate limiting
            $this->recordEmailSent();
            
            return $result;
            
        } catch (Exception $e) {
            app_log("Error en sendEmail: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => 'Error interno enviando email'];
        }
    }
    
    /**
     * Preparar headers del email
     */
    private function prepareHeaders($htmlContent, $textContent) {
        $boundary = md5(uniqid(time()));
        
        $headers = [
            'MIME-Version' => '1.0',
            'From' => EmailConfig::get('from_name') . ' <' . EmailConfig::get('from_email') . '>',
            'Reply-To' => EmailConfig::get('reply_to'),
            'Return-Path' => EmailConfig::get('bounce_email') ?: EmailConfig::get('from_email'),
            'X-Mailer' => 'MapVision Analytics Mailer v' . config('app_version'),
            'X-Priority' => '3',
            'Date' => date('r'),
            'Message-ID' => '<' . md5(uniqid()) . '@' . parse_url(config('app_url'), PHP_URL_HOST) . '>'
        ];
        
        // Configurar Content-Type según el contenido
        if (!empty($textContent) && !empty($htmlContent)) {
            // Multipart/alternative
            $headers['Content-Type'] = "multipart/alternative; boundary=\"{$boundary}\"";
        } elseif (!empty($htmlContent)) {
            // Solo HTML
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        } else {
            // Solo texto
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        }
        
        // Convertir a string
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }
        
        return [
            'string' => rtrim($headerString, "\r\n"),
            'boundary' => $boundary,
            'array' => $headers
        ];
    }
    
    /**
     * Preparar contenido del email
     */
    private function prepareContent($htmlContent, $textContent) {
        // Si solo hay contenido HTML
        if (!empty($htmlContent) && empty($textContent)) {
            return $htmlContent;
        }
        
        // Si solo hay contenido de texto
        if (empty($htmlContent) && !empty($textContent)) {
            return $textContent;
        }
        
        // Si hay ambos, crear multipart
        if (!empty($htmlContent) && !empty($textContent)) {
            $boundary = md5(uniqid(time()));
            
            $content = "--{$boundary}\r\n";
            $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $content .= $textContent . "\r\n\r\n";
            
            $content .= "--{$boundary}\r\n";
            $content .= "Content-Type: text/html; charset=UTF-8\r\n";
            $content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $content .= $htmlContent . "\r\n\r\n";
            
            $content .= "--{$boundary}--";
            
            return $content;
        }
        
        return $htmlContent ?: $textContent;
    }
    
    /**
     * Intentar envío con reintentos
     */
    private function attemptSend($to, $subject, $content, $headers) {
        $maxAttempts = EmailConfig::get('retry_attempts');
        $retryDelay = EmailConfig::get('retry_delay');
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                app_log("Enviando email a {$to} (intento {$attempt}/{$maxAttempts})", 'DEBUG');
                
                // Usar función mail() de PHP (en producción podrías usar PHPMailer/SwiftMailer)
                $success = mail($to, $subject, $content, $headers['string']);
                
                if ($success) {
                    app_log("Email enviado exitosamente a: {$to}", 'INFO');
                    return ['success' => true, 'message' => 'Email enviado correctamente'];
                } else {
                    throw new Exception("La función mail() retornó false");
                }
                
            } catch (Exception $e) {
                app_log("Intento {$attempt} falló para {$to}: " . $e->getMessage(), 'WARNING');
                
                if ($attempt < $maxAttempts) {
                    app_log("Esperando {$retryDelay} segundos antes del siguiente intento", 'DEBUG');
                    sleep($retryDelay);
                } else {
                    app_log("Todos los intentos fallaron para {$to}", 'ERROR');
                    return [
                        'success' => false, 
                        'message' => 'Error enviando email después de ' . $maxAttempts . ' intentos'
                    ];
                }
            }
        }
        
        return ['success' => false, 'message' => 'Error desconocido enviando email'];
    }
    
    /**
     * Verificar rate limiting
     */
    private function checkRateLimit() {
        $limit = EmailConfig::get('rate_limit_per_hour');
        
        if ($limit <= 0) {
            return true; // Sin límite
        }
        
        $data = $this->loadRateLimitData();
        $currentHour = date('Y-m-d H');
        
        // Limpiar datos antiguos
        foreach ($data as $hour => $count) {
            if ($hour < date('Y-m-d H', time() - 3600)) {
                unset($data[$hour]);
            }
        }
        
        $currentCount = $data[$currentHour] ?? 0;
        
        if ($currentCount >= $limit) {
            app_log("Rate limit alcanzado: {$currentCount}/{$limit} emails en la hora actual", 'WARNING');
            return false;
        }
        
        return true;
    }
    
    /**
     * Registrar email enviado para rate limiting
     */
    private function recordEmailSent() {
        $data = $this->loadRateLimitData();
        $currentHour = date('Y-m-d H');
        
        $data[$currentHour] = ($data[$currentHour] ?? 0) + 1;
        
        $this->saveRateLimitData($data);
    }
    
    /**
     * Cargar datos de rate limiting
     */
    private function loadRateLimitData() {
        if (file_exists($this->rateLimitFile)) {
            $content = file_get_contents($this->rateLimitFile);
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }
        
        return [];
    }
    
    /**
     * Guardar datos de rate limiting
     */
    private function saveRateLimitData($data) {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->rateLimitFile, $json, LOCK_EX);
    }
    
    /**
     * Convertir HTML a texto plano
     */
    private function htmlToText($html) {
        // Eliminar scripts y styles
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Convertir algunos elementos HTML a texto
        $html = str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html);
        $html = str_replace(['</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'], "\n\n", $html);
        
        // Eliminar todas las etiquetas HTML
        $text = strip_tags($html);
        
        // Limpiar espacios extra
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Probar configuración SMTP
     */
    public function testConnection() {
        try {
            return EmailConfig::testSMTPConnection();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtener estadísticas de emails
     */
    public function getEmailStats() {
        $data = $this->loadRateLimitData();
        $limit = EmailConfig::get('rate_limit_per_hour');
        $currentHour = date('Y-m-d H');
        $currentCount = $data[$currentHour] ?? 0;
        
        return [
            'current_hour_count' => $currentCount,
            'hourly_limit' => $limit,
            'remaining' => max(0, $limit - $currentCount),
            'percentage_used' => $limit > 0 ? round(($currentCount / $limit) * 100, 2) : 0,
            'last_24_hours' => $this->getLast24HoursStats($data)
        ];
    }
    
    /**
     * Obtener estadísticas de las últimas 24 horas
     */
    private function getLast24HoursStats($data) {
        $stats = [];
        $total = 0;
        
        for ($i = 23; $i >= 0; $i--) {
            $hour = date('Y-m-d H', time() - ($i * 3600));
            $count = $data[$hour] ?? 0;
            $stats[] = [
                'hour' => $hour,
                'count' => $count
            ];
            $total += $count;
        }
        
        return [
            'total' => $total,
            'hourly_breakdown' => $stats
        ];
    }
    
    /**
     * Enviar email de prueba
     */
    public function sendTestEmail($to, $testMessage = '') {
        $subject = "Email de prueba - " . config('app_name');
        $content = '
            <h2>🧪 Email de Prueba</h2>
            <p>Este es un email de prueba para verificar la configuración SMTP.</p>
            <p><strong>Enviado:</strong> ' . date('Y-m-d H:i:s') . '</p>
            ' . ($testMessage ? '<p><strong>Mensaje:</strong> ' . htmlspecialchars($testMessage) . '</p>' : '') . '
            <p>Si recibes este email, la configuración está funcionando correctamente.</p>
        ';
        
        return $this->sendCustomEmail($to, $subject, $content);
    }
    
    /**
     * Limpiar archivos temporales antiguos
     */
    public function cleanup() {
        try {
            // Limpiar datos de rate limiting antiguos
            $data = $this->loadRateLimitData();
            $cutoff = date('Y-m-d H', time() - (24 * 3600)); // 24 horas atrás
            
            $cleaned = 0;
            foreach ($data as $hour => $count) {
                if ($hour < $cutoff) {
                    unset($data[$hour]);
                    $cleaned++;
                }
            }
            
            if ($cleaned > 0) {
                $this->saveRateLimitData($data);
                app_log("Limpiados {$cleaned} registros antiguos de rate limiting", 'INFO');
            }
            
        } catch (Exception $e) {
            app_log("Error en cleanup de EmailService: " . $e->getMessage(), 'WARNING');
        }
    }
}

// Función helper para enviar emails
if (!function_exists('send_email')) {
    function send_email($to, $subject, $content, $variables = []) {
        $emailService = new EmailService();
        return $emailService->sendCustomEmail($to, $subject, $content, $variables);
    }
}

// Log de inicialización
app_log("Módulo EmailService cargado", 'INFO');
?>