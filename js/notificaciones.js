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
    if (alertaElement) {
        alertaElement.classList.add('leida');
        
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
    const alertas = document.querySelectorAll('.alerta-item');
    const idsLeidos = [];
    
    alertas.forEach(alerta => {
        alerta.classList.add('leida');
        const id = parseInt(alerta.getAttribute('data-id'));
        if (id) idsLeidos.push(id);
    });
    
    // Guardar en localStorage
    localStorage.setItem('alertasInventario', JSON.stringify(idsLeidos));
    actualizarContador();
}

// Cargar alertas leídas al iniciar
function cargarAlertasLeidas() {
    const alertasLeidas = JSON.parse(localStorage.getItem('alertasInventario') || '[]');
    
    alertasLeidas.forEach(id => {
        const alerta = document.querySelector(`.alerta-item[data-id="${id}"]`);
        if (alerta) {
            alerta.classList.add('leida');
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
}