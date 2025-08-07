<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MapVision Analytics</title>
    
    <!-- Meta tags -->
    <meta name="description" content="Dashboard de MapVision Analytics - Panel de control para análisis de mapas inteligentes con IA">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <!-- Webix CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.webix.com/edge/webix.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        /* Header */
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo {
            font-size: 28px;
        }
        
        .brand-text h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        
        .brand-text p {
            font-size: 13px;
            opacity: 0.9;
            margin: 0;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .user-role {
            font-size: 12px;
            opacity: 0.8;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-header {
            padding: 8px 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }
        
        .btn-header.btn-logout {
            background: rgba(220, 53, 69, 0.8);
            border-color: rgba(220, 53, 69, 0.8);
        }
        
        .btn-header.btn-logout:hover {
            background: rgba(220, 53, 69, 1);
        }
        
        /* Navigation */
        .dashboard-nav {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 0 30px;
            overflow-x: auto;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 30px;
        }
        
        .nav-item {
            position: relative;
            padding: 15px 0;
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s ease;
            white-space: nowrap;
        }
        
        .nav-item:hover {
            color: #667eea;
        }
        
        .nav-item.active {
            color: #667eea;
        }
        
        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #667eea;
        }
        
        /* Main Content */
        .dashboard-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #667eea;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea20, transparent);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 15px;
            display: block;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            margin: 0 0 8px 0;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            margin: 0;
            font-weight: 500;
        }
        
        .stat-change {
            font-size: 12px;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 12px;
            display: inline-block;
        }
        
        .stat-change.positive {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-change.negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Section Container */
        .section-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .section-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .section-subtitle {
            font-size: 14px;
            color: #6c757d;
            margin: 5px 0 0 0;
        }
        
        .section-content {
            padding: 30px;
        }
        
        /* Feature Restrictions */
        .feature-restricted {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffc107;
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            margin: 30px 0;
        }
        
        .feature-restricted h3 {
            color: #856404;
            margin: 0 0 15px 0;
            font-size: 20px;
        }
        
        .feature-restricted p {
            color: #856404;
            margin: 0 0 20px 0;
            line-height: 1.6;
        }
        
        .upgrade-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: transform 0.3s ease;
        }
        
        .upgrade-button:hover {
            transform: translateY(-2px);
        }
        
        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            font-size: 18px;
            color: #667eea;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 15px 20px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column-reverse;
                gap: 15px;
            }
            
            .dashboard-nav {
                padding: 0 20px;
            }
            
            .dashboard-main {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .section-content {
                padding: 20px;
            }
        }
        
        /* Welcome Animation */
        .welcome-animation {
            opacity: 0;
            transform: translateY(20px);
            animation: welcomeSlideIn 0.6s ease-out forwards;
        }
        
        @keyframes welcomeSlideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Success Messages */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message .icon {
            font-size: 20px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div>Cargando dashboard...</div>
    </div>

    <!-- Header -->
    <header class="dashboard-header">
        <div class="header-content">
            <div class="header-left">
                <div class="logo-section">
                    <div class="logo">📊</div>
                    <div class="brand-text">
                        <h1>MapVision Analytics</h1>
                        <p>Panel de Control Inteligente</p>
                    </div>
                </div>
            </div>
            
            <div class="header-right">
                <div class="user-info">
                    <div class="user-name">Cargando...</div>
                    <div class="user-role">...</div>
                </div>
                
                <div class="header-actions">
                    <a href="#" class="btn-header" onclick="showChangePasswordModal()">
                        🔑 Cambiar Contraseña
                    </a>
                    <a href="admin.html" class="btn-header admin-only" style="display: none;">
                        ⚙️ Administración
                    </a>
                    <a href="#" class="btn-header btn-logout" onclick="logout()">
                        🚪 Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="dashboard-nav">
        <div class="nav-container">
            <a href="#" class="nav-item active" onclick="showSection('overview', this)">
                📊 Resumen
            </a>
            <a href="#" class="nav-item" onclick="showSection('maps', this)">
                🗺️ Mapas
            </a>
            <a href="#" class="nav-item personal-plus" onclick="showSection('analytics', this)">
                📈 Análisis Avanzado
            </a>
            <a href="#" class="nav-item empresa-plus" onclick="showSection('team', this)">
                👥 Equipo
            </a>
            <a href="#" class="nav-item admin-only" onclick="showSection('admin', this)">
                🔧 Administración
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="dashboard-main">
        <!-- Welcome Message -->
        <div id="welcomeMessage" class="success-message welcome-animation" style="display: none;">
            <span class="icon">🎉</span>
            <span>¡Bienvenido a MapVision Analytics! Tu dashboard está listo para usar.</span>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🗺️</span>
                <h2 class="stat-value" id="statMaps">12</h2>
                <p class="stat-label">Mapas Creados</p>
                <span class="stat-change positive">+3 este mes</span>
            </div>
            
            <div class="stat-card">
                <span class="stat-icon">📊</span>
                <h2 class="stat-value" id="statDataPoints">3,247</h2>
                <p class="stat-label">Puntos de Datos</p>
                <span class="stat-change positive">+247 nuevos</span>
            </div>
            
            <div class="stat-card">
                <span class="stat-icon">🤖</span>
                <h2 class="stat-value" id="statAnalysis">8</h2>
                <p class="stat-label">Análisis IA Completados</p>
                <span class="stat-change positive">+2 hoy</span>
            </div>
            
            <div class="stat-card">
                <span class="stat-icon">⏱️</span>
                <h2 class="stat-value" id="statLastUpdate">24h</h2>
                <p class="stat-label">Última Actualización</p>
                <span class="stat-change">En tiempo real</span>
            </div>
        </div>

        <!-- Sección Resumen -->
        <div id="overview" class="section">
            <div class="section-container">
                <div class="section-header">
                    <h2 class="section-title">📊 Resumen de Actividad</h2>
                    <p class="section-subtitle">Vista general de tus mapas y análisis</p>
                </div>
                <div class="section-content">
                    <div id="overviewChart" style="height: 400px;"></div>
                </div>
            </div>
        </div>

        <!-- Sección Mapas -->
        <div id="maps" class="section" style="display: none;">
            <div class="section-container">
                <div class="section-header">
                    <h2 class="section-title">🗺️ Mis Mapas</h2>
                    <p class="section-subtitle">Gestiona y visualiza tus mapas de desviaciones</p>
                </div>
                <div class="section-content">
                    <div id="mapsGrid" style="height: 600px;"></div>
                </div>
            </div>
        </div>

        <!-- Sección Análisis Avanzado (Personal+) -->
        <div id="analytics" class="section" style="display: none;">
            <div class="trial-only feature-restricted">
                <h3>🔒 Análisis Avanzado con IA</h3>
                <p>
                    Desbloquea análisis predictivos, correlaciones automáticas y insights 
                    profundos con nuestro motor de IA. Disponible para usuarios Personal y superiores.
                </p>
                <button class="upgrade-button" onclick="showUpgradeModal()">
                    Actualizar a Personal
                </button>
            </div>
            
            <div class="personal-plus section-container">
                <div class="section-header">
                    <h2 class="section-title">📈 Análisis Avanzado</h2>
                    <p class="section-subtitle">Insights profundos con inteligencia artificial</p>
                </div>
                <div class="section-content">
                    <div id="analyticsContent" style="height: 500px;"></div>
                </div>
            </div>
        </div>

        <!-- Sección Equipo (Empresa+) -->
        <div id="team" class="section" style="display: none;">
            <div class="trial-only personal-only feature-restricted">
                <h3>👥 Gestión de Equipo</h3>
                <p>
                    Colabora con tu equipo, comparte mapas, asigna permisos y gestiona 
                    proyectos conjuntos. Disponible para usuarios Empresa y superiores.
                </p>
                <button class="upgrade-button" onclick="showUpgradeModal()">
                    Actualizar a Empresa
                </button>
            </div>
            
            <div class="empresa-plus section-container">
                <div class="section-header">
                    <h2 class="section-title">👥 Mi Equipo</h2>
                    <p class="section-subtitle">Colaboración y gestión de proyectos</p>
                </div>
                <div class="section-content">
                    <div id="teamContent" style="height: 500px;"></div>
                </div>
            </div>
        </div>

        <!-- Sección Administración (Solo Admin) -->
        <div id="admin" class="section admin-only" style="display: none;">
            <div class="section-container">
                <div class="section-header">
                    <h2 class="section-title">🔧 Panel de Administración</h2>
                    <p class="section-subtitle">Gestión completa del sistema</p>
                </div>
                <div class="section-content">
                    <div style="text-align: center; padding: 40px;">
                        <h3 style="margin-bottom: 20px;">Panel Administrativo Completo</h3>
                        <p style="margin-bottom: 30px; color: #6c757d;">
                            Accede al panel completo de administración para gestionar usuarios, 
                            ver estadísticas del sistema y configurar la plataforma.
                        </p>
                        <a href="admin.html" class="upgrade-button" style="text-decoration: none;">
                            Ir al Panel de Administración
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Webix JS -->
    <script src="https://cdn.webix.com/edge/webix.js"></script>
    
    <!-- Auth Manager -->
    <script src="js/auth-check.js"></script>

    <script>
        // Variables globales
        let currentSection = 'overview';
        let dashboardInitialized = false;
        
        // Inicializar dashboard
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('📊 Inicializando Dashboard...');
            
            // Verificar autenticación requerida
            if (!requireAuth()) {
                return; // Usuario será redirigido
            }
            
            // Esperar a que AuthManager esté listo
            await waitForAuth();
            
            // Verificar acceso al dashboard
            const user = getCurrentUser();
            if (!user) {
                window.location.href = 'index.html';
                return;
            }
            
            // Inicializar componentes
            await initializeDashboard(user);
            
            // Ocultar loading
            hideLoading();
            
            console.log('✅ Dashboard inicializado correctamente');
        });
        
        /**
         * Esperar a que AuthManager esté listo
         */
        async function waitForAuth() {
            return new Promise((resolve) => {
                const checkAuth = () => {
                    if (authManager && authManager.isAuthenticated()) {
                        resolve();
                    } else {
                        setTimeout(checkAuth, 100);
                    }
                };
                checkAuth();
            });
        }
        
        /**
         * Inicializar dashboard
         */
        async function initializeDashboard(user) {
            try {
                // Actualizar información del usuario
                updateUserInterface(user);
                
                // Mostrar mensaje de bienvenida para nuevos usuarios
                showWelcomeMessage(user);
                
                // Inicializar componentes Webix
                initializeWebixComponents(user);
                
                // Actualizar estadísticas
                await updateStatistics();
                
                // Marcar como inicializado
                dashboardInitialized = true;
                
            } catch (error) {
                console.error('Error inicializando dashboard:', error);
                showErrorMessage('Error cargando el dashboard. Por favor recarga la página.');
            }
        }
        
        /**
         * Actualizar interfaz de usuario
         */
        function updateUserInterface(user) {
            // Actualizar información del usuario
            const userNameEl = document.querySelector('.user-name');
            const userRoleEl = document.querySelector('.user-role');
            
            if (userNameEl) {
                userNameEl.textContent = `${user.nombre} ${user.apellido}`;
            }
            
            if (userRoleEl) {
                userRoleEl.textContent = user.rol;
            }
            
            // Mostrar/ocultar elementos según rol
            toggleFeaturesByRole(user.rol);
        }
        
        /**
         * Mostrar/ocultar funcionalidades por rol
         */
        function toggleFeaturesByRole(role) {
            const features = {
                'trial-only': ['Trial'],
                'personal-only': ['Personal'],
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
         * Mostrar mensaje de bienvenida
         */
        function showWelcomeMessage(user) {
            // Mostrar solo para nuevos usuarios o en primera visita
            const hasSeenWelcome = localStorage.getItem('mv_dashboard_welcome');
            
            if (!hasSeenWelcome) {
                const welcomeEl = document.getElementById('welcomeMessage');
                if (welcomeEl) {
                    welcomeEl.style.display = 'flex';
                    
                    // Ocultar después de 5 segundos
                    setTimeout(() => {
                        welcomeEl.style.display = 'none';
                        localStorage.setItem('mv_dashboard_welcome', 'true');
                    }, 5000);
                }
            }
        }
        
        /**
         * Inicializar componentes Webix
         */
        function initializeWebixComponents(user) {
            // Gráfico de resumen
            webix.ui({
                container: "overviewChart",
                view: "chart",
                type: "line",
                value: "#value#",
                label: "#month#",
                color: "#667eea",
                data: [
                    { month: "Enero", value: 20 },
                    { month: "Febrero", value: 55 },
                    { month: "Marzo", value: 40 },
                    { month: "Abril", value: 78 },
                    { month: "Mayo", value: 61 },
                    { month: "Junio", value: 95 }
                ],
                padding: { top: 20, bottom: 40, left: 60, right: 40 },
                xAxis: {
                    template: "#month#",
                    title: "Período"
                },
                yAxis: {
                    title: "Mapas Creados"
                }
            });
            
            // Grid de mapas
            webix.ui({
                container: "mapsGrid",
                view: "datatable",
                columns: [
                    { id: "name", header: "Nombre del Mapa", width: 250, sort: "string" },
                    { id: "type", header: "Tipo", width: 120 },
                    { id: "created", header: "Creado", width: 150, sort: "date" },
                    { id: "status", header: "Estado", width: 100, template: function(obj) {
                        const statusClass = obj.status === 'Activo' ? 'positive' : 'negative';
                        return `<span class="stat-change ${statusClass}">${obj.status}</span>`;
                    }},
                    { id: "actions", header: "Acciones", width: 200, template: function(obj) {
                        return `
                            <button class="btn-header" onclick="editMap('${obj.id}')" style="margin-right: 8px;">Editar</button>
                            <button class="btn-header" onclick="viewMap('${obj.id}')">Ver</button>
                        `;
                    }}
                ],
                data: [
                    { id: 1, name: "Análisis Regional Q1", type: "Heatmap", created: "2025-01-15", status: "Activo" },
                    { id: 2, name: "Ventas por Producto", type: "Desviaciones", created: "2025-01-20", status: "Borrador" },
                    { id: 3, name: "Cumplimiento Norte", type: "Comparativo", created: "2025-02-01", status: "Activo" },
                    { id: 4, name: "Proyecciones Q2", type: "Predictivo", created: "2025-02-05", status: "Procesando" }
                ],
                autoheight: false,
                scroll: "y",
                select: true
            });
            
            // Contenido de análisis (solo para Personal+)
            if (canAccess('advanced_analytics')) {
                webix.ui({
                    container: "analyticsContent",
                    view: "chart",
                    type: "bar",
                    value: "#value#",
                    label: "#region#",
                    color: "#764ba2",
                    data: [
                        { region: "Norte", value: 142 },
                        { region: "Centro", value: 198 },
                        { region: "Sur", value: 175 },
                        { region: "Oriente", value: 89 },
                        { region: "Poniente", value: 156 }
                    ],
                    padding: { top: 20, bottom: 40, left: 80, right: 40 }
                });
            }
            
            // Contenido de equipo (solo para Empresa+)
            if (canAccess('team_management')) {
                webix.ui({
                    container: "teamContent",
                    view: "datatable",
                    columns: [
                        { id: "name", header: "Nombre", width: 180 },
                        { id: "email", header: "Email", width: 220 },
                        { id: "role", header: "Rol", width: 120 },
                        { id: "last_access", header: "Último Acceso", width: 150 },
                        { id: "status", header: "Estado", width: 100, template: function(obj) {
                            const statusClass = obj.status === 'Activo' ? 'positive' : 'negative';
                            return `<span class="stat-change ${statusClass}">${obj.status}</span>`;
                        }}
                    ],
                    data: [
                        { id: 1, name: "Juan Pérez", email: "juan@empresa.com", role: "Analista", last_access: "2025-02-05", status: "Activo" },
                        { id: 2, name: "María García", email: "maria@empresa.com", role: "Viewer", last_access: "2025-02-06", status: "Activo" },
                        { id: 3, name: "Carlos López", email: "carlos@empresa.com", role: "Editor", last_access: "2025-02-04", status: "Inactivo" }
                    ],
                    autoheight: false,
                    scroll: "y"
                });
            }
        }
        
        /**
         * Actualizar estadísticas
         */
        async function updateStatistics() {
            try {
                // Simular datos dinámicos (en una app real, estos vendrían de la API)
                const stats = {
                    maps: Math.floor(Math.random() * 20) + 10,
                    dataPoints: Math.floor(Math.random() * 5000) + 2000,
                    analysis: Math.floor(Math.random() * 15) + 5,
                    lastUpdate: '24h'
                };
                
                // Actualizar elementos
                document.getElementById('statMaps').textContent = stats.maps;
                document.getElementById('statDataPoints').textContent = stats.dataPoints.toLocaleString();
                document.getElementById('statAnalysis').textContent = stats.analysis;
                document.getElementById('statLastUpdate').textContent = stats.lastUpdate;
                
            } catch (error) {
                console.error('Error actualizando estadísticas:', error);
            }
        }
        
        /**
         * Cambiar sección activa
         */
        function showSection(sectionName, element) {
            // Ocultar todas las secciones
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Mostrar sección seleccionada
            const targetSection = document.getElementById(sectionName);
            if (targetSection) {
                targetSection.style.display = 'block';
            }
            
            // Actualizar navegación activa
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            if (element) {
                element.classList.add('active');
            }
            
            currentSection = sectionName;
            
            // Actualizar contenido específico de la sección
            updateSectionContent(sectionName);
        }
        
        /**
         * Actualizar contenido de sección
         */
        function updateSectionContent(sectionName) {
            switch (sectionName) {
                case 'analytics':
                    if (canAccess('advanced_analytics')) {
                        // Actualizar datos de análisis
                        console.log('Actualizando análisis avanzado');
                    }
                    break;
                case 'team':
                    if (canAccess('team_management')) {
                        // Actualizar datos del equipo
                        console.log('Actualizando datos del equipo');
                    }
                    break;
            }
        }
        
        /**
         * Mostrar modal de actualización de plan
         */
        function showUpgradeModal() {
            webix.ui({
                view: "window",
                id: "upgradeModal",
                width: 600,
                height: 500,
                position: "center",
                modal: true,
                head: "🚀 Actualizar Plan",
                body: {
                    rows: [
                        {
                            template: `
                                <div style="padding: 40px; text-align: center;">
                                    <h2 style="color: #667eea; margin-bottom: 20px;">Desbloquea todo el potencial</h2>
                                    <p style="color: #6c757d; margin-bottom: 40px; line-height: 1.6;">
                                        Accede a análisis avanzados con IA, gestión de equipo, 
                                        exportaciones ilimitadas y soporte prioritario.
                                    </p>
                                    
                                    <div style="display: flex; gap: 30px; justify-content: center; margin-bottom: 40px;">
                                        <div style="padding: 30px; border: 2px solid #667eea; border-radius: 16px; max-width: 200px;">
                                            <h3 style="color: #667eea; margin-bottom: 10px;">Personal</h3>
                                            <div style="font-size: 32px; font-weight: bold; color: #495057; margin-bottom: 10px;">$29<span style="font-size: 16px;">/mes</span></div>
                                            <ul style="text-align: left; color: #6c757d; line-height: 1.8;">
                                                <li>✅ Análisis avanzados</li>
                                                <li>✅ Exportación de datos</li>
                                                <li>✅ Soporte por email</li>
                                            </ul>
                                        </div>
                                        
                                        <div style="padding: 30px; border: 2px solid #28a745; border-radius: 16px; max-width: 200px; background: #f8fff9;">
                                            <h3 style="color: #28a745; margin-bottom: 10px;">Empresa</h3>
                                            <div style="font-size: 32px; font-weight: bold; color: #495057; margin-bottom: 10px;">$99<span style="font-size: 16px;">/mes</span></div>
                                            <ul style="text-align: left; color: #6c757d; line-height: 1.8;">
                                                <li>✅ Todo lo anterior</li>
                                                <li>✅ Gestión de equipo</li>
                                                <li>✅ Integraciones API</li>
                                                <li>✅ Soporte prioritario</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            `,
                            borderless: true
                        },
                        {
                            cols: [
                                {
                                    view: "button",
                                    value: "Tal vez después",
                                    click: () => webix.$$('upgradeModal').close()
                                },
                                {},
                                {
                                    view: "button",
                                    value: "Contactar Ventas",
                                    css: "webix_primary",
                                    click: () => {
                                        window.open('mailto:sales@mapvision.com?subject=Consulta sobre planes');
                                        webix.$$('upgradeModal').close();
                                    }
                                }
                            ]
                        }
                    ]
                }
            });
            
            webix.$$('upgradeModal').show();
        }
        
        /**
         * Mostrar modal de cambio de contraseña
         */
        function showChangePasswordModal() {
            if (!webix.$$('changePasswordModal')) {
                webix.ui({
                    view: "window",
                    id: "changePasswordModal",
                    width: 450,
                    height: 400,
                    position: "center",
                    modal: true,
                    head: "🔑 Cambiar Contraseña",
                    body: {
                        view: "form",
                        id: "changePasswordForm",
                        elements: [
                            {
                                template: '<div id="changePasswordMessage" class="alert"></div>',
                                height: 50,
                                borderless: true
                            },
                            {
                                view: "text",
                                name: "current_password",
                                type: "password",
                                label: "Contraseña Actual",
                                labelWidth: 140,
                                required: true,
                                placeholder: "Tu contraseña actual"
                            },
                            {
                                view: "text",
                                name: "new_password",
                                type: "password",
                                label: "Nueva Contraseña",
                                labelWidth: 140,
                                required: true,
                                placeholder: "Mínimo 8 caracteres"
                            },
                            {
                                view: "text",
                                name: "confirm_password",
                                type: "password",
                                label: "Confirmar Nueva",
                                labelWidth: 140,
                                required: true,
                                placeholder: "Repite la nueva contraseña"
                            },
                            {
                                margin: 20,
                                cols: [
                                    {
                                        view: "button",
                                        value: "Cancelar",
                                        click: () => webix.$$('changePasswordModal').close()
                                    },
                                    {
                                        view: "button",
                                        value: "Cambiar Contraseña",
                                        css: "webix_primary",
                                        click: handleChangePassword
                                    }
                                ]
                            }
                        ]
                    }
                });
            }
            
            webix.$$('changePasswordModal').show();
        }
        
        /**
         * Manejar cambio de contraseña
         */
        async function handleChangePassword() {
            const form = webix.$$('changePasswordForm');
            if (!form.validate()) return;

            const values = form.getValues();

            if (values.new_password !== values.confirm_password) {
                showChangePasswordMessage('Las contraseñas no coinciden', 'error');
                return;
            }

            if (values.new_password.length < 8) {
                showChangePasswordMessage('La nueva contraseña debe tener al menos 8 caracteres', 'error');
                return;
            }

            showChangePasswordMessage('Cambiando contraseña...', 'info');

            const result = await authManager.changePassword(values.current_password, values.new_password);

            if (result.success) {
                showChangePasswordMessage('Contraseña actualizada exitosamente', 'success');
                setTimeout(() => {
                    webix.$$('changePasswordModal').close();
                }, 2000);
            } else {
                showChangePasswordMessage(result.message, 'error');
            }
        }
        
        /**
         * Mostrar mensaje en modal de cambio de contraseña
         */
        function showChangePasswordMessage(message, type) {
            const container = document.getElementById('changePasswordMessage');
            if (container) {
                container.className = `alert alert-${type} show`;
                container.textContent = message;
            }
        }
        
        /**
         * Acciones de mapas
         */
        function editMap(mapId) {
            console.log('Editando mapa:', mapId);
            // Implementar edición de mapa
            webix.message(`Editando mapa ${mapId}`);
        }
        
        function viewMap(mapId) {
            console.log('Visualizando mapa:', mapId);
            // Implementar visualización de mapa
            webix.message(`Visualizando mapa ${mapId}`);
        }
        
        /**
         * Mostrar mensaje de error
         */
        function showErrorMessage(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error show';
            errorDiv.textContent = message;
            errorDiv.style.position = 'fixed';
            errorDiv.style.top = '20px';
            errorDiv.style.right = '20px';
            errorDiv.style.zIndex = '10001';
            
            document.body.appendChild(errorDiv);
            
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 5000);
        }
        
        /**
         * Ocultar loading
         */
        function hideLoading() {
            const loadingEl = document.getElementById('loadingOverlay');
            if (loadingEl) {
                loadingEl.style.opacity = '0';
                setTimeout(() => {
                    loadingEl.style.display = 'none';
                }, 300);
            }
        }
        
        // Actualizar estadísticas cada 5 minutos
        setInterval(() => {
            if (dashboardInitialized && document.visibilityState === 'visible') {
                updateStatistics();
            }
        }, 300000);
        
        // Hacer funciones globales
        window.showSection = showSection;
        window.showUpgradeModal = showUpgradeModal;
        window.showChangePasswordModal = showChangePasswordModal;
        window.editMap = editMap;
        window.viewMap = viewMap;
    </script>
</body>
</html>