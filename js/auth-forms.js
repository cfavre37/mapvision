/**
 * MapVision Analytics - Gestión de Formularios de Autenticación
 * Archivo: js/auth-forms.js
 */

class AuthForms {
    constructor() {
        this.currentModal = null;
    }

    /**
     * Mostrar modal de autenticación
     */
    showModal(initialTab = 'login') {
        if (this.currentModal) {
            this.currentModal.destructor();
        }
        
        this.currentModal = webix.ui({
            view: "window",
            id: "authModal",
            css: "auth-modal",
            width: 460,
            height: 650,
            position: "center",
            modal: true,
            head: false,
            body: {
                css: "auth-container",
                rows: [
                    {
                        template: `
                            <div class="auth-header">
                                <h2>MapVision Analytics</h2>
                                <p>Mapas Inteligentes con IA</p>
                            </div>
                        `,
                        height: 120,
                        borderless: true
                    },
                    {
                        view: "tabview",
                        id: "authTabs",
                        tabbar: {
                            css: "auth-tabs"
                        },
                        cells: [
                            {
                                header: "Iniciar Sesión",
                                body: this.createLoginForm()
                            },
                            {
                                header: "Registrarse",
                                body: this.createRegisterForm()
                            },
                            {
                                header: "Recuperar Contraseña",
                                body: this.createForgotPasswordForm()
                            }
                        ]
                    }
                ]
            }
        });
        
        this.currentModal.show();
        
        // Cambiar a la pestaña inicial
        const tabIndex = initialTab === 'register' ? 1 : initialTab === 'forgot' ? 2 : 0;
        webix.$$('authTabs').setValue(`tab${tabIndex + 1}`);
        
        // Enfocar primer campo
        setTimeout(() => {
            const firstInput = document.querySelector('#authModal input[type="text"], #authModal input[type="email"]');
            if (firstInput) firstInput.focus();
        }, 300);
    }

    /**
     * Crear formulario de login
     */
    createLoginForm() {
        return {
            view: "form",
            id: "loginForm",
            css: "auth-form",
            elements: [
                {
                    template: '<div id="loginMessage" class="alert"></div>',
                    height: 50,
                    borderless: true
                },
                {
                    view: "text",
                    name: "email",
                    label: "Email",
                    labelWidth: 80,
                    placeholder: "tu@email.com",
                    required: true,
                    invalidMessage: "Email es requerido"
                },
                {
                    view: "text",
                    name: "password",
                    type: "password",
                    label: "Contraseña",
                    labelWidth: 80,
                    placeholder: "Tu contraseña",
                    required: true,
                    invalidMessage: "Contraseña es requerida"
                },
                {
                    view: "checkbox",
                    name: "remember_me",
                    labelRight: "Recordarme",
                    labelWidth: 0,
                    value: false
                },
                {
                    margin: 20,
                    cols: [
                        {
                            view: "button",
                            value: "Iniciar Sesión",
                            css: "webix_primary",
                            click: () => this.handleLogin()
                        }
                    ]
                },
                {
                    template: '<div class="auth-divider"><span>¿Problemas para acceder?</span></div>',
                    height: 40,
                    borderless: true
                },
                {
                    cols: [
                        {
                            view: "button",
                            value: "¿Olvidaste tu contraseña?",
                            css: "webix_transparent",
                            click: () => webix.$$('authTabs').setValue('tab3')
                        },
                        {
                            view: "button",
                            value: "Crear cuenta",
                            css: "webix_transparent",
                            click: () => webix.$$('authTabs').setValue('tab2')
                        }
                    ]
                }
            ]
        };
    }

    /**
     * Crear formulario de registro
     */
    createRegisterForm() {
        return {
            view: "form",
            id: "registerForm",
            css: "auth-form",
            elements: [
                {
                    template: '<div id="registerMessage" class="alert"></div>',
                    height: 50,
                    borderless: true
                },
                {
                    cols: [
                        {
                            view: "text",
                            name: "nombre",
                            label: "Nombre",
                            required: true,
                            invalidMessage: "Nombre es requerido"
                        },
                        {
                            view: "text",
                            name: "apellido",
                            label: "Apellido",
                            required: true,
                            invalidMessage: "Apellido es requerido"
                        }
                    ]
                },
                {
                    view: "text",
                    name: "email",
                    label: "Email",
                    labelWidth: 80,
                    placeholder: "tu@email.com",
                    required: true,
                    invalidMessage: "Email válido es requerido"
                },
                {
                    view: "text",
                    name: "empresa",
                    label: "Empresa",
                    labelWidth: 80,
                    placeholder: "Nombre de tu empresa (opcional)"
                },
                {
                    view: "text",
                    name: "telefono",
                    label: "Teléfono",
                    labelWidth: 80,
                    placeholder: "+56 9 1234 5678 (opcional)"
                },
                {
                    view: "select",
                    name: "rol",
                    label: "Tipo de Cuenta",
                    labelWidth: 110,
                    value: "Trial",
                    options: [
                        { id: "Trial", value: "Trial (Gratuito)" },
                        { id: "Personal", value: "Personal" },
                        { id: "Empresa", value: "Empresa" }
                    ]
                },
                {
                    view: "text",
                    name: "password",
                    type: "password",
                    label: "Contraseña",
                    labelWidth: 80,
                    placeholder: "Mínimo 8 caracteres",
                    required: true,
                    invalidMessage: "Contraseña de al menos 8 caracteres",
                    on: {
                        onKeyPress: function() {
                            setTimeout(() => authForms.updatePasswordStrength(this.getValue()), 100);
                        }
                    }
                },
                {
                    template: '<div class="password-strength"><div class="password-strength-bar" style="width: 0%"></div></div>',
                    height: 15,
                    borderless: true
                },
                {
                    view: "text",
                    name: "confirmPassword",
                    type: "password",
                    label: "Confirmar",
                    labelWidth: 80,
                    placeholder: "Repite tu contraseña",
                    required: true,
                    invalidMessage: "Confirma tu contraseña"
                },
                {
                    margin: 20,
                    cols: [
                        {
                            view: "button",
                            value: "Crear Cuenta",
                            css: "webix_primary",
                            click: () => this.handleRegister()
                        }
                    ]
                },
                {
                    template: '<div class="auth-divider"><span>¿Ya tienes cuenta?</span></div>',
                    height: 40,
                    borderless: true
                },
                {
                    view: "button",
                    value: "Iniciar Sesión",
                    css: "webix_transparent",
                    click: () => webix.$$('authTabs').setValue('tab1')
                }
            ]
        };
    }

    /**
     * Crear formulario de recuperación
     */
    createForgotPasswordForm() {
        return {
            view: "form",
            id: "forgotForm",
            css: "auth-form",
            elements: [
                {
                    template: '<div id="forgotMessage" class="alert"></div>',
                    height: 50,
                    borderless: true
                },
                {
                    template: '<p style="text-align: center; color: #666; margin: 20px 0; line-height: 1.5;">Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.</p>',
                    height: 60,
                    borderless: true
                },
                {
                    view: "text",
                    name: "email",
                    label: "Email",
                    labelWidth: 80,
                    placeholder: "tu@email.com",
                    required: true,
                    invalidMessage: "Email es requerido"
                },
                {
                    margin: 20,
                    cols: [
                        {
                            view: "button",
                            value: "Enviar Enlace",
                            css: "webix_primary",
                            click: () => this.handleForgotPassword()
                        }
                    ]
                },
                {
                    template: '<div class="auth-divider"><span>¿Recordaste tu contraseña?</span></div>',
                    height: 40,
                    borderless: true
                },
                {
                    view: "button",
                    value: "Volver al Login",
                    css: "webix_transparent",
                    click: () => webix.$$('authTabs').setValue('tab1')
                }
            ]
        };
    }

    /**
     * Manejar login
     */
    async handleLogin() {
        const form = webix.$$('loginForm');
        if (!form.validate()) return;
        
        const values = form.getValues();
        this.showMessage('loginMessage', 'Iniciando sesión...', 'info');
        
        const result = await authManager.login(values.email, values.password, values.remember_me);
        
        if (result.success) {
            this.showMessage('loginMessage', 'Login exitoso', 'success');
            setTimeout(() => {
                this.currentModal.close();
                if (result.email_verificado) {
                    window.location.href = 'dashboard.html';
                }
            }, 1000);
        } else {
            this.showMessage('loginMessage', result.message, 'error');
        }
    }

    /**
     * Manejar registro
     */
    async handleRegister() {
        const form = webix.$$('registerForm');
        if (!form.validate()) return;
        
        const values = form.getValues();
        
        // Validar contraseñas
        if (values.password !== values.confirmPassword) {
            this.showMessage('registerMessage', 'Las contraseñas no coinciden', 'error');
            return;
        }
        
        if (values.password.length < 8) {
            this.showMessage('registerMessage', 'La contraseña debe tener al menos 8 caracteres', 'error');
            return;
        }
        
        this.showMessage('registerMessage', 'Creando cuenta...', 'info');
        
        const result = await authManager.register(values);
        
        if (result.success) {
            this.showMessage('registerMessage', result.message, 'success');
            setTimeout(() => {
                webix.$$('authTabs').setValue('tab1');
                this.showMessage('loginMessage', 'Cuenta creada. Verifica tu email y luego inicia sesión.', 'success');
            }, 2000);
        } else {
            this.showMessage('registerMessage', result.message, 'error');
        }
    }

    /**
     * Manejar recuperación de contraseña
     */
    async handleForgotPassword() {
        const form = webix.$$('forgotForm');
        if (!form.validate()) return;
        
        const values = form.getValues();
        this.showMessage('forgotMessage', 'Enviando enlace...', 'info');
        
        const result = await authManager.forgotPassword(values.email);
        
        if (result.success) {
            this.showMessage('forgotMessage', result.message, 'success');
        } else {
            this.showMessage('forgotMessage', result.message, 'error');
        }
    }

    /**
     * Mostrar mensaje en modal
     */
    showMessage(containerId, message, type) {
        const container = document.getElementById(containerId);
        if (container) {
            container.className = `alert alert-${type} show`;
            container.textContent = message;
            
            // Auto-hide después de 5 segundos para mensajes de éxito
            if (type === 'success') {
                setTimeout(() => {
                    container.classList.remove('show');
                }, 5000);
            }
        }
    }

    /**
     * Actualizar indicador de fortaleza de contraseña
     */
    updatePasswordStrength(password) {
        const strengthBar = document.querySelector('.password-strength-bar');
        if (!strengthBar) return;
        
        let strength = 0;
        
        if (password.length >= 8) strength += 25;
        if (password.match(/[a-z]/)) strength += 25;
        if (password.match(/[A-Z]/)) strength += 25;
        if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) strength += 25;
        
        strengthBar.style.width = strength + '%';
    }

    /**
     * Cerrar modal
     */
    closeModal() {
        if (this.currentModal) {
            this.currentModal.close();
            this.currentModal = null;
        }
    }
}

// Instancia global
const authForms = new AuthForms();

// Funciones globales para compatibilidad
window.showAuthModal = (tab) => authForms.showModal(tab);
window.handleLogin = () => authForms.handleLogin();
window.handleRegister = () => authForms.handleRegister();
window.handleForgotPassword = () => authForms.handleForgotPassword();