<!DOCTYPE html>

<html>

<head>
  <meta charset="UTF-8" />
  <title>SIA Login</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <header>
    <img src="img/cmapa.png" />
    <h1>Sistema de Inventario de Almacén - CMAPA</h1>
    <p>Versión 1.2</p>
  </header>

  <section class="container">
    <h2>Bienvenid@</h2>
    <form action="php/login.php" method="POST">
      <div>
        <img src="img/userB.png" />
        <input type="text" name="user" placeholder="Usuario" required />
      </div>
      <div>
        <img src="img/padlockB.png" />
        <input type="password" name="password" placeholder="Contraseña" required />
      </div>
      <p class="forgot">
        <a href="#" onclick="mostrarMensaje()">Olvidé mi contraseña</a>
      </p>
      <button type="submit">Iniciar Sesión</button>
    </form>
    <p id="mensaje-info" style="display: none;">
      PARA UN CAMBIO DE USUARIO Y/O CONTRASEÑA ES NECESARIO QUE SE PONGA EN CONTACTO CON EL ADMINISTRADOR DEL SISTEMA.
    </p>
  </section>

  <script>
    function mostrarMensaje() {
      document.getElementById('mensaje-info').style.display = 'block';
    }
  </script>
</body>
</html>
