/**
 * MapVision Analytics - Script Principal
 * Archivo: js/main.js
 * Descripción: Orquestador principal de la aplicación
 */

class MapVisionApp {
    constructor() {
        this.initialized = false;
        this.modules = {};
        this.config = {
            debug: false,
            version: '2.0.0',
            apiBaseUrl: 'api/',
            features: {
                animations: true,
                notifications: true,
                analytics: false
            }
        };
        
        this.init();
    }

    /**
     * Inicializar aplicación
     */
    async init() {
        try {
            console.log('🚀 Inicializando MapVision Analytics v' + this.config.version);
            
            // Verificar dependencias
            this.checkDependencies();
            
            // Configurar manejo de errores globales
            this.setupGlobalErrorHandling();
            
            // Inicializar módulos en orden
            await this.initializeModules();
            
            // Configurar eventos globales
            this.setupGlobalEvents();
            
            // Verificar autenticación inicial
            this.checkInitialAuth();
            
            this.initialized = true;
            console.log('✅ MapVision Analytics inicializado correctamente');
            
            // Disparar evento de aplicación lista
            this.dispatchEvent('app:ready', { version: this.config.version });
            
        } catch (error) {
            console.error('❌ Error inicializando aplicación:', error);
            this.handleInitError(error);
        }
    }

    /**
     * Verificar dependencias requeridas
     */
    checkDependencies() {
        const requiredGlobals = [
            'webix',
            'authManager',
            'authForms',
            'landingPage'
        ];
        
        const missing = requiredGlobals.filter(dep => typeof window[dep] === 'undefined');
        
        if (missing.length > 0) {
            throw new Error(`Dependencias faltantes: ${missing.join(', ')}`);
        }
        
        console.log('✅ Todas las dependencias están disponibles');
    }

    /**
     * Inicializar módulos
     */
    async initializeModules() {
        const modules = [
            { name: 'auth', instance: authManager, required: true },
            { name: 'forms', instance: authForms, required: true },
            { name: 'landing', instance: landingPage, required: true }
        ];
        
        for (const module of modules) {
            try {
                if (module.instance && typeof module.instance.init === 'function') {
                    await module.instance.init();
                }
                this.modules[module.name] = module.instance;
                console.log(`✅ Módulo ${module.name} inicializado`);
            } catch (error) {
                console.error(`❌ Error inicializando módulo ${module.name}:`, error);
                if (module.required) {
                    throw error;
                }
            }
        }
    }

    /**
     * Configurar manejo de errores globales
     */
    setupGlobalErrorHandling() {
        // Errores JavaScript no capturados
        window.addEventListener('error', (event) => {
            console.error('Error global capturado:', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error
            });
            
            this.handleError(event.error || new Error(event.message));
        });
        
        // Promesas rechazadas no capturadas
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Promise rejection no capturada:', event.reason);
            this.handleError(event.reason);
        });
        
        // Errores de recursos (imágenes, scripts, etc.)
        window.addEventListener('error', (event) => {
            if (event.target !== window) {
                console.warn('Error cargando recurso:', {
                    type: event.target.tagName,
                    source: event.target.src || event.target.href,
                    message: 'Recurso no disponible'
                });
            }
        }, true);
    }

    /**
     * Configurar eventos globales
     */
    setupGlobalEvents() {
        // Evento de carga completa del DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.onDOMReady();
            });
        } else {
            this.onDOMReady();
        }
        
        // Evento de carga completa de la página
        window.addEventListener('load', () => {
            this.onPageLoad();
        });
        
        // Eventos de visibilidad de página
        document.addEventListener('visibilitychange', () => {
            this.handleVisibilityChange();
        });
        
        // Eventos de conexión
        window.addEventListener('online', () => {
            this.handleConnectionChange(true);
        });
        
        window.addEventListener('offline', () => {
            this.handleConnectionChange(false);
        });
        
        // Eventos de autenticación
        document.addEventListener('auth:success', (event) => {
            this.handleAuthSuccess(event.detail);
        });
        
        document.addEventListener('auth:failure', (event) => {
            this.handleAuthFailure(event.detail);
        });
    }

    /**
     * Verificar autenticación inicial
     */
    async checkInitialAuth() {
        try {
            const isAuthenticated = await authManager.checkSession();
            
            if (isAuthenticated) {
                console.log('👤 Usuario autenticado detectado');
                this.updateUIForAuthenticatedUser();
            } else {
                console.log('🔓 Usuario no autenticado');
                this.updateUIForGuestUser();
            }
        } catch (error) {
            console.error('Error verificando autenticación inicial:', error);
        }
    }

    /**
     * Manejar DOM listo
     */
    onDOMReady() {
        console.log('📄 DOM cargado completamente');
        
        // Configurar tooltips
        this.setupTooltips();
        
        // Configurar atajos de teclado
        this.setupKeyboardShortcuts();
        
        // Configurar lazy loading mejorado
        this.setupAdvancedLazyLoading();
    }

    /**
     * Manejar página completamente cargada
     */
    onPageLoad() {
        console.log('🎨 Página cargada completamente');
        
        // Ocultar loading si existe
        this.hideInitialLoading();
        
        // Iniciar métricas de rendimiento
        this.startPerformanceMetrics();
        
        // Configurar analytics si está habilitado
        if (this.config.features.analytics) {
            this.setupAnalytics();
        }
    }

    /**
     * Manejar cambio de visibilidad
     */
    handleVisibilityChange() {
        if (document.hidden) {
            console.log('📱 Página oculta');
            this.onPageHidden();
        } else {
            console.log('📱 Página visible');
            this.onPageVisible();
        }
    }

    /**
     * Manejar cambio de conexión
     */
    handleConnectionChange(isOnline) {
        console.log(isOnline ? '🌐 Conexión restaurada' : '📵 Sin conexión');
        
        if (isOnline) {
            this.showNotification('Conexión restaurada', 'success', 3000);
            // Reactivar funcionalidades que requieren conexión
            this.enableOnlineFeatures();
        } else {
            this.showNotification('Sin conexión a internet', 'warning', 5000);
            // Activar modo offline
            this.enableOfflineMode();
        }
    }

    /**
     * Manejar autenticación exitosa
     */
    handleAuthSuccess(userData) {
        console.log('🎉 Autenticación exitosa:', userData);
        this.updateUIForAuthenticatedUser(userData.user);
        this.showNotification(`¡Bienvenido ${userData.user.nombre}!`, 'success', 4000);
    }

    /**
     * Manejar falla de autenticación
     */
    handleAuthFailure(data) {
        console.log('🔒 Falla de autenticación:', data);
        this.updateUIForGuestUser();
    }

    /**
     * Actualizar UI para usuario autenticado
     */
    updateUIForAuthenticatedUser(user = null) {
        if (landingPage && typeof landingPage.updateAuthUI === 'function') {
            landingPage.updateAuthUI();
        }
        
        // Habilitar funcionalidades para usuarios autenticados
        this.enableAuthenticatedFeatures();
    }

    /**
     * Actualizar UI para usuario invitado
     */
    updateUIForGuestUser() {
        if (landingPage && typeof landingPage.updateAuthUI === 'function') {
            landingPage.updateAuthUI();
        }
        
        // Deshabilitar funcionalidades que requieren autenticación
        this.disableAuthenticatedFeatures();
    }

    /**
     * Configurar tooltips
     */
    setupTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    /**
     * Configurar atajos de teclado
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K: Abrir búsqueda o comando
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.openCommandPalette();
            }
            
            // Escape: Cerrar modales
            if (e.key === 'Escape') {
                this.closeModals();
            }
            
            // Ctrl/Cmd + L: Abrir login
            if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
                e.preventDefault();
                if (!authManager.isAuthenticated()) {
                    showAuthModal('login');
                }
            }
        });
    }

    /**
     * Configurar lazy loading avanzado
     */
    setupAdvancedLazyLoading() {
        const lazyElements = document.querySelectorAll('[data-lazy]');
        
        const lazyObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadLazyElement(entry.target);
                    lazyObserver.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '50px'
        });
        
        lazyElements.forEach(element => lazyObserver.observe(element));
    }

    /**
     * Cargar elemento lazy
     */
    loadLazyElement(element) {
        const type = element.dataset.lazy;
        
        switch (type) {
            case 'image':
                if (element.dataset.src) {
                    element.src = element.dataset.src;
                    element.classList.add('loaded');
                }
                break;
            case 'component':
                this.loadComponent(element);
                break;
            case 'script':
                this.loadScript(element.dataset.src);
                break;
        }
    }

    /**
     * Mostrar tooltip
     */
    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'app-tooltip';
        tooltip.textContent = text;
        tooltip.id = 'app-tooltip';
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        setTimeout(() => tooltip.classList.add('show'), 10);
    }

    /**
     * Ocultar tooltip
     */
    hideTooltip() {
        const tooltip = document.getElementById('app-tooltip');
        if (tooltip) {
            tooltip.classList.remove('show');
            setTimeout(() => tooltip.remove(), 200);
        }
    }

    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'info', duration = 3000) {
        if (!this.config.features.notifications) return;
        
        const notification = document.createElement('div');
        notification.className = `app-notification app-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Estilos inline
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: '10000',
            opacity: '0',
            transform: 'translateX(100%)',
            transition: 'all 0.3s ease'
        });
        
        document.body.appendChild(notification);
        
        // Animar entrada
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto-remover
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }

    /**
     * Manejar errores
     */
    handleError(error) {
        console.error('Error de aplicación:', error);
        
        // En desarrollo, mostrar error completo
        if (this.config.debug) {
            this.showNotification(`Error: ${error.message}`, 'error', 5000);
        } else {
            // En producción, mensaje genérico
            this.showNotification('Ocurrió un error inesperado', 'error', 3000);
        }
        
        // Enviar error a servicio de logging si está configurado
        this.logError(error);
    }

    /**
     * Manejar error de inicialización
     */
    handleInitError(error) {
        document.body.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 100vh; font-family: Arial, sans-serif;">
                <div style="text-align: center; max-width: 500px;">
                    <h1 style="color: #dc3545;">Error de Inicialización</h1>
                    <p>No se pudo cargar MapVision Analytics correctamente.</p>
                    <p style="color: #666; font-size: 14px;">${error.message}</p>
                    <button onclick="window.location.reload()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Recargar Página</button>
                </div>
            </div>
        `;
    }

    /**
     * Métodos de utilidad
     */
    onPageHidden() {
        // Pausar animaciones, timers, etc.
    }

    onPageVisible() {
        // Reanudar actividades
        if (authManager.isAuthenticated()) {
            authManager.checkSession();
        }
    }

    enableOnlineFeatures() {
        // Reactivar funcionalidades que requieren conexión
    }

    enableOfflineMode() {
        // Configurar modo offline
    }

    enableAuthenticatedFeatures() {
        // Habilitar funciones para usuarios autenticados
    }

    disableAuthenticatedFeatures() {
        // Deshabilitar funciones que requieren autenticación
    }

    hideInitialLoading() {
        const loader = document.querySelector('.app-loader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 300);
        }
    }

    startPerformanceMetrics() {
        // Implementar métricas de rendimiento
        if (typeof performance !== 'undefined') {
            const loadTime = performance.now();
            console.log(`⚡ Tiempo de carga: ${Math.round(loadTime)}ms`);
        }
    }

    setupAnalytics() {
        // Configurar analytics si está habilitado
        console.log('📊 Analytics configurado');
    }

    openCommandPalette() {
        console.log('🎯 Abrir paleta de comandos');
    }

    closeModals() {
        // Cerrar todos los modales abiertos
        if (authForms && typeof authForms.closeModal === 'function') {
            authForms.closeModal();
        }
    }

    loadComponent(element) {
        // Cargar componente dinámicamente
        console.log('📦 Cargando componente:', element);
    }

    loadScript(src) {
        // Cargar script dinámicamente
        const script = document.createElement('script');
        script.src = src;
        document.head.appendChild(script);
    }

    logError(error) {
        // Log de errores
        if (this.config.debug) {
            console.error('Error logged:', error);
        }
    }

    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(eventName, { detail });
        document.dispatchEvent(event);
    }

    /**
     * API pública
     */
    getVersion() {
        return this.config.version;
    }

    isInitialized() {
        return this.initialized;
    }

    getModule(name) {
        return this.modules[name];
    }
}

// CSS para tooltips y notificaciones
const appCSS = `
.app-tooltip {
    position: absolute;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    transform: translateY(5px);
    transition: all 0.2s ease;
    z-index: 10001;
}

.app-tooltip.show {
    opacity: 1;
    transform: translateY(0);
}

.app-notification {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    min-width: 300px;
    max-width: 400px;
}

.notification-content {
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.notification-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #999;
    margin-left: 10px;
}

.app-notification-success .notification-content {
    border-left: 4px solid #28a745;
}

.app-notification-error .notification-content {
    border-left: 4px solid #dc3545;
}

.app-notification-warning .notification-content {
    border-left: 4px solid #ffc107;
}

.app-notification-info .notification-content {
    border-left: 4px solid #17a2b8;
}
`;

// Inyectar CSS
const appStyle = document.createElement('style');
appStyle.textContent = appCSS;
document.head.appendChild(appStyle);

// Inicializar aplicación cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.mapVisionApp = new MapVisionApp();
    });
} else {
    window.mapVisionApp = new MapVisionApp();
}