<?php
/**
 * MapVision Analytics - Validador de Usuarios
 * Archivo: classes/UserValidator.php
 * Descripción: Validaciones y sanitización de datos de usuario
 */

require_once __DIR__ . '/../config/app.php';

class UserValidator {
    
    /**
     * Reglas de validación por defecto
     */
    private static $defaultRules = [
        'password_min_length' => 8,
        'password_max_length' => 128,
        'name_min_length' => 2,
        'name_max_length' => 50,
        'email_max_length' => 255,
        'empresa_max_length' => 255,
        'telefono_max_length' => 20
    ];
    
    /**
     * Patrones de validación
     */
    private static $patterns = [
        'name' => '/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\-\'\.]+$/u',
        'telefono' => '/^[\+]?[0-9\s\-\(\)\.]{7,20}$/',
        'password_strength' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        'empresa' => '/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-\&\.\,\(\)]+$/u'
    ];
    
    /**
     * Dominios de email bloqueados (temporales, sospechosos)
     */
    private static $blockedEmailDomains = [
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'yopmail.com',
        'tempmail.org',
        'throwaway.email'
    ];
    
    /**
     * Contraseñas comunes bloqueadas
     */
    private static $commonPasswords = [
        'password', '123456', '123456789', 'qwerty', 'abc123',
        'password123', 'admin', 'letmein', 'welcome', 'monkey',
        '1234567890', 'password1', 'admin123'
    ];
    
    /**
     * Validar datos de registro completo
     */
    public function validateRegistration($data) {
        $errors = [];
        
        // Validar campos requeridos
        $required = ['email', 'password', 'nombre', 'apellido'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "El campo '{$field}' es requerido";
            }
        }
        
        if (!empty($errors)) {
            return [
                'valid' => false, 
                'message' => implode(', ', $errors),
                'errors' => $errors
            ];
        }
        
        // Validar email
        $emailValidation = $this->validateEmail($data['email']);
        if (!$emailValidation['valid']) {
            return $emailValidation;
        }
        
        // Validar contraseña
        $passwordValidation = $this->validatePassword($data['password']);
        if (!$passwordValidation['valid']) {
            return $passwordValidation;
        }
        
        // Validar nombre
        $nameValidation = $this->validateName($data['nombre'], 'nombre');
        if (!$nameValidation['valid']) {
            return $nameValidation;
        }
        
        // Validar apellido
        $lastNameValidation = $this->validateName($data['apellido'], 'apellido');
        if (!$lastNameValidation['valid']) {
            return $lastNameValidation;
        }
        
        // Validar campos opcionales
        if (!empty($data['empresa'])) {
            $empresaValidation = $this->validateEmpresa($data['empresa']);
            if (!$empresaValidation['valid']) {
                return $empresaValidation;
            }
        }
        
        if (!empty($data['telefono'])) {
            $telefonoValidation = $this->validateTelefono($data['telefono']);
            if (!$telefonoValidation['valid']) {
                return $telefonoValidation;
            }
        }
        
        // Validar rol si se proporciona
        if (!empty($data['rol'])) {
            $rolValidation = $this->validateRol($data['rol']);
            if (!$rolValidation['valid']) {
                return $rolValidation;
            }
        }
        
        return [
            'valid' => true, 
            'message' => 'Validación exitosa',
            'sanitized_data' => $this->sanitizeRegistrationData($data)
        ];
    }
    
    /**
     * Validar email con verificaciones avanzadas
     */
    public function validateEmail($email) {
        $email = trim($email);
        
        // Verificar longitud
        if (strlen($email) > self::$defaultRules['email_max_length']) {
            return [
                'valid' => false,
                'message' => 'Email demasiado largo (máximo ' . self::$defaultRules['email_max_length'] . ' caracteres)'
            ];
        }
        
        // Validación básica de formato
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Formato de email inválido'];
        }
        
        // Obtener dominio
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        
        // Verificar dominio bloqueado
        if (in_array($domain, self::$blockedEmailDomains)) {
            return [
                'valid' => false,
                'message' => 'No se permiten emails temporales o desechables'
            ];
        }
        
        // Verificar que el dominio tenga al menos un punto
        if (strpos($domain, '.') === false) {
            return ['valid' => false, 'message' => 'Dominio de email inválido'];
        }
        
        // Verificar MX record si está disponible
        if (function_exists('checkdnsrr')) {
            if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                app_log("Dominio sin registros MX/A: {$domain}", 'WARNING');
                return [
                    'valid' => false,
                    'message' => 'El dominio del email no parece válido'
                ];
            }
        }
        
        return ['valid' => true, 'message' => 'Email válido'];
    }
    
    /**
     * Validar contraseña con verificaciones de seguridad
     */
    public function validatePassword($password) {
        $minLength = config('password_min_length', self::$defaultRules['password_min_length']);
        $maxLength = self::$defaultRules['password_max_length'];
        
        // Verificar longitud mínima
        if (strlen($password) < $minLength) {
            return [
                'valid' => false,
                'message' => "La contraseña debe tener al menos {$minLength} caracteres"
            ];
        }
        
        // Verificar longitud máxima
        if (strlen($password) > $maxLength) {
            return [
                'valid' => false,
                'message' => "La contraseña no puede tener más de {$maxLength} caracteres"
            ];
        }
        
        // Verificar contraseñas comunes
        $lowerPassword = strtolower($password);
        foreach (self::$commonPasswords as $common) {
            if ($lowerPassword === $common || strpos($lowerPassword, $common) !== false) {
                return [
                    'valid' => false,
                    'message' => 'La contraseña es demasiado común. Elige una más segura'
                ];
            }
        }
        
        // Verificar fortaleza (opcional, configurable)
        $requireStrong = config('require_strong_passwords', false);
        if ($requireStrong && !preg_match(self::$patterns['password_strength'], $password)) {
            return [
                'valid' => false,
                'message' => 'La contraseña debe contener al menos una mayúscula, una minúscula y un número'
            ];
        }
        
        // Verificar que no sea solo números o letras
        if (ctype_alpha($password) || ctype_digit($password)) {
            return [
                'valid' => false,
                'message' => 'La contraseña debe contener una combinación de letras y números'
            ];
        }
        
        return ['valid' => true, 'message' => 'Contraseña válida'];
    }
    
    /**
     * Validar nombre o apellido
     */
    public function validateName($name, $field = 'nombre') {
        $name = trim($name);
        $minLength = self::$defaultRules['name_min_length'];
        $maxLength = self::$defaultRules['name_max_length'];
        
        // Verificar longitud
        if (strlen($name) < $minLength) {
            return [
                'valid' => false,
                'message' => "El {$field} debe tener al menos {$minLength} caracteres"
            ];
        }
        
        if (strlen($name) > $maxLength) {
            return [
                'valid' => false,
                'message' => "El {$field} no puede tener más de {$maxLength} caracteres"
            ];
        }
        
        // Verificar caracteres válidos
        if (!preg_match(self::$patterns['name'], $name)) {
            return [
                'valid' => false,
                'message' => "El {$field} contiene caracteres no válidos"
            ];
        }
        
        // Verificar que no sea solo espacios o guiones
        if (preg_match('/^[\s\-]+$/', $name)) {
            return [
                'valid' => false,
                'message' => "El {$field} debe contener letras"
            ];
        }
        
        return ['valid' => true, 'message' => ucfirst($field) . ' válido'];
    }
    
    /**
     * Validar empresa
     */
    public function validateEmpresa($empresa) {
        $empresa = trim($empresa);
        $maxLength = self::$defaultRules['empresa_max_length'];
        
        if (strlen($empresa) > $maxLength) {
            return [
                'valid' => false,
                'message' => "El nombre de empresa no puede tener más de {$maxLength} caracteres"
            ];
        }
        
        if (!preg_match(self::$patterns['empresa'], $empresa)) {
            return [
                'valid' => false,
                'message' => 'El nombre de empresa contiene caracteres no válidos'
            ];
        }
        
        return ['valid' => true, 'message' => 'Empresa válida'];
    }
    
    /**
     * Validar teléfono
     */
    public function validateTelefono($telefono) {
        $telefono = trim($telefono);
        $maxLength = self::$defaultRules['telefono_max_length'];
        
        if (strlen($telefono) > $maxLength) {
            return [
                'valid' => false,
                'message' => "El teléfono no puede tener más de {$maxLength} caracteres"
            ];
        }
        
        if (!preg_match(self::$patterns['telefono'], $telefono)) {
            return [
                'valid' => false,
                'message' => 'Formato de teléfono inválido'
            ];
        }
        
        return ['valid' => true, 'message' => 'Teléfono válido'];
    }
    
    /**
     * Validar rol de usuario
     */
    public function validateRol($rol) {
        $rolesValidos = ['Trial', 'Personal', 'Empresa', 'Administrador'];
        
        if (!in_array($rol, $rolesValidos)) {
            return [
                'valid' => false,
                'message' => 'Rol no válido. Roles permitidos: ' . implode(', ', $rolesValidos)
            ];
        }
        
        return ['valid' => true, 'message' => 'Rol válido'];
    }
    
    /**
     * Sanitizar datos de registro
     */
    public function sanitizeRegistrationData($data) {
        $sanitized = [];
        
        // Sanitizar email
        $sanitized['email'] = $this->sanitizeEmail($data['email']);
        
        // Sanitizar strings
        $stringFields = ['nombre', 'apellido', 'empresa', 'telefono'];
        foreach ($stringFields as $field) {
            if (!empty($data[$field])) {
                $sanitized[$field] = $this->sanitizeString($data[$field]);
            }
        }
        
        // La contraseña no se sanitiza (se mantiene como está para el hash)
        if (!empty($data['password'])) {
            $sanitized['password'] = $data['password'];
        }
        
        // Rol
        if (!empty($data['rol'])) {
            $sanitized['rol'] = $data['rol'];
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitizar email
     */
    public function sanitizeEmail($email) {
        return strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
    }
    
    /**
     * Sanitizar string general
     */
    public function sanitizeString($input) {
        // Eliminar espacios extra
        $input = trim($input);
        $input = preg_replace('/\s+/', ' ', $input);
        
        // Sanitizar HTML
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        // Eliminar caracteres de control
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return $input;
    }
    
    /**
     * Validar datos de login
     */
    public function validateLogin($email, $password) {
        $errors = [];
        
        if (empty($email)) {
            $errors[] = 'Email es requerido';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Formato de email inválido';
        }
        
        if (empty($password)) {
            $errors[] = 'Contraseña es requerida';
        }
        
        if (!empty($errors)) {
            return [
                'valid' => false,
                'message' => implode(', ', $errors),
                'errors' => $errors
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Datos de login válidos',
            'sanitized_data' => [
                'email' => $this->sanitizeEmail($email),
                'password' => $password
            ]
        ];
    }
    
    /**
     * Validar token
     */
    public function validateToken($token) {
        if (empty($token)) {
            return ['valid' => false, 'message' => 'Token es requerido'];
        }
        
        // Verificar longitud (tokens son de 64 caracteres hex)
        if (strlen($token) !== 64) {
            return ['valid' => false, 'message' => 'Token inválido'];
        }
        
        // Verificar que sea hexadecimal
        if (!ctype_xdigit($token)) {
            return ['valid' => false, 'message' => 'Token inválido'];
        }
        
        return ['valid' => true, 'message' => 'Token válido'];
    }
    
    /**
     * Calcular fortaleza de contraseña (0-100)
     */
    public function calculatePasswordStrength($password) {
        $score = 0;
        $length = strlen($password);
        
        // Puntos por longitud
        if ($length >= 8) $score += 25;
        if ($length >= 12) $score += 15;
        if ($length >= 16) $score += 10;
        
        // Puntos por variedad de caracteres
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 10;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 15;
        
        // Penalizaciones
        if (in_array(strtolower($password), self::$commonPasswords)) $score -= 50;
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 20; // Caracteres repetidos
        
        return max(0, min(100, $score));
    }
    
    /**
     * Obtener sugerencias para mejorar contraseña
     */
    public function getPasswordSuggestions($password) {
        $suggestions = [];
        $length = strlen($password);
        
        if ($length < 8) {
            $suggestions[] = 'Usa al menos 8 caracteres';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $suggestions[] = 'Incluye letras minúsculas';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $suggestions[] = 'Incluye letras mayúsculas';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $suggestions[] = 'Incluye números';
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $suggestions[] = 'Incluye símbolos especiales';
        }
        
        if (in_array(strtolower($password), self::$commonPasswords)) {
            $suggestions[] = 'Evita contraseñas comunes';
        }
        
        return $suggestions;
    }
    
    /**
     * Log de validación
     */
    private function logValidation($type, $result, $data = null) {
        if (config('debug')) {
            $logData = [
                'type' => $type,
                'valid' => $result['valid'],
                'message' => $result['message']
            ];
            
            if ($data && !in_array($type, ['password', 'login'])) {
                $logData['data'] = $data;
            }
            
            app_log("Validación {$type}: " . json_encode($logData), 'DEBUG');
        }
    }
}

// Log de inicialización
app_log("Módulo UserValidator cargado", 'INFO');
?>