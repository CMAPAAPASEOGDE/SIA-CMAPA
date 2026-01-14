// Menú hamburguesa
const toggle = document.getElementById('menu-toggle');
const dropdown = document.getElementById('dropdown-menu');

if (toggle && dropdown) {
    toggle.addEventListener('click', () => {
        dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
    });
    
    window.addEventListener('click', (e) => {
        if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

// Menú de usuario
const userToggle = document.getElementById('user-toggle');
const userDropdown = document.getElementById('user-dropdown');

if (userToggle && userDropdown) {
    userToggle.addEventListener('click', () => {
        userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
    });
    
    window.addEventListener('click', (e) => {
        if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.style.display = 'none';
        }
    });
}