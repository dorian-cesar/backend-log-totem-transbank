<?php
// Configuración de headers CORS más completa
header("Content-Type: application/json");

// Manejo de CORS más robusto
$allowedOrigins = [
    "http://localhost",
    "http://127.0.0.1",
    "http://0.0.0.0:8080", // Añadido para desarrollo
    "https://tudominio.com",
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
        'rut', 
        'origen', 
        'destino', 
        'fecha_viaje', 
        'hora_viaje', 
        'asiento',
        'codigo_reserva',
        'numero_boleto',
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
            codigo_reserva, numero_boleto, estado_boleto, codigo_confirmacion, 
            codigo_transaccion, estado_transaccion, numero_transaccion, 
            fecha_transaccion, hora_transaccion, total_transaccion, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
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
            $input['numero_boleto'],            
            $input['estado_boleto'],
            $input['codigo_confirmacion'] ?? null,
            $input['codigo_transaccion'] ?? null,
            $input['estado_transaccion'] ?? null,
            $input['numero_transaccion'] ?? null,
            $input['fecha_transaccion'] ?? null,
            $input['hora_transaccion'] ?? null,
            $input['total_transaccion'] ?? null
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

// Endpoint 3: GET /api.php?rut=X (registros por RUT)
if ($method == 'GET' && isset($_GET['rut'])) {
    $rut = $_GET['rut'];
    
    if (empty($rut)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'RUT no válido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM totem_logs WHERE rut LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$rut%"]);
    $data = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'count' => $stmt->rowCount(),
        'data' => $data
    ]);
    exit;
}

// Si no coincide con ningún endpoint válido
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'Endpoint no encontrado']);
?>