/**
 * MapVision Analytics - JavaScript del Panel Administrativo
 * Archivo: admin.js
 * Descripción: Lógica JavaScript para el panel de administración
 */

// Variables globales
let currentAdminSection = 'overview';
let adminInitialized = false;
let refreshInterval = null;

// Configuración
const AdminConfig = {
    refreshInterval: 30000, // 30 segundos
    notificationDuration: 5000, // 5 segundos
    autoRedirectDelay: 3000 // 3 segundos
};

/**
 * Inicialización principal
 */
document.addEventListener('DOMContentLoaded', async function() {
    console.log('🛡️ Inicializando Panel Administrativo...');
    
    try {
        // Verificar autenticación y permisos de administrador
        if (!requireAuth()) {
            return;
        }
        
        const user = getCurrentUser();
        if (!user || user.rol !== 'Administrador') {
            window.location.href = 'dashboard.html';
            return;
        }
        
        // Inicializar componentes
        await initializeAdminPanel(user);
        
        // Ocultar loading
        hideLoading();
        
        console.log('✅ Panel Administrativo inicializado correctamente');
        
    } catch (error) {
        console.error('❌ Error inicializando panel administrativo:', error);
        showAdminNotification('Error crítico inicializando el panel', 'error');
    }
});

/**
 * Inicializar panel administrativo
 */
async function initializeAdminPanel(user) {
    try {
        // Actualizar información del usuario
        updateAdminUserInterface(user);
        
        // Cargar datos iniciales
        await loadAdminData();
        
        // Inicializar componentes Webix
        initializeAdminComponents();
        
        // Configurar actualización automática
        startAutoRefresh();
        
        // Configurar event listeners
        setupEventListeners();
        
        adminInitialized = true;
        
    } catch (error) {
        console.error('Error inicializando panel administrativo:', error);
        showAdminNotification('Error cargando el panel administrativo', 'error');
        throw error;
    }
}

/**
 * Actualizar interfaz de usuario administrativo
 */
function updateAdminUserInterface(user) {
    const userNameEl = document.querySelector('.admin-name');
    if (userNameEl) {
        userNameEl.textContent = `${user.nombre} ${user.apellido}`;
    }
}

/**
 * Cargar datos administrativos
 */
async function loadAdminData() {
    try {
        // Cargar estadísticas generales
        const statsData = await fetchAdminData('get_general_stats');
        if (statsData.success) {
            updateGeneralStats(statsData.data);
        }
        
        // Cargar alertas del sistema
        const alertsData = await fetchAdminData('get_system_alerts');
        if (alertsData.success) {
            updateSystemAlerts(alertsData.data);
        }
        
    } catch (error) {
        console.error('Error cargando datos administrativos:', error);
        showAdminNotification('Error cargando datos del sistema', 'warning');
    }
}

/**
 * Función helper para fetch de datos admin
 */
async function fetchAdminData(action, data = null) {
    const options = {
        method: data ? 'POST' : 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    const url = data ? `api/Admin.php?action=${action}` : `api/Admin.php?action=${action}`;
    const response = await fetch(url, options);
    
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    return await response.json();
}

/**
 * Actualizar estadísticas generales
 */
function updateGeneralStats(stats) {
    // Actualizar elementos del DOM
    updateElement('totalUsers', stats.TOTAL_USUARIOS || 0);
    updateElement('onlineUsers', stats.USUARIOS_CONECTADOS || 0);
    updateElement('activeToday', stats.ACTIVOS_HOY || 0);
    updateElement('systemAlertCount', '0');
    
    // Actualizar tendencias
    const newToday = stats.NUEVOS_HOY || 0;
    updateElement('usersTrend', `+${newToday} nuevos hoy`);
    
    console.log('📊 Estadísticas actualizadas:', stats);
}

/**
 * Helper para actualizar elementos del DOM
 */
function updateElement(id, content) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = content;
    }
}

/**
 * Actualizar alertas del sistema
 */
function updateSystemAlerts(alerts) {
    const alertsContainer = document.getElementById('systemAlerts');
    const alertsContent = document.getElementById('alertsContent');
    
    if (!alerts || alerts.length === 0) {
        if (alertsContainer) alertsContainer.innerHTML = '';
        if (alertsContent) {
            alertsContent.innerHTML = createNoAlertsMessage();
        }
        updateElement('systemAlertCount', '0');
        return;
    }
    
    // Mostrar alertas en el header (máximo 3)
    if (alertsContainer) {
        alertsContainer.innerHTML = alerts.slice(0, 3)
            .map(alert => createAlertHTML(alert))
            .join('');
    }
    
    // Mostrar todas las alertas en la sección de alertas
    if (alertsContent) {
        alertsContent.innerHTML = alerts
            .map(alert => createAlertHTML(alert))
            .join('');
    }
    
    // Actualizar contador
    updateElement('systemAlertCount', alerts.length.toString());
    
    console.log('🚨 Alertas actualizadas:', alerts.length);
}

/**
 * Crear HTML para alertas
 */
function createAlertHTML(alert) {
    const icon = getAlertIcon(alert.type);
    return `
        <div class="alert alert-${alert.type}">
            <div class="alert-icon">${icon}</div>
            <div class="alert-content">
                <div class="alert-title">${alert.title}</div>
                <div class="alert-message">${alert.message}</div>
            </div>
        </div>
    `;
}

/**
 * Crear mensaje de no alertas
 */
function createNoAlertsMessage() {
    return `
        <div class="text-center">
            <h3>✅ No hay alertas activas</h3>
            <p>El sistema está funcionando correctamente.</p>
        </div>
    `;
}

/**
 * Obtener icono según tipo de alerta
 */
function getAlertIcon(type) {
    const icons = {
        'warning': '⚠️',
        'error': '🚨',
        'info': 'ℹ️',
        'success': '✅'
    };
    return icons[type] || 'ℹ️';
}

/**
 * Inicializar componentes Webix
 */
function initializeAdminComponents() {
    try {
        // Gráfico de resumen
        initializeOverviewChart();
        
        // Grid de usuarios
        initializeUsersGrid();
        
        console.log('📊 Componentes Webix inicializados');
        
    } catch (error) {
        console.error('Error inicializando componentes Webix:', error);
        showAdminNotification('Error inicializando gráficos', 'warning');
    }
}

/**
 * Inicializar gráfico de resumen
 */
function initializeOverviewChart() {
    webix.ui({
        container: "overviewChart",
        view: "chart",
        type: "line",
        value: "#value#",
        label: "#day#",
        color: "#dc3545",
        data: [
            { day: "Lun", value: 45 },
            { day: "Mar", value: 52 },
            { day: "Mié", value: 38 },
            { day: "Jue", value: 67 },
            { day: "Vie", value: 73 },
            { day: "Sáb", value: 41 },
            { day: "Dom", value: 29 }
        ],
        padding: { top: 20, bottom: 40, left: 60, right: 40 },
        xAxis: {
            template: "#day#",
            title: "Días de la semana"
        },
        yAxis: {
            title: "Usuarios activos"
        }
    });
}

/**
 * Inicializar grid de usuarios
 */
function initializeUsersGrid() {
    webix.ui({
        container: "usersGrid",
        view: "datatable",
        columns: [
            { id: "email", header: "Email", width: 200, sort: "string" },
            { id: "nombre", header: "Nombre", width: 150 },
            { id: "apellido", header: "Apellido", width: 150 },
            { id: "rol", header: "Rol", width: 120 },
            { id: "empresa", header: "Empresa", width: 180 },
            { 
                id: "estado_display", 
                header: "Estado", 
                width: 120, 
                template: function(obj) {
                    return createStatusBadge(obj.estado_display);
                }
            },
            { id: "fecha_creacion", header: "Registrado", width: 150 },
            { 
                id: "actions", 
                header: "Acciones", 
                width: 200, 
                template: function(obj) {
                    return createActionButtons(obj);
                }
            }
        ],
        data: [],
        autoheight: false,
        scroll: "y",
        select: true,
        resizeColumn: true
    });
    
    // Cargar datos iniciales
    loadUsersData();
}

/**
 * Crear badge de estado
 */
function createStatusBadge(status) {
    const colors = {
        'Conectado': 'background: #d4edda; color: #155724;',
        'Desconectado': 'background: #f8f9fa; color: #6c757d;',
        'Bloqueado': 'background: #f8d7da; color: #721c24;',
        'Deshabilitado': 'background: #f8d7da; color: #721c24;'
    };
    const style = colors[status] || 'background: #e2e3e5; color: #383d41;';
    return `<span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; ${style}">${status}</span>`;
}

/**
 * Crear botones de acción
 */
function createActionButtons(obj) {
    const toggleText = obj.activo ? 'Deshabilitar' : 'Habilitar';
    const toggleClass = obj.activo ? 'btn-danger' : 'btn-success';
    return `
        <button class="btn-admin ${toggleClass}" 
                onclick="toggleUserStatus('${obj.email}', ${!obj.activo})" 
                style="margin-right: 5px; padding: 4px 8px; font-size: 12px;">
            ${toggleText}
        </button>
        <button class="btn-admin" 
                onclick="viewUserDetails('${obj.email}')" 
                style="padding: 4px 8px; font-size: 12px;">
            Ver
        </button>
    `;
}

/**
 * Cargar datos de usuarios
 */
async function loadUsersData() {
    try {
        const data = await fetchAdminData('get_users_status');
        
        if (data.success) {
            const usersGrid = webix.$$('usersGrid');
            if (usersGrid) {
                usersGrid.clearAll();
                usersGrid.parse(data.data);
            }
            console.log('👥 Datos de usuarios cargados:', data.data.length);
        }
    } catch (error) {
        console.error('Error cargando usuarios:', error);
        showAdminNotification('Error cargando lista de usuarios', 'error');
    }
}

/**
 * Cambiar sección activa
 */
function showAdminSection(sectionName, element) {
    // Ocultar todas las secciones
    document.querySelectorAll('.admin-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Mostrar sección seleccionada
    const targetSection = document.getElementById(sectionName + 'Section');
    if (targetSection) {
        targetSection.style.display = 'block';
    }
    
    // Actualizar navegación activa
    document.querySelectorAll('.admin-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    if (element) {
        element.classList.add('active');
    }
    
    currentAdminSection = sectionName;
    
    // Cargar contenido específico según la sección
    loadSectionContent(sectionName);
    
    console.log('📋 Sección cambiada a:', sectionName);
}

/**
 * Cargar contenido específico de sección
 */
function loadSectionContent(sectionName) {
    switch (sectionName) {
        case 'users':
            loadUsersData();
            break;
        case 'activity':
            loadActivityData();
            break;
        case 'logs':
            loadLogsData();
            break;
        default:
            console.log('Sección sin contenido específico:', sectionName);
    }
}

/**
 * Cargar datos de actividad (placeholder)
 */
async function loadActivityData() {
    console.log('📊 Cargando datos de actividad...');
    // TODO: Implementar carga de datos de actividad
}

/**
 * Cargar logs (placeholder)
 */
async function loadLogsData() {
    console.log('📋 Cargando logs...');
    // TODO: Implementar carga de logs
}

/**
 * Activar/Desactivar usuario
 */
async function toggleUserStatus(email, active) {
    try {
        showAdminNotification('Actualizando estado de usuario...', 'info');
        
        const data = await fetchAdminData('toggle_user_status', {
            user_email: email,
            active: active
        });
        
        if (data.success) {
            showAdminNotification('Estado de usuario actualizado', 'success');
            loadUsersData(); // Recargar grid
        } else {
            showAdminNotification(data.message || 'Error actualizando usuario', 'error');
        }
    } catch (error) {
        console.error('Error cambiando estado de usuario:', error);
        showAdminNotification('Error cambiando estado de usuario', 'error');
    }
}

/**
 * Ver detalles de usuario
 */
function viewUserDetails(email) {
    console.log('👤 Ver detalles de usuario:', email);
    showAdminNotification('Función de detalles en desarrollo', 'info');
    // TODO: Implementar modal de detalles de usuario
}

/**
 * Realizar mantenimiento
 */
async function performMaintenance(type) {
    try {
        showAdminNotification('Ejecutando mantenimiento...', 'info');
        
        const data = await fetchAdminData('perform_maintenance', { type: type });
        
        if (data.success) {
            showAdminNotification('Mantenimiento completado', 'success');
            
            // Mostrar resultados
            const resultsContainer = document.getElementById('maintenanceResults');
            if (resultsContainer) {
                resultsContainer.innerHTML = createMaintenanceResultsHTML(data.results);
            }
        } else {
            showAdminNotification(data.message || 'Error en mantenimiento', 'error');
        }
    } catch (error) {
        console.error('Error en mantenimiento:', error);
        showAdminNotification('Error ejecutando mantenimiento', 'error');
    }
}

/**
 * Crear HTML para resultados de mantenimiento
 */
function createMaintenanceResultsHTML(results) {
    return `
        <div class="alert alert-info">
            <div class="alert-icon">✅</div>
            <div class="alert-content">
                <div class="alert-title">Mantenimiento Completado</div>
                <div class="alert-message">
                    <pre>${JSON.stringify(results, null, 2)}</pre>
                </div>
            </div>
        </div>
    `;
}

/**
 * Exportar usuarios
 */
async function exportUsers() {
    try {
        showAdminNotification('Preparando exportación...', 'info');
        
        const response = await fetch('api/Admin.php?action=export_users&format=csv');
        
        if (response.ok) {
            const blob = await response.blob();
            downloadBlob(blob, `usuarios_${getCurrentTimestamp()}.csv`);
            showAdminNotification('Usuarios exportados correctamente', 'success');
        } else {
            showAdminNotification('Error exportando usuarios', 'error');
        }
    } catch (error) {
        console.error('Error exportando usuarios:', error);
        showAdminNotification('Error exportando usuarios', 'error');
    }
}

/**
 * Descargar blob como archivo
 */
function downloadBlob(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

/**
 * Obtener timestamp actual formateado
 */
function getCurrentTimestamp() {
    return new Date().toISOString().split('T')[0];
}

/**
 * Actualizar vista general
 */
function refreshOverview() {
    loadAdminData();
    showAdminNotification('Datos actualizados', 'success');
}

/**
 * Iniciar actualización automática
 */
function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        if (document.visibilityState === 'visible' && adminInitialized) {
            loadAdminData();
        }
    }, AdminConfig.refreshInterval);
    
    console.log('🔄 Auto-refresh iniciado cada', AdminConfig.refreshInterval / 1000, 'segundos');
}

/**
 * Detener actualización automática
 */
function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
        console.log('🔄 Auto-refresh detenido');
    }
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Limpiar al cerrar la página
    window.addEventListener('beforeunload', () => {
        stopAutoRefresh();
    });
    
    // Pausar refresh cuando la página no es visible
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            console.log('📱 Página oculta - pausando actualizaciones');
        } else {
            console.log('📱 Página visible - reanudando actualizaciones');
        }
    });
}

/**
 * Mostrar notificación administrativa
 */
function showAdminNotification(message, type = 'info', duration = AdminConfig.notificationDuration) {
    const notification = document.createElement('div');
    notification.className = `admin-notification admin-notification-${type}`;
    notification.textContent = message;
    
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

// Exportar funciones globales para uso en HTML
window.showAdminSection = showAdminSection;
window.toggleUserStatus = toggleUserStatus;
window.viewUserDetails = viewUserDetails;
window.performMaintenance = performMaintenance;
window.exportUsers = exportUsers;
window.refreshOverview = refreshOverview;