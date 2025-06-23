<?php
/**
 * Endpoint AJAX para validación de RUC con SUNAT
 * Se usa para la validación en tiempo real en el formulario
 */

header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar que se envió el RUC
if (!isset($_POST['ruc']) || empty($_POST['ruc'])) {
    echo json_encode(['success' => false, 'message' => 'RUC requerido']);
    exit;
}

// Incluir la clase SunatAPI (ruta corregida)
require_once __DIR__ . '/SunatAPI.php';

$ruc = trim($_POST['ruc']);


try {
    $sunatAPI = new SunatAPI();
    $resultado = $sunatAPI->validarRUC($ruc);

    // Agrega esto para depurar la respuesta real:
    file_put_contents('debug_factiliza.txt', print_r($resultado['data'], true));

    
    // Si la validación es exitosa, verificar estado y condición
    if ($resultado['success']) {
        $datos = $resultado['data'];
        
        // Verificaciones adicionales para el registro
        $advertencias = [];
        
        if (strtoupper($datos['estado']) !== 'ACTIVO') {
            $advertencias[] = 'El RUC no está en estado ACTIVO';
        }
        
        if (strtoupper($datos['condicion']) !== 'HABIDO') {
            $advertencias[] = 'El RUC no está en condición HABIDO';
        }
        
        if (!empty($advertencias)) {
            echo json_encode([
                'success' => false,
                'message' => 'RUC encontrado pero con observaciones: ' . implode(', ', $advertencias),
                'data' => $datos
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'RUC válido y activo en SUNAT',
                'data' => $datos
            ]);
        }
    } else {
        echo json_encode($resultado);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al validar RUC: ' . $e->getMessage()
    ]);
}
?>