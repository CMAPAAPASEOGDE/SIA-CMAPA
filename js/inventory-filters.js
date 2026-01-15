// Filtrado de la tabla de inventario
function filterTable() {
    const searchText = document.getElementById('search').value.toLowerCase();
    const linea = document.getElementById('linea').value;
    const tipo = document.getElementById('tipo').value;
    const estado = document.getElementById('estado').value;

    const table = document.getElementById('inventory-table');
    const rows = table.tBodies[0].rows;

    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].cells;
        const show =
            (searchText === '' ||
                cells[0].textContent.toLowerCase().includes(searchText) ||
                cells[1].textContent.toLowerCase().includes(searchText)) &&
            (linea === '' || cells[2].textContent === linea) &&
            (tipo === '' || cells[9].textContent === tipo) &&
            (estado === '' || cells[10].textContent.includes(estado));
        rows[i].style.display = show ? '' : 'none';
    }
}

// Inicializar filtros al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Agregar eventos de teclado para búsqueda
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filterTable();
            }
        });
    }
});