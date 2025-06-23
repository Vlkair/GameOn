<?php
/**
 * Procesamiento de registro de instituciones deportivas
 * Con validación de RUC mediante API de SUNAT
 */

// Incluir la clase SunatAPI (ruta corregida)
require_once __DIR__ . '/../../SunatAPI.php';

// Configuración de base de datos (ajusta según tu configuración)
$host = 'localhost';
$dbname = 'gameon';
$username = 'root'; 
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    die('Método no permitido');
}

// Función para responder con JSON
function responderJSON($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Función para limpiar datos de entrada
function limpiarDato($dato) {
    return htmlspecialchars(strip_tags(trim($dato)));
}

// Validar que todos los campos requeridos estén presentes
$camposRequeridos = ['nombre', 'ruc', 'email', 'password'];
$errores = [];

foreach ($camposRequeridos as $campo) {
    if (empty($_POST[$campo])) {
        $errores[] = "El campo {$campo} es obligatorio";
    }
}

// Validar archivo PDF
if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== 0) {
    $errores[] = "Debe adjuntar un documento PDF válido";
}

if (!empty($errores)) {
    responderJSON(false, implode(', ', $errores));
}

// Limpiar y obtener datos del formulario
$nombre = limpiarDato($_POST['nombre']);
$ruc = limpiarDato($_POST['ruc']);
$email = limpiarDato($_POST['email']);
$password = $_POST['password'];

// Validaciones adicionales
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderJSON(false, 'El formato del email es inválido');
}

if (strlen($password) < 6) {
    responderJSON(false, 'La contraseña debe tener al menos 6 caracteres');
}

// VALIDACIÓN CON API DE SUNAT
$sunatAPI = new SunatAPI();
$resultadoRUC = $sunatAPI->validarRUC($ruc);

if (!$resultadoRUC['success']) {
    responderJSON(false, $resultadoRUC['message']);
}

$datosRUC = $resultadoRUC['data'];

// Verificar que el RUC esté activo y en buen estado
if (strtoupper($datosRUC['estado']) !== 'ACTIVO') {
    responderJSON(false, 'El RUC debe estar en estado ACTIVO en SUNAT');
}

if (strtoupper($datosRUC['condicion']) !== 'HABIDO') {
    responderJSON(false, 'El RUC debe estar en condición HABIDO en SUNAT');
}

// Verificar si el RUC o email ya están registrados
try {
    $stmt = $pdo->prepare("SELECT id FROM instituciones_deportivas WHERE ruc = ? OR email = ?");
    $stmt->execute([$ruc, $email]);
    
    if ($stmt->rowCount() > 0) {
        responderJSON(false, 'El RUC o email ya están registrados en el sistema');
    }
} catch (PDOException $e) {
    responderJSON(false, 'Error al verificar datos existentes');
}

// Procesar archivo PDF
$directorioDocumentos = '../uploads/documentos/';
if (!is_dir($directorioDocumentos)) {
    mkdir($directorioDocumentos, 0755, true);
}

$archivo = $_FILES['documento'];
$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

if ($extension !== 'pdf') {
    responderJSON(false, 'Solo se permiten archivos PDF');
}

// Generar nombre único para el archivo
$nombreArchivo = 'doc_' . $ruc . '_' . date('YmdHis') . '.pdf';
$rutaCompleta = $directorioDocumentos . $nombreArchivo;

if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
    responderJSON(false, 'Error al subir el documento');
}

// Encriptar contraseña
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insertar en base de datos
try {
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO instituciones_deportivas (
        nombre, 
        ruc, 
        email, 
        password, 
        razon_social_sunat,
        direccion_sunat,
        distrito_sunat,
        provincia_sunat,
        departamento_sunat,
        estado_sunat,
        condicion_sunat,
        documento_legal,
        fecha_registro,
        estado_aprobacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'PENDIENTE')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $nombre,
        $ruc,
        $email,
        $passwordHash,
        $datosRUC['razon_social'],
        $datosRUC['direccion'],
        $datosRUC['distrito'],
        $datosRUC['provincia'],
        $datosRUC['departamento'],
        $datosRUC['estado'],
        $datosRUC['condicion'],
        $nombreArchivo
    ]);
    
    $institucionId = $pdo->lastInsertId();
    
    $pdo->commit();
    
    // Enviar email de confirmación (opcional)
    enviarEmailConfirmacion($email, $nombre, $ruc);
    
    responderJSON(true, 'Registro exitoso. Su solicitud será evaluada en un plazo de 3 días hábiles.', [
        'institucion_id' => $institucionId,
        'datos_sunat' => $datosRUC
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    // Eliminar archivo si hay error
    if (file_exists($rutaCompleta)) {
        unlink($rutaCompleta);
    }
    responderJSON(false, 'Error al registrar la institución');
}

/**
 * Función para enviar email de confirmación
 */
function enviarEmailConfirmacion($email, $nombre, $ruc) {
    $asunto = "Registro Recibido - GameOn Network";
    $mensaje = "
    <html>
    <body>
        <h2>Registro Recibido Exitosamente</h2>
        <p>Estimado(a) representante de <strong>{$nombre}</strong>,</p>
        <p>Hemos recibido su solicitud de registro con los siguientes datos:</p>
        <ul>
            <li><strong>Institución:</strong> {$nombre}</li>
            <li><strong>RUC:</strong> {$ruc}</li>
            <li><strong>Email:</strong> {$email}</li>
        </ul>
        <p>Su solicitud será evaluada por nuestro equipo en un plazo de hasta 3 días hábiles.</p>
        <p>Recibirá una respuesta a este mismo correo electrónico.</p>
        <br>
        <p>Saludos cordiales,<br>
        Equipo GameOn Network</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@gameonnetwork.com" . "\r\n";
    
    mail($email, $asunto, $mensaje, $headers);
}
?>