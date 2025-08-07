/**
 * MapVision Analytics - Cliente de Autenticación
 * Archivo: js/auth-check.js
 * Descripción: Gestión del lado cliente para autenticación y sesiones
 */

class AuthManager {
    constructor() {
        this.API_BASE = 'api/auth.php';
        this.ADMIN_API_BASE = 'api/admin.php';
        this.currentUser = null;
        this.sessionCheckInterval = null;
        this.activityCheckInterval = null;
        this.lastActivity = Date.now();
        this.sessionWarningShown = false;
        this.config = {
            sessionCheckInterval: 300000, // 5 minutos
            activityCheckInterval: 60000,  // 1 minuto
            sessionWarningTime: 300000,    // 5 minutos antes de expirar
            inactivityLogout: 1800000,     // 30 minutos de inactividad
            autoRedirectDelay: 3000        // 3 segundos
        };
        
        // Inicializar
        this.init();
    }
    
    /**
     * Inicializar el gestor de autenticación
     */
    async init() {
        try {
            console.log('🔐 Inicializando AuthManager...');
            
            // Verificar sesión existente
            await this.checkSession();
            
            // Configurar verificación periódica
            this.startPeriodicChecks();
            
            // Configurar listeners de actividad
            this.setupActivityListeners();
            
            // Configurar listener de visibilidad
            this.setupVisibilityListener();
            
            console.log('✅ AuthManager inicializado correctamente');
            
        } catch (error) {
            console.error('❌ Error inicializando AuthManager:', error);
        }
    }
    
    /**
     * Verificar sesión actual
     */
    async checkSession() {
        try {
            const response = await this.makeRequest('GET', this.API_BASE + '?action=verify_session');
            
            if (response.success && response.user) {
                this.currentUser = response.user;
                this.onAuthSuccess(response.user);
                return true;
            } else {
                this.currentUser = null;
                this.onAuthFailure();
                return false;
            }
        } catch (error) {
            console.error('Error verificando sesión:', error);
            this.currentUser = null;
            this.onAuthFailure();
            return false;
        }
    }
    
    /**
     * Realizar login
     */
    async login(email, password, rememberMe = false) {
        try {
            this.showLoading('Iniciando sesión...');
            
            const response = await this.makeRequest('POST', this.API_BASE + '?action=login', {
                email: email,
                password: password,
                remember_me: rememberMe
            });
            
            this.hideLoading();
            
            if (response.success) {
                this.currentUser = response.user;
                this.onAuthSuccess(response.user);
                
                // Verificar si el email está verificado
                if (!response.email_verificado) {
                    this.showEmailVerificationWarning();
                }
                
                return response;
            } else {
                this.handleLoginError(response);
                return response;
            }
            
        } catch (error) {
            this.hideLoading();
            console.error('Error en login:', error);
            return {
                success: false,
                message: 'Error de conexión. Intenta nuevamente.',
                code: 'CONNECTION_ERROR'
            };
        }
    }
    
    /**
     * Realizar registro
     */
    async register(userData) {
        try {
            this.showLoading('Registrando usuario...');
            
            const response = await this.makeRequest('POST', this.API_BASE + '?action=register', userData);
            
            this.hideLoading();
            
            if (response.success) {
                this.showNotification('Registro exitoso. Verifica tu email para activar tu cuenta.', 'success');
            }
            
            return response;
            
        } catch (error) {
            this.hideLoading();
            console.error('Error en registro:', error);
            return {
                success: false,
                message: 'Error de conexión. Intenta nuevamente.',
                code: 'CONNECTION_ERROR'
            };
        }
    }
    
    /**
     * Cerrar sesión
     */
    async logout() {
        try {
            // Detener verificaciones periódicas
            this.stopPeriodicChecks();
            
            // Llamar a la API de logout
            await this.makeRequest('POST', this.API_BASE + '?action=logout');
            
            // Limpiar estado local
            this.currentUser = null;
            this.onAuthFailure();
            
            // Redirigir al home
            this.redirectToHome();
            
        } catch (error) {
            console.error('Error en logout:', error);
            // Aún así limpiar el estado local
            this.currentUser = null;
            this.onAuthFailure();
            this.redirectToHome();
        }
    }
    
    /**
     * Solicitar recuperación de contraseña
     */
    async forgotPassword(email) {
        try {
            this.showLoading('Enviando solicitud...');
            
            const response = await this.makeRequest('POST', this.API_BASE + '?action=forgot_password', {
                email: email
            });
            
            this.hideLoading();
            
            if (response.success) {
                this.showNotification('Instrucciones enviadas a tu email.', 'success');
            }
            
            return response;
            
        } catch (error) {
            this.hideLoading();
            console.error('Error en forgot password:', error);
            return {
                success: false,
                message: 'Error de conexión. Intenta nuevamente.'
            };
        }
    }
    
    /**
     * Cambiar contraseña
     */
    async changePassword(currentPassword, newPassword) {
        try {
            this.showLoading('Cambiando contraseña...');
            
            const response = await this.makeRequest('POST', this.API_BASE + '?action=change_password', {
                current_password: currentPassword,
                new_password: newPassword
            });
            
            this.hideLoading();
            
            if (response.success) {
                this.showNotification('Contraseña actualizada exitosamente.', 'success');
            }
            
            return response;
            
        } catch (error) {
            this.hideLoading();
            console.error('Error cambiando contraseña:', error);
            return {
                success: false,
                message: 'Error de conexión. Intenta nuevamente.'
            };
        }
    }
    
    /**
     * Verificar si el usuario tiene un rol específico
     */
    hasRole(requiredRole) {
        if (!this.currentUser) return false;
        
        const roleHierarchy = {
            'Trial': 1,
            'Personal': 2,
            'Empresa': 3,
            'Administrador': 4
        };

        const userLevel = roleHierarchy[this.currentUser.rol] || 0;
        const requiredLevel = roleHierarchy[requiredRole] || 0;

        return userLevel >= requiredLevel;
    }
    
    /**
     * Verificar si el usuario puede acceder a una funcionalidad
     */
    canAccess(feature) {
        if (!this.currentUser) return false;

        const permissions = {
            'Trial': ['view_basic_maps', 'create_basic_maps'],
            'Personal': ['view_basic_maps', 'create_basic_maps', 'export_data', 'advanced_analytics'],
            'Empresa': ['view_basic_maps', 'create_basic_maps', 'export_data', 'advanced_analytics', 'team_management', 'custom_integrations'],
            'Administrador': ['all']
        };

        const userPermissions = permissions[this.currentUser.rol] || [];
        return userPermissions.includes('all') || userPermissions.includes(feature);
    }
    
    /**
     * Obtener usuario actual
     */
    getCurrentUser() {
        return this.currentUser;
    }
    
    /**
     * Verificar si está autenticado
     */
    isAuthenticated() {
        return this.currentUser !== null;
    }
    
    /**
     * Configurar verificaciones periódicas
     */
    startPeriodicChecks() {
        // Verificar sesión cada 5 minutos
        this.sessionCheckInterval = setInterval(async () => {
            if (document.visibilityState === 'visible') {
                const isValid = await this.checkSession();
                if (!isValid && this.shouldRedirectOnInvalidSession()) {
                    this.handleSessionExpired();
                }
            }
        }, this.config.sessionCheckInterval);
        
        // Verificar actividad cada minuto
        this.activityCheckInterval = setInterval(() => {
            this.checkInactivity();
        }, this.config.activityCheckInterval);
    }
    
    /**
     * Detener verificaciones periódicas
     */
    stopPeriodicChecks() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
            this.sessionCheckInterval = null;
        }
        
        if (this.activityCheckInterval) {
            clearInterval(this.activityCheckInterval);
            this.activityCheckInterval = null;
        }
    }
    
    /**
     * Configurar listeners de actividad del usuario
     */
    setupActivityListeners() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateLastActivity();
            }, { passive: true });
        });
    }
    
    /**
     * Actualizar última actividad
     */
    updateLastActivity() {
        this.lastActivity = Date.now();
        this.sessionWarningShown = false;
    }
    
    /**
     * Verificar inactividad
     */
    checkInactivity() {
        const now = Date.now();
        const timeSinceActivity = now - this.lastActivity;
        
        // Mostrar advertencia 5 minutos antes de logout por inactividad
        if (timeSinceActivity > (this.config.inactivityLogout - this.config.sessionWarningTime) && 
            !this.sessionWarningShown && this.isAuthenticated()) {
            this.showInactivityWarning();
            this.sessionWarningShown = true;
        }
        
        // Logout por inactividad
        if (timeSinceActivity > this.config.inactivityLogout && this.isAuthenticated()) {
            this.handleInactivityLogout();
        }
    }
    
    /**
     * Configurar listener de visibilidad de página
     */
    setupVisibilityListener() {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.isAuthenticated()) {
                // Verificar sesión cuando la página vuelve a ser visible
                this.checkSession();
                this.updateLastActivity();
            }
        });
    }
    
    /**
     * Realizar request HTTP
     */
    async makeRequest(method, url, data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include'
        };
        
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }
    
    /**
     * Manejar autenticación exitosa
     */
    onAuthSuccess(user) {
        console.log('✅ Usuario autenticado:', user);
        
        // Actualizar interfaz
        this.updateUserInterface(user);
        
        // Mostrar/ocultar elementos según rol
        this.toggleFeaturesByRole(user.rol);
        
        // Disparar evento personalizado
        this.dispatchAuthEvent('auth:success', { user });
    }
    
    /**
     * Manejar falla de autenticación
     */
    onAuthFailure() {
        console.log('❌ Autenticación fallida o sesión expirada');
        
        // Limpiar interfaz
        this.clearUserInterface();
        
        // Limpiar datos sensibles
        this.clearSensitiveData();
        
        // Disparar evento personalizado
        this.dispatchAuthEvent('auth:failure');
    }
    
    /**
     * Actualizar interfaz de usuario
     */
    updateUserInterface(user) {
        // Actualizar elementos que muestran info del usuario
        const userNameElements = document.querySelectorAll('.user-name');
        userNameElements.forEach(el => {
            el.textContent = `${user.nombre} ${user.apellido}`;
        });

        const userEmailElements = document.querySelectorAll('.user-email');
        userEmailElements.forEach(el => {
            el.textContent = user.email;
        });

        const userRoleElements = document.querySelectorAll('.user-role');
        userRoleElements.forEach(el => {
            el.textContent = user.rol;
        });
        
        // Mostrar elementos autenticados
        const authElements = document.querySelectorAll('.auth-required');
        authElements.forEach(el => {
            el.style.display = '';
        });
        
        // Ocultar elementos no autenticados
        const noAuthElements = document.querySelectorAll('.no-auth-required');
        noAuthElements.forEach(el => {
            el.style.display = 'none';
        });
    }
    
    /**
     * Limpiar interfaz de usuario
     */
    clearUserInterface() {
        // Ocultar elementos autenticados
        const authElements = document.querySelectorAll('.auth-required');
        authElements.forEach(el => {
            el.style.display = 'none';
        });
        
        // Mostrar elementos no autenticados
        const noAuthElements = document.querySelectorAll('.no-auth-required');
        noAuthElements.forEach(el => {
            el.style.display = '';
        });
    }
    
    /**
     * Mostrar/ocultar funcionalidades por rol
     */
    toggleFeaturesByRole(role) {
        const features = {
            'trial-only': ['Trial'],
            'personal-plus': ['Personal', 'Empresa', 'Administrador'],
            'empresa-plus': ['Empresa', 'Administrador'],
            'admin-only': ['Administrador']
        };

        Object.keys(features).forEach(featureClass => {
            const elements = document.querySelectorAll(`.${featureClass}`);
            const shouldShow = features[featureClass].includes(role);
            
            elements.forEach(el => {
                el.style.display = shouldShow ? '' : 'none';
            });
        });
    }
    
    /**
     * Limpiar datos sensibles
     */
    clearSensitiveData() {
        // Limpiar localStorage y sessionStorage
        const keysToRemove = ['userPreferences', 'tempData', 'dashboardState'];
        keysToRemove.forEach(key => {
            localStorage.removeItem(key);
            sessionStorage.removeItem(key);
        });
    }
    
    /**
     * Manejar errores de login
     */
    handleLoginError(response) {
        let message = response.message || 'Error desconocido';
        
        switch (response.code) {
            case 'RATE_LIMIT_EXCEEDED':
                message = 'Demasiados intentos. Espera un momento antes de intentar nuevamente.';
                break;
            case 'USER_BLOCKED':
                message = 'Tu cuenta está temporalmente bloqueada por múltiples intentos fallidos.';
                break;
            case 'ACCOUNT_DISABLED':
                message = 'Tu cuenta ha sido desactivada. Contacta al administrador.';
                break;
            case 'EMAIL_NOT_VERIFIED':
                message = 'Debes verificar tu email antes de iniciar sesión.';
                this.showEmailVerificationOptions(response);
                break;
        }
        
        this.showNotification(message, 'error');
    }
    
    /**
     * Manejar sesión expirada
     */
    handleSessionExpired() {
        this.showNotification('Tu sesión ha expirado. Por favor inicia sesión nuevamente.', 'warning');
        
        setTimeout(() => {
            this.redirectToLogin();
        }, this.config.autoRedirectDelay);
    }
    
    /**
     * Manejar logout por inactividad
     */
    handleInactivityLogout() {
        this.showNotification('Sesión cerrada por inactividad.', 'info');
        this.logout();
    }
    
    /**
     * Mostrar advertencia de inactividad
     */
    showInactivityWarning() {
        const message = '⚠️ Tu sesión expirará pronto por inactividad. Mueve el mouse para mantenerla activa.';
        this.showNotification(message, 'warning', 10000);
    }
    
    /**
     * Mostrar advertencia de email no verificado
     */
    showEmailVerificationWarning() {
        const message = '📧 Tu email no está verificado. Algunas funcionalidades pueden estar limitadas.';
        this.showNotification(message, 'warning', 8000);
    }
    
    /**
     * Mostrar opciones de verificación de email
     */
    showEmailVerificationOptions(response) {
        // Implementar modal o UI para reenviar verificación
        console.log('Mostrar opciones de verificación de email');
    }
    
    /**
     * Determinar si debe redirigir en sesión inválida
     */
    shouldRedirectOnInvalidSession() {
        const protectedPages = ['/dashboard.html', '/admin.html', '/profile.html'];
        const currentPath = window.location.pathname;
        
        return protectedPages.some(page => currentPath.includes(page));
    }
    
    /**
     * Redirigir al login
     */
    redirectToLogin() {
        window.location.href = '/index.html#login';
    }
    
    /**
     * Redirigir al home
     */
    redirectToHome() {
        window.location.href = '/index.html';
    }
    
    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'info', duration = 5000) {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `auth-notification auth-notification-${type}`;
        notification.textContent = message;
        
        // Estilos básicos
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '500',
            zIndex: '10000',
            maxWidth: '400px',
            opacity: '0',
            transform: 'translateX(100%)',
            transition: 'all 0.3s ease'
        });
        
        // Colores por tipo
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#17a2b8'
        };
        
        notification.style.backgroundColor = colors[type] || colors.info;
        
        // Agregar al DOM
        document.body.appendChild(notification);
        
        // Animar entrada
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Remover después del duration
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    }
    
    /**
     * Mostrar/ocultar loading
     */
    showLoading(message = 'Cargando...') {
        // Implementar overlay de loading
        console.log('Loading:', message);
    }
    
    hideLoading() {
        // Ocultar overlay de loading
        console.log('Loading complete');
    }
    
    /**
     * Disparar evento personalizado
     */
    dispatchAuthEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, { detail });
        document.dispatchEvent(event);
    }
    
    /**
     * Destructor - limpiar recursos
     */
    destroy() {
        this.stopPeriodicChecks();
        this.currentUser = null;
    }
}

// Crear instancia global del administrador de autenticación
const authManager = new AuthManager();

// Funciones globales de conveniencia
window.authManager = authManager;

window.requireAuth = () => {
    if (!authManager.isAuthenticated()) {
        authManager.redirectToLogin();
        return false;
    }
    return true;
};

window.hasRole = (role) => authManager.hasRole(role);

window.canAccess = (feature) => authManager.canAccess(feature);

window.getCurrentUser = () => authManager.getCurrentUser();

window.logout = () => authManager.logout();

// Eventos de escucha para otras partes de la aplicación
document.addEventListener('auth:success', (event) => {
    console.log('🎉 Evento de autenticación exitosa:', event.detail);
});

document.addEventListener('auth:failure', (event) => {
    console.log('💥 Evento de falla de autenticación:', event.detail);
});

// Limpiar recursos al cerrar la página
window.addEventListener('beforeunload', () => {
    authManager.destroy();
});

// Logging de inicialización
console.log('🚀 AuthManager cargado y disponible globalmente');

// Exportar para módulos ES6 si es necesario
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthManager;
}