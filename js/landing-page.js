/**
 * MapVision Analytics - Funcionalidad de Landing Page
 * Archivo: js/landing-page.js
 */

class LandingPage {
    constructor() {
        this.init();
    }

    /**
     * Inicializar funcionalidades de la landing page
     */
    init() {
        // Configurar smooth scroll
        this.setupSmoothScroll();
        
        // Configurar header scroll
        this.setupHeaderScroll();
        
        // Configurar animaciones
        this.setupAnimations();
        
        // Configurar lazy loading
        this.setupLazyLoading();
        
        // Verificar hash en URL
        this.checkURLHash();
        
        console.log('🎨 Landing page inicializada');
    }

    /**
     * Configurar smooth scroll para enlaces internos
     */
    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Configurar efectos del header al hacer scroll
     */
    setupHeaderScroll() {
        let lastScrollY = window.scrollY;
        let isScrolling = false;

        const updateHeader = () => {
            const header = document.querySelector('.header');
            const currentScrollY = window.scrollY;
            
            if (currentScrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
                header.style.backdropFilter = 'blur(10px)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = 'none';
                header.style.backdropFilter = 'blur(5px)';
            }

            // Ocultar/mostrar header al hacer scroll
            if (currentScrollY > lastScrollY && currentScrollY > 200) {
                // Scrolling down
                header.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                header.style.transform = 'translateY(0)';
            }

            lastScrollY = currentScrollY;
            isScrolling = false;
        };

        window.addEventListener('scroll', () => {
            if (!isScrolling) {
                requestAnimationFrame(updateHeader);
                isScrolling = true;
            }
        }, { passive: true });
    }

    /**
     * Configurar animaciones de scroll
     */
    setupAnimations() {
        // Intersection Observer para animaciones
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, observerOptions);

        // Observar elementos que necesitan animación
        const animateElements = document.querySelectorAll(
            '.feature-card, .hero-content > *, .features-header'
        );
        
        animateElements.forEach(el => {
            el.classList.add('animate-on-scroll');
            observer.observe(el);
        });

        // Animación del heatmap
        this.animateHeatmap();
    }

    /**
     * Animar el heatmap demo
     */
    animateHeatmap() {
        const heatmapCells = document.querySelectorAll('.heatmap-cell');
        
        // Animar entrada de las celdas
        heatmapCells.forEach((cell, index) => {
            cell.style.animationDelay = `${index * 0.1}s`;
            cell.classList.add('fade-in-cell');
        });

        // Efecto hover mejorado
        heatmapCells.forEach(cell => {
            cell.addEventListener('mouseenter', () => {
                cell.style.transform = 'scale(1.1) rotate(2deg)';
                cell.style.zIndex = '10';
            });

            cell.addEventListener('mouseleave', () => {
                cell.style.transform = 'scale(1) rotate(0deg)';
                cell.style.zIndex = '1';
            });
        });

        // Pulso automático cada 5 segundos
        setInterval(() => {
            const randomCell = heatmapCells[Math.floor(Math.random() * heatmapCells.length)];
            randomCell.classList.add('pulse-highlight');
            
            setTimeout(() => {
                randomCell.classList.remove('pulse-highlight');
            }, 1000);
        }, 5000);
    }

    /**
     * Configurar lazy loading para imágenes
     */
    setupLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    /**
     * Verificar hash en URL para auto-abrir modal
     */
    checkURLHash() {
        const hash = window.location.hash;
        if (hash === '#login') {
            setTimeout(() => showAuthModal('login'), 500);
        } else if (hash === '#register') {
            setTimeout(() => showAuthModal('register'), 500);
        }
    }

    /**
     * Actualizar UI según estado de autenticación
     */
    updateAuthUI() {
        const isAuthenticated = authManager.isAuthenticated();
        const user = authManager.getCurrentUser();
        
        // Mostrar/ocultar elementos según autenticación
        const guestElements = document.querySelectorAll('.no-auth-required');
        const authElements = document.querySelectorAll('.auth-required');
        
        guestElements.forEach(el => {
            el.style.display = isAuthenticated ? 'none' : '';
        });
        
        authElements.forEach(el => {
            el.style.display = isAuthenticated ? '' : 'none';
        });
        
        // Actualizar información del usuario
        if (isAuthenticated && user) {
            const userNameElements = document.querySelectorAll('.user-name');
            userNameElements.forEach(el => {
                el.textContent = user.nombre;
            });
            
            const userInitialsElements = document.querySelectorAll('.user-initials');
            userInitialsElements.forEach(el => {
                el.textContent = (user.nombre.charAt(0) + user.apellido.charAt(0)).toUpperCase();
            });
        }
    }

    /**
     * Configurar listeners de eventos de autenticación
     */
    setupAuthEventListeners() {
        document.addEventListener('auth:success', (event) => {
            console.log('🎉 Auth success:', event.detail);
            this.updateAuthUI();
            
            // Mostrar mensaje de bienvenida
            this.showWelcomeMessage(event.detail.user);
        });
        
        document.addEventListener('auth:failure', (event) => {
            console.log('💥 Auth failure:', event.detail);
            this.updateAuthUI();
        });
    }

    /**
     * Mostrar mensaje de bienvenida
     */
    showWelcomeMessage(user) {
        const message = `¡Bienvenido ${user.nombre}! Tu cuenta está lista.`;
        this.showNotification(message, 'success', 5000);
    }

    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'info', duration = 3000) {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `landing-notification landing-notification-${type}`;
        notification.textContent = message;
        
        // Estilos
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '600',
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
     * Manejar clics en características
     */
    handleFeatureClick(featureId) {
        const feature = document.querySelector(`[data-feature="${featureId}"]`);
        if (feature) {
            feature.scrollIntoView({ behavior: 'smooth' });
            
            // Resaltar temporalmente
            feature.classList.add('highlight-feature');
            setTimeout(() => {
                feature.classList.remove('highlight-feature');
            }, 2000);
        }
    }

    /**
     * Configurar parallax effect
     */
    setupParallax() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        
        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset;
            
            parallaxElements.forEach(element => {
                const speed = element.dataset.parallax || 0.5;
                const yPos = -(scrollTop * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        }, { passive: true });
    }

    /**
     * Configurar efectos de typing
     */
    setupTypingEffect() {
        const typingElements = document.querySelectorAll('[data-typing]');
        
        typingElements.forEach(element => {
            const text = element.textContent;
            const speed = parseInt(element.dataset.typingSpeed) || 50;
            
            element.textContent = '';
            element.style.borderRight = '2px solid #667eea';
            
            let i = 0;
            const typeWriter = () => {
                if (i < text.length) {
                    element.textContent += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, speed);
                } else {
                    // Remover cursor después de completar
                    setTimeout(() => {
                        element.style.borderRight = 'none';
                    }, 1000);
                }
            };
            
            // Iniciar cuando el elemento sea visible
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        setTimeout(typeWriter, 500);
                        observer.unobserve(element);
                    }
                });
            });
            
            observer.observe(element);
        });
    }

    /**
     * Configurar contador animado
     */
    setupCounterAnimation() {
        const counters = document.querySelectorAll('[data-counter]');
        
        const animateCounter = (element) => {
            const target = parseInt(element.dataset.counter);
            const duration = parseInt(element.dataset.duration) || 2000;
            const increment = target / (duration / 16); // 60 FPS
            
            let current = 0;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString();
            }, 16);
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        });
        
        counters.forEach(counter => observer.observe(counter));
    }

    /**
     * Manejar redimensionamiento de ventana
     */
    handleResize() {
        let resizeTimer;
        
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                // Recalcular animaciones si es necesario
                this.updateAnimations();
            }, 250);
        });
    }

    /**
     * Actualizar animaciones después de resize
     */
    updateAnimations() {
        // Lógica para actualizar animaciones después de resize
        const animatedElements = document.querySelectorAll('.animate-in');
        animatedElements.forEach(el => {
            el.style.transform = '';
            el.style.opacity = '';
        });
    }

    /**
     * Configurar modo oscuro/claro
     */
    setupThemeToggle() {
        const themeToggle = document.querySelector('[data-theme-toggle]');
        if (!themeToggle) return;
        
        const currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const newTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
    }

    /**
     * Cleanup al salir de la página
     */
    cleanup() {
        // Limpiar event listeners y timers
        window.removeEventListener('scroll', this.handleScroll);
        window.removeEventListener('resize', this.handleResize);
    }
}

// CSS adicional para animaciones
const additionalCSS = `
.animate-on-scroll {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s ease-out;
}

.animate-in {
    opacity: 1 !important;
    transform: translateY(0) !important;
}

.fade-in-cell {
    animation: fadeInCell 0.5s ease-out forwards;
}

@keyframes fadeInCell {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.pulse-highlight {
    animation: pulseHighlight 1s ease-in-out;
}

@keyframes pulseHighlight {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
    }
}

.highlight-feature {
    animation: highlightFeature 2s ease-in-out;
}

@keyframes highlightFeature {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    50% {
        transform: scale(1.02);
        box-shadow: 0 8px 40px rgba(102, 126, 234, 0.2);
    }
}

[data-theme="dark"] {
    --background-light: #1a1a1a;
    --text-primary: #ffffff;
    --text-secondary: #a0a0a0;
}
`;

// Inyectar CSS adicional
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);

// Instancia global
const landingPage = new LandingPage();

// Cleanup al salir
window.addEventListener('beforeunload', () => {
    landingPage.cleanup();
});

// Funciones globales
window.toggleUserDropdown = () => {
    console.log('Toggle user dropdown');
    // Implementar dropdown de usuario
};