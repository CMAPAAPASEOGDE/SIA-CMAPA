<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Si no hay sesión activa, redirigir al login
    header("Location: index.php");
    exit();
}

// Verificar el rol del usuario
$idRol = (int)($_SESSION['rol'] ?? 0);
if (!in_array($idRol, [1, 2])) {
    header("Location: acceso_denegado.php");
    exit();
}

// Obtener proveedores para el dropdown
$serverName = "sqlserver-sia.database.windows.net";
$connectionOptions = array(
    "Database" => "db_sia",
    "Uid" => "cmapADMIN",
    "PWD" => "@siaADMN56*",
    "Encrypt" => true,
    "TrustServerCertificate" => false
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
$proveedores = [];

if ($conn !== false) {
    $sql = "SELECT idProveedor, razonSocial FROM Proveedores";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $proveedores[] = $row;
        }
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
}

// Obtener fecha actual en formato YYYY-MM-DD (formato de input date)
$fecha_actual = date('Y-m-d');
?>

<!DOCTYPE html>

<html>
<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    <title>SIA New Entry</title>
    <link rel="stylesheet" href="css/StyleNWET.css">
</head>

<body>
<header>
  <div class="brand">
    <img src="img/cmapa.png" class="logo" />
    <h1>SIA - CMAPA</h1>
  </div>
  <div class="header-right">
    <div class="notification-container">
      <button class="icon-btn" id="notif-toggle">
        <img src="img/bell.png" class="imgh3" alt="Notificaciones" />
      </button>
      <div class="notification-dropdown" id="notif-dropdown"></div>
    </div>
    <p> <?= $_SESSION['usuario'] ?> </p>
    <div class="user-menu-container">
      <button class="icon-btn" id="user-toggle">
        <img src="img/userB.png" class="imgh2" alt="Usuario" />
      </button>
      <div class="user-dropdown" id="user-dropdown">
        <p><strong>Usuario:</strong> <?= $_SESSION[ 'rol' ]?></p>
        <p><strong>Apodo:</strong> <?= htmlspecialchars($_SESSION['nombre'])?></p>
        <a href="passchng.php"><button class="user-option">CAMBIAR CONTRASEÑA</button></a>
      </div>
    </div>
    <!-- botón hamburguesa -->
    <div class="menu-container">
      <button class="icon-btn" id="menu-toggle">
        <img src="img/menu.png" alt="Menú" />
      </button>
      <div class="dropdown" id="dropdown-menu">
        <a href="homepage.php">Inicio</a>
        <a href="mnthclsr.php">Cierre de mes</a>
        <a href="admin.php">Menu de administador</a>
        <a href="about.php">Acerca de</a>
        <a href="help.php">Ayuda</a>
        <a href="logout.php">Cerrar Sesion</a>
      </div>
    </div>
</div>
</header>

<main class="entrada-container">
    <h2 class="entrada-title">ENTRADAS</h2>
    <div class="entrada-tabs">
        <a href="exstentry.php"><button class="tab new-bttn">ENTRADA EXISTENTE</button></a>
        <button class="tab">ENTRADA NUEVA</button>
    </div>

    <div class="success-message" id="success-message">
        ¡Producto registrado exitosamente! Redirigiendo...
    </div>

    <form class="entrada-form" id="entrada-form" method="POST" action="php/procesar_entrada.php">
        <div class="entrada-row">
            <div class="entrada-col">
                <label>ASIGNAR CÓDIGO <span style="color:red;">*</span></label>
                <input type="text" name="codigo" id="codigo" required pattern="[A-Z0-9]{1,20}" title="Solo letras mayúsculas y números (máx 20 caracteres)" />
                <div class="form-error" id="codigo-error">Código inválido</div>
            </div>
            <div class="entrada-col">
                <label>FECHA <span style="color:red;">*</span></label>
                <input type="date" name="fecha" id="fecha" value="<?= $fecha_actual ?>" required/>
                <div class="form-error" id="fecha-error">Fecha inválida</div>
            </div>
        </div>
        <div class="entrada-row">
            <div class="entrada-col full">
                <label>NOMBRE/DESCRIPCIÓN <span style="color:red;">*</span></label>
                <input type="text" name="descripcion" id="descripcion" required />
                <div class="form-error" id="descripcion-error">Descripción requerida</div>
            </div>
            <div class="entrada-col">
                <label>TIPO <span style="color:red;">*</span></label>
                <select name="tipo" id="tipo" required>
                    <option value="">Seleccionar...</option>
                    <option value="Material">Material</option>
                    <option value="Herramienta">Herramienta</option>
                </select>
                <div class="form-error" id="tipo-error">Tipo requerido</div>
            </div>
        </div>
        <div class="entrada-row">
            <div class="entrada-col">
                <label>LÍNEA <span style="color:red;">*</span></label>
                <select name="linea" id="linea" required>
                    <option value="">Seleccionar...</option>
                    <option value="Construcción">Construcción</option>
                    <option value="Electricidad">Electricidad</option>
                    <option value="Fontanería">Fontanería</option>
                    <option value="Herramientas">Herramientas</option>
                    <option value="Materiales">Materiales</option>
                </select>
                <div class="form-error" id="linea-error">Línea requerida</div>
            </div>
            <div class="entrada-col">
                <label>SUBLÍNEA <span style="color:red;">*</span></label>
                <select name="sublinea" id="sublinea" required>
                    <option value="">Seleccionar...</option>
                    <option value="Acero">Acero</option>
                    <option value="Cemento">Cemento</option>
                    <option value="Herramientas Manuales">Herramientas Manuales</option>
                    <option value="Herramientas Eléctricas">Herramientas Eléctricas</option>
                    <option value="Tuberías">Tuberías</option>
                    <option value="Cables">Cables</option>
                </select>
                <div class="form-error" id="sublinea-error">Sublínea requerida</div>
            </div>
            <div class="entrada-col">
                <label>UNIDAD <span style="color:red;">*</span></label>
                <select name="unidad" id="unidad" required>
                    <option value="">Seleccionar...</option>
                    <option value="Piezas">Piezas</option>
                    <option value="Kg">Kilogramos</option>
                    <option value="L">Litros</option>
                    <option value="m">Metros</option>
                    <option value="m²">Metros cuadrados</option>
                    <option value="m³">Metros cúbicos</option>
                </select>
                <div class="form-error" id="unidad-error">Unidad requerida</div>
            </div>
        </div>
        <div class="entrada-row">
            <div class="entrada-col full">
                <label>PROVEEDOR <span style="color:red;">*</span></label>
                <select name="proveedor" id="proveedor" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($proveedores as $proveedor): ?>
                        <option value="<?= $proveedor['idProveedor'] ?>"><?= htmlspecialchars($proveedor['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-error" id="proveedor-error">Proveedor requerido</div>
            </div>
        </div>
        <div class="entrada-row">
            <div class="entrada-col">
                <label>CANTIDAD <span style="color:red;">*</span></label>
                <input type="number" name="cantidad" id="cantidad" min="1" required />
                <div class="form-error" id="cantidad-error">Cantidad inválida</div>
            </div>
            <div class="entrada-col">
                <label>PRECIO UNITARIO <span style="color:red;">*</span></label>
                <input type="number" name="precio" id="precio" min="0.01" step="0.01" required />
                <div class="form-error" id="precio-error">Precio inválido</div>
            </div>
            <div class="entrada-col">
                <label>STOCK MÁXIMO</label>
                <input type="number" name="stockMaximo" id="stockMaximo" min="0" />
                <div class="form-error" id="stockMaximo-error">Valor inválido</div>
            </div>
            <div class="entrada-col">
                <label>PUNTO DE REORDEN</label>
                <input type="number" name="puntoReorden" id="puntoReorden" min="0" />
                <div class="form-error" id="puntoReorden-error">Valor inválido</div>
            </div>
        </div>
        <div class="entrada-actions">
            <a href="warehouse.php"><button type="button" class="btn cancel">CANCELAR</button></a>
            <button type="submit" class="btn confirm">CONFIRMAR</button>
        </div>
    </form>
</main>


<script>
  const toggle = document.getElementById('menu-toggle');
  const dropdown = document.getElementById('dropdown-menu');
  toggle.addEventListener('click', () => {
    dropdown.style.display = dropdown.style.display === 'flex' ? 'none' : 'flex';
  });
  window.addEventListener('click', (e) => {
    if (!toggle.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });
</script>

<script>
  const userToggle = document.getElementById('user-toggle');
  const userDropdown = document.getElementById('user-dropdown');
  userToggle.addEventListener('click', () => {
    userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
  });

  // Cerrar el menú al hacer clic fuera
  window.addEventListener('click', (e) => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
      userDropdown.style.display = 'none';
    }
  });
</script>

<script>
  const notifToggle = document.getElementById('notif-toggle');
  const notifDropdown = document.getElementById('notif-dropdown');
  notifToggle.addEventListener('click', () => {
    notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
  });
  window.addEventListener('click', (e) => {
    if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) {
      notifDropdown.style.display = 'none';
    }
  });
</script>

<script>
   // Validación del formulario
  const form = document.getElementById('entrada-form');
  const codigoInput = document.getElementById('codigo');
  
  // Convertir código a mayúsculas automáticamente
  codigoInput.addEventListener('input', function() {
    this.value = this.value.toUpperCase();
  });
  
  // Validar formulario antes de enviar
  form.addEventListener('submit', function(e) {
    let valid = true;
    
    // Validar campos requeridos
    const requiredInputs = form.querySelectorAll('input[required], select[required]');
    requiredInputs.forEach(input => {
      if (!input.value.trim()) {
        showError(input, 'Este campo es obligatorio');
        valid = false;
      }
    });
    
    // Validar cantidad
    const cantidad = document.getElementById('cantidad');
    if (cantidad.value <= 0) {
      showError(cantidad, 'La cantidad debe ser mayor a 0');
      valid = false;
    }
    
    // Validar precio
    const precio = document.getElementById('precio');
    if (precio.value <= 0) {
      showError(precio, 'El precio debe ser mayor a 0');
      valid = false;
    }
    
    // Validar stock máximo y punto de reorden
    const stockMaximo = document.getElementById('stockMaximo');
    const puntoReorden = document.getElementById('puntoReorden');
    
    if (stockMaximo.value && puntoReorden.value) {
      if (parseInt(stockMaximo.value) <= parseInt(puntoReorden.value)) {
        showError(stockMaximo, 'Stock máximo debe ser mayor que punto de reorden');
        valid = false;
      }
    }
    
    if (!valid) {
      e.preventDefault();
    }
  });
  
  // Función para mostrar errores
  function showError(input, message) {
    const errorElement = document.getElementById(`${input.id}-error`);
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    input.classList.add('input-error');
    
    // Remover el error después de 3 segundos
    setTimeout(() => {
      errorElement.style.display = 'none';
      input.classList.remove('input-error');
    }, 3000);
  }
</script>
</body>
</html>
