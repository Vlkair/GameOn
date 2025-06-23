<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro - Institución Deportiva</title>
  <link rel="stylesheet" href="../../Public/css/styles_registroinsdepor.css">
  <style>
    .loading {
      display: none;
      color: #007bff;
      font-size: 14px;
    }
    .success {
      color: #28a745;
      font-size: 14px;
    }
    .error {
      color: #dc3545;
      font-size: 14px;
    }
    .ruc-info {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 4px;
      padding: 10px;
      margin-top: 10px;
      display: none;
    }
    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  </style>
</head>
<body class="auth-page">
  <div class="auth-container dual-column">
    
    <div class="auth-info">
      <h2>Información Importante</h2>
      <p>
        Bienvenido al proceso de registro para Propietarios de Instalaciones Deportivas en <strong>GameOn Network</strong>.
      </p>
      <p>
        Esta sección está diseñada exclusivamente para instituciones deportivas que desean formar parte de nuestra plataforma. 
        Para completar el registro, deberás adjuntar un documento legal en formato PDF que respalde tu actividad.
      </p>
      <p>
        <strong>Validación Automática:</strong> El sistema validará automáticamente tu RUC con SUNAT para verificar que esté activo y en buen estado.
      </p>
      <p>
        Una vez enviado el formulario, tu solicitud será evaluada por un miembro del equipo en un plazo de hasta <strong>3 días hábiles</strong>. 
        Recibirás un correo electrónico desde nuestra cuenta oficial de Gmail indicando si tu solicitud fue aprobada o si requiere modificaciones.
      </p>
      <p>
        En caso de ser aprobada, recibirás los datos de acceso y podrás comenzar a gestionar tus instalaciones, horarios, tarifas y más.
      </p>
      <p><em>¡Gracias por formar parte de la comunidad GameOn Network!</em></p>
    </div>

    <!-- COLUMNA DERECHA: Formulario -->
    <div class="auth-form">
      <div class="auth-header">
        <h2>Registro de Institución Deportiva</h2>
      </div>
      <div class="auth-body">
        <form id="registroForm" action="procesar_registroinsdepor.php" method="POST" enctype="multipart/form-data">
          <div class="form-group">
            <label for="nombre">Nombre de la Institución</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="ruc">RUC</label>
            <input type="text" name="ruc" id="ruc" class="form-control" required pattern="\d{11}" title="Ingrese 11 dígitos" maxlength="11">
            <div id="rucLoading" class="loading">Validando RUC con SUNAT...</div>
            <div id="rucSuccess" class="success"></div>
            <div id="rucError" class="error"></div>
            <div id="rucInfo" class="ruc-info">
              <strong>Datos de SUNAT:</strong>
              <div id="rucDatos"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="email">Correo electrónico</label>
            <input type="email" name="email" id="email" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" class="form-control" required minlength="6">
            <small class="form-text text-muted">Mínimo 6 caracteres</small>
          </div>

          <div class="form-group">
            <label for="documento">Subir Documento Legal (PDF)</label>
            <input type="file" name="documento" id="documento" class="form-control" accept=".pdf" required>
            <small class="form-text text-muted">Solo archivos PDF, máximo 5MB</small>
          </div>

          <button type="submit" id="submitBtn" class="btn btn-primary btn-large">Registrarse</button>
        </form>
        
        <div id="resultado" style="margin-top: 20px;"></div>
      </div>
      <div class="auth-footer">
        ¿Ya tienes una cuenta? <a href="../Auth/login.php">Inicia sesión</a>
      </div>
    </div>

  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const rucInput = document.getElementById('ruc');
      const submitBtn = document.getElementById('submitBtn');
      const form = document.getElementById('registroForm');
      let rucValido = false;
      let timeoutId;

      // Validación de RUC en tiempo real
      rucInput.addEventListener('input', function() {
        const ruc = this.value.trim();
        
        // Limpiar timeout anterior
        clearTimeout(timeoutId);
        
        // Ocultar elementos de estado
        document.getElementById('rucLoading').style.display = 'none';
        document.getElementById('rucSuccess').style.display = 'none';
        document.getElementById('rucError').style.display = 'none';
        document.getElementById('rucInfo').style.display = 'none';
        
        rucValido = false;
        updateSubmitButton();
        
        if (ruc.length === 11 && /^\d{11}$/.test(ruc)) {
          // Esperar 1 segundo antes de validar
          timeoutId = setTimeout(() => {
            validarRUC(ruc);
          }, 1000);
        }
      });

      function validarRUC(ruc) {
        document.getElementById('rucLoading').style.display = 'block';
        
        fetch('validar_ruc.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'ruc=' + encodeURIComponent(ruc)
        })
        .then(response => response.json())
        .then(data => {
          document.getElementById('rucLoading').style.display = 'none';
          
          if (data.success) {
            document.getElementById('rucSuccess').innerHTML = data.message;
            document.getElementById('rucSuccess').style.display = 'block';
            
            // Mostrar información de SUNAT
            const rucDatos = data.data;
            document.getElementById('rucDatos').innerHTML = `
              <div><strong>Razón Social:</strong> ${rucDatos.razon_social}</div>
              <div><strong>Estado:</strong> ${rucDatos.estado}</div>
              <div><strong>Condición:</strong> ${rucDatos.condicion}</div>
              <div><strong>Dirección:</strong> ${rucDatos.direccion}</div>
            `;
            document.getElementById('rucInfo').style.display = 'block';
            
            rucValido = true;
          } else {
            document.getElementById('rucError').innerHTML = data.message;
            document.getElementById('rucError').style.display = 'block';
            rucValido = false;
          }
          
          updateSubmitButton();
        })
        .catch(error => {
          document.getElementById('rucLoading').style.display = 'none';
          document.getElementById('rucError').innerHTML = 'Error al validar RUC';
          document.getElementById('rucError').style.display = 'block';
          rucValido = false;
          updateSubmitButton();
        });
      }

      function updateSubmitButton() {
        submitBtn.disabled = !rucValido;
      }

      // Envío del formulario
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!rucValido) {
          alert('Debe validar el RUC antes de continuar');
          return;
        }
        
        const formData = new FormData(form);
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Registrando...';
        
        fetch('procesar_registroinsdepor.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          const resultado = document.getElementById('resultado');
          
          if (data.success) {
            resultado.innerHTML = `
              <div class="alert alert-success">
                <h4>¡Registro Exitoso!</h4>
                <p>${data.message}</p>
              </div>
            `;
            form.reset();
            document.getElementById('rucInfo').style.display = 'none';
            rucValido = false;
          } else {
            resultado.innerHTML = `
              <div class="alert alert-danger">
                <h4>Error en el Registro</h4>
                <p>${data.message}</p>
              </div>
            `;
          }
          
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Registrarse';
        })
        .catch(error => {
          document.getElementById('resultado').innerHTML = `
            <div class="alert alert-danger">
              <h4>Error</h4>
              <p>Ocurrió un error inesperado. Intente nuevamente.</p>
            </div>
          `;
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Registrarse';
        });
      });
    });
  </script>
</body>
</html>