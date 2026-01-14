// Sistema de Notificaciones de Inventario
document.addEventListener('DOMContentLoaded', function() {
    // Toggle dropdown de notificaciones
    const notifToggle = document.getElementById('notif-toggle');
    const notifDropdown = document.getElementById('notif-dropdown');
    
    if (notifToggle && notifDropdown) {
        notifToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Cerrar otros dropdowns
            document.getElementById('user-dropdown').style.display = 'none';
            document.getElementById('dropdown-menu').style.display = 'none';
            
            // Toggle notificaciones
            notifDropdown.style.display = 
                notifDropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        // Cerrar al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!notifDropdown.contains(e.target) && !notifToggle.contains(e.target)) {
                notifDropdown.style.display = 'none';
            }
        });
    }
    
    // Cargar alertas leídas del localStorage
    cargarAlertasLeidas();
});

// Marcar una alerta como leída
function marcarComoLeido(idCodigo) {
    const alertaElement = document.querySelector(`.alerta-item[data-id="${idCodigo}"]`);
    const boton = alertaElement?.querySelector('.btn-marcar-leido');
    
    if (alertaElement && boton) {
        // Marcar como leída visualmente
        alertaElement.classList.add('leida');
        boton.innerHTML = '✓';
        boton.disabled = true;
        
        // Guardar en localStorage
        let alertasLeidas = JSON.parse(localStorage.getItem('alertasInventario') || '[]');
        if (!alertasLeidas.includes(idCodigo)) {
            alertasLeidas.push(idCodigo);
            localStorage.setItem('alertasInventario', JSON.stringify(alertasLeidas));
        }
        
        actualizarContador();
    }
}

// Marcar todas las alertas como leídas
function marcarTodasLeidas() {
    const alertas = document.querySelectorAll('.alerta-item:not(.leida)');
    const idsLeidos = [];
    
    alertas.forEach(alerta => {
        const id = parseInt(alerta.getAttribute('data-id'));
        const boton = alerta.querySelector('.btn-marcar-leido');
        
        alerta.classList.add('leida');
        if (boton) {
            boton.innerHTML = '✓';
            boton.disabled = true;
        }
        
        if (id) idsLeidos.push(id);
    });
    
    // Agregar a las existentes en localStorage
    let alertasLeidas = JSON.parse(localStorage.getItem('alertasInventario') || '[]');
    idsLeidos.forEach(id => {
        if (!alertasLeidas.includes(id)) {
            alertasLeidas.push(id);
        }
    });
    
    localStorage.setItem('alertasInventario', JSON.stringify(alertasLeidas));
    actualizarContador();
}

// Cargar alertas leídas al iniciar
function cargarAlertasLeidas() {
    const alertasLeidas = JSON.parse(localStorage.getItem('alertasInventario') || '[]');
    
    alertasLeidas.forEach(id => {
        const alerta = document.querySelector(`.alerta-item[data-id="${id}"]`);
        const boton = alerta?.querySelector('.btn-marcar-leido');
        
        if (alerta) {
            alerta.classList.add('leida');
            if (boton) {
                boton.innerHTML = '✓';
                boton.disabled = true;
            }
        }
    });
    
    actualizarContador();
}

// Actualizar contador de alertas
function actualizarContador() {
    const alertasNoLeidas = document.querySelectorAll('.alerta-item:not(.leida)').length;
    const badge = document.querySelector('.contador-badge');
    const bellImg = document.querySelector('.notif-icon');
    
    if (badge) {
        if (alertasNoLeidas > 0) {
            badge.textContent = alertasNoLeidas;
            badge.style.display = 'block';
            if (bellImg) bellImg.src = 'img/belldot.png';
        } else {
            badge.style.display = 'none';
            if (bellImg) bellImg.src = 'img/bell.png';
        }
    }
    
    // Si no hay alertas no leídas, mostrar mensaje opcional
    const contenedor = document.querySelector('.alertas-container');
    if (contenedor && alertasNoLeidas === 0) {
        const todasLeidas = document.querySelectorAll('.alerta-item').length;
        if (todasLeidas > 0) {
            // Opcional: agregar un pequeño indicador de que todas están leídas
            const header = document.querySelector('.notif-header .notif-title');
            if (header && !header.innerHTML.includes('(Todas leídas)')) {
                header.innerHTML += ' <span style="color: #28a745; font-size: 12px;">(Todas leídas)</span>';
            }
        }
    }
}

// Función para limpiar alertas leídas (opcional - puedes agregar un botón para esto)
function limpiarAlertasLeidas() {
    localStorage.removeItem('alertasInventario');
    location.reload();
}