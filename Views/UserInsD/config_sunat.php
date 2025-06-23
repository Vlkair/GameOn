<?php
/**
 * Configuración para integración con APIs de SUNAT
 */

// APIs disponibles para consulta RUC (puedes cambiar entre estas opciones)
define('SUNAT_APIs', [
    'apisperu' => [
        'url' => 'https://dniruc.apisperu.com/api/v1/ruc/{ruc}?token={token}',
        'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6Inp1cmN0aG9ydmljMDdAZ21haWwuY29tIn0.zzyUejU7lpCGDvDq4zlIz3NqcP0BNdregSSvKTQO_4w',
        'method' => 'GET',
        'headers' => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ],
    'api_net_pe' => [
        'url' => 'https://api.apis.net.pe/v2/sunat/ruc?numero={ruc}',
        'token' => 'apis-token-15944.6NjWunW1SAdZkyn9tzf63Aa5Bfch9jK2', // Reemplazar con tu token real
        'method' => 'GET',
        'headers' => [
            'Accept: application/json',
            'Authorization: Bearer {token}'
        ]
    ],
    'factiliza' => [
        'url' => 'https://api.factiliza.com/v1/ruc/info/{ruc}', // Cambia la URL si es diferente
        'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIzODkxNiIsImh0dHA6Ly9zY2hlbWFzLm1pY3Jvc29mdC5jb20vd3MvMjAwOC8wNi9pZGVudGl0eS9jbGFpbXMvcm9sZSI6ImNvbnN1bHRvciJ9.OJI-RHXFevShMsj6dOpPQzArdxbk1JfgI_YH5NpC_ik', // Reemplaza con tu token real
        'method' => 'GET',
        'headers' => [
            'Accept: application/json',
            'Authorization: Bearer {token}'
        ]
    ],
    'consulta_sunat' => [
        'url' => 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/jcrS00Alias',
        'method' => 'POST',
        'scraping' => true // Indica que requiere scraping web
    ]
]);

// API por defecto a usar
define('SUNAT_API_DEFAULT', 'factiliza');

// Configuración de timeouts
define('SUNAT_TIMEOUT', 30);
define('SUNAT_CONNECT_TIMEOUT', 10);

// Configuración de base de datos
define('DB_CONFIG', [
    'host' => 'localhost',
    'dbname' => 'gameon',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8'
]);

// Configuración de archivos
define('UPLOAD_CONFIG', [
    'documents_dir' => '../uploads/documentos/',
    'max_file_size' => 5 * 1024 * 1024, // 5MB
    'allowed_extensions' => ['pdf'],
    'create_dir_if_not_exists' => true
]);

// Configuración de email
define('EMAIL_CONFIG', [
    'from' => 'noreply@gameonnetwork.com',
    'from_name' => 'GameOn Network',
    'smtp_host' => 'zurcthorvic07.gmail.com', // Cambiar según tu proveedor
    'smtp_port' => 587,
    'smtp_username' => 'zurcthorvic07@gmail.com',
    'smtp_password' => 'bb6.Ac15',
    'smtp_secure' => 'tls'
]);

// Estados válidos para aprobación de registro
define('ESTADOS_APROBACION', [
    'PENDIENTE' => 'Pendiente de revisión',
    'APROBADO' => 'Aprobado y activo',
    'RECHAZADO' => 'Rechazado'
]);

// Función helper para obtener configuración de BD
function getDBConnection() {
    $config = DB_CONFIG;
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}", 
            $config['username'], 
            $config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Error de conexión a BD: " . $e->getMessage());
    }
}

// Función helper para logging de errores
function logError($message, $context = []) {
    $logFile = '../logs/sunat_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Función helper para respuestas JSON uniformes
function jsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>