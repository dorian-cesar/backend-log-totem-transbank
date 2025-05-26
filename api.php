<?php
// Configuración de headers CORS más completa
header("Content-Type: application/json");

// Manejo de CORS más robusto
$allowedOrigins = [
    "http://localhost",
    "http://127.0.0.1",
    "http://0.0.0.0:8080",
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Si estás en desarrollo, puedes permitir cualquier origen
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // Cache preflight por 1 día

// Manejar petición OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Resto de tu código (conexión a DB, endpoints, etc.)
require_once __DIR__ . '/config/db.php';

try {
    $pdo = Database::getInstance();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Endpoint 1: GET /api.php (todos los registros con paginación)
if ($method == 'GET' && empty($_GET)) {  // <-- Quitar el paréntesis adicional
    // Parámetros de paginación
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 200;
    $offset = ($page - 1) * $perPage;

    // Consulta para contar el total de registros
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM totem_logs");
    $total = $totalStmt->fetch()['total'];
    $totalPages = ceil($total / $perPage);

    // Modificar la consulta para usar placeholders ?
    $stmt = $pdo->prepare("SELECT * FROM totem_logs ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'count' => $stmt->rowCount(),
        'total' => $total,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $perPage,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) // Asegurar array asociativo
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

// Endpoint 3: GET /api.php?rut=X (registros por RUT con paginación)
if ($method == 'GET' && isset($_GET['rut'])) {
    $rut = $_GET['rut'];
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 200;
    $offset = ($page - 1) * $perPage;
    
    if (empty($rut)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'RUT no válido']);
        exit;
    }

    // Consulta para contar el total de registros para este RUT
    $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM totem_logs WHERE rut LIKE ?");
    $totalStmt->execute(["%$rut%"]);
    $total = $totalStmt->fetch()['total'];
    $totalPages = ceil($total / $perPage);

    // Consulta para obtener los registros paginados
    $stmt = $pdo->prepare("SELECT * FROM totem_logs WHERE rut LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->execute(["%$rut%", $perPage, $offset]);
    
    echo json_encode([
        'success' => true,
        'count' => $stmt->rowCount(),
        'total' => $total,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $perPage,
        'data' => $stmt->fetchAll()
    ]);
    exit;
}

// Endpoint 4: POST /api.php (crear nuevo registro)
if ($method == 'POST') {
    // Obtener y decodificar el input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    // Manejar datos anidados (tanto 'data' como 'bookingData')
    if (isset($input['bookingData']) && is_array($input['bookingData'])) {
        $input = $input['bookingData'];
    } elseif (isset($input['data']) && is_array($input['data'])) {
        $input = $input['data'];
    }

    // Debugging (solo en desarrollo)
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        error_log("Raw input: " . $rawInput);
        error_log("Processed input: " . print_r($input, true));
    }

    // Validar JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'JSON inválido',
            'details' => json_last_error_msg()
        ]);
        exit;
    }

    // Campos requeridos
    $required = [
        //'rut', 
        'origen', 
        'destino', 
        'fecha_viaje', 
        'hora_viaje', 
        'asiento',
        'codigo_reserva',
        // 'codigo_autorizacion',
        // 'id_pos',
        //'numero_boleto',
        'estado_boleto'
    ];

    // Validar campos requeridos
    $missing = [];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Datos incompletos',
            'missing_fields' => $missing,
            'received_fields' => array_keys($input)
        ]);
        exit;
    }

    
    try {
        // Insertar en la base de datos
        $sql = "INSERT INTO totem_logs (
            numTotem, rut, origen, destino, fecha_viaje, hora_viaje, asiento, 
            codigo_reserva, codigo_autorizacion, id_pos, numero_boleto, estado_boleto, 
            codigo_transaccion, tipo_tarjeta, tarjeta_marca, id_bus, estado_transaccion, numero_transaccion, 
            fecha_transaccion, hora_transaccion, total_transaccion, error, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['numTotem'] ?? null,
            $input['rut'],
            $input['origen'],
            $input['destino'],
            $input['fecha_viaje'],
            $input['hora_viaje'],
            $input['asiento'],
            $input['codigo_reserva'],
            $input['codigo_autorizacion'] ?? null,
            $input['id_pos'] ?? null,
            $input['numero_boleto'],            
            $input['estado_boleto'],            
            $input['codigo_transaccion'] ?? null,
            $input['tipo_tarjeta'] ?? null,
            $input['tarjeta_marca'] ?? null,
            $input['id_bus'] ?? null,
            $input['estado_transaccion'] ?? null,
            $input['numero_transaccion'] ?? null,
            $input['fecha_transaccion'] ?? null,
            $input['hora_transaccion'] ?? null,
            $input['total_transaccion'] ?? null,
            $input['error'] ?? null
        ]);
        
        // Respuesta exitosa
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'message' => 'Registro creado exitosamente',
            'data' => $input
        ]);
        
    } catch (PDOException $e) {
        // Manejo de errores de base de datos
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al crear registro',
            'details' => $e->getMessage(),
            'sql_state' => $e->errorInfo[0] ?? null
        ]);
    }
    exit;
}


// Si no coincide con ningún endpoint válido
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
?>