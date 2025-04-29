<?php
// Configuración de headers para respuestas JSON y CORS
header("Content-Type: application/json"); 
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST"); 
header("Access-Control-Allow-Headers: Content-Type"); 

// Incluye el archivo de configuración de la base de datos
require_once __DIR__ . '/config/db.php';

// Intenta establecer conexión con la base de datos
try {
    $pdo = Database::getInstance();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Obtiene el método HTTP utilizado en la petición
$method = $_SERVER['REQUEST_METHOD'];

// Endpoint 1: GET /api.php (todos los registros)
if ($method == 'GET' && empty($_GET)) {
    $stmt = $pdo->query("SELECT * FROM totem_logs");
    echo json_encode([
        'success' => true,
        'count' => $stmt->rowCount(),
        'data' => $stmt->fetchAll()
    ]);
    exit;
}

// Endpoint 2: GET /api.php?id=X (registro específico)
if ($method == 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID no válido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM totem_logs WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Registro no encontrado']);
    }
    exit;
}

// Endpoint 3: POST /api.php (crear nuevo registro)
if ($method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }
    
    $required = ['rut', 'origen', 'destino', 'fecha_viaje', 'hora_viaje', 'asiento', 
                'codigo_reserva', 'codigo_venta', 'codigo_confirmacion', 'estado_transaccion'];
    
    $missing = [];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Campos requeridos faltantes',
            'missing_fields' => $missing
        ]);
        exit;
    }
    
    try {
        $sql = "INSERT INTO totem_logs (
            rut, origen, destino, fecha_viaje, hora_viaje, asiento, 
            codigo_reserva, codigo_venta, codigo_confirmacion, estado_transaccion, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['rut'],
            $input['origen'],
            $input['destino'],
            $input['fecha_viaje'],
            $input['hora_viaje'],
            $input['asiento'],
            $input['codigo_reserva'],
            $input['codigo_venta'],
            $input['codigo_confirmacion'],
            $input['estado_transaccion']
        ]);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'message' => 'Registro creado exitosamente',
            'data' => $input
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al crear registro',
            'details' => $e->getMessage()
        ]);
    }
    exit;
}

// Si no coincide con ningún endpoint válido
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
?>