<?php
/**
 * API Principal para el juego RPG
 * Este archivo actúa como punto de entrada para la API
 */

 session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,  // Changed from true to false for local development
    'httponly' => true,
    'samesite' => 'Lax'  // Changed from None to Lax for better compatibility
]);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');
// Configuración
header('Access-Control-Allow-Origin: http://127.0.0.1:5500');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');


// Para solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuración de la base de datos
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => 'rpg_game',
    'port' => '3307'
];

// Conectar a la base de datos
$conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['dbname'], $db_config['port']);

// Verificar conexión
if ($conn->connect_error) {
    send_response(500, ['error' => 'Error de conexión a la base de datos: ' . $conn->connect_error]);
    exit;
}

// Obtener la ruta de la solicitud
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));

// Eliminar 'rpg-api' del inicio del path (ajustar según tu configuración)
if ($uri_parts[0] === 'backend-php') {
    array_shift($uri_parts);
}

// Obtener el endpoint principal y el subrecurso si existe
$endpoint = $uri_parts[0] ?? '';
$resource_id = $uri_parts[1] ?? null;
$subresource = $uri_parts[2] ?? null;

// Obtener el método HTTP
$request_method = $_SERVER['REQUEST_METHOD'];

// Para solicitudes POST y PUT, obtener los datos enviados
$request_data = [];
if ($request_method === 'POST' || $request_method === 'PUT') {
    $input = file_get_contents('php://input');
    $request_data = json_decode($input, true) ?? [];
}

// Router simple para manejar las solicitudes
switch ($endpoint) {
    case 'player':
        include_once 'controllers/player_controller.php';
        $controller = new PlayerController($conn);
        
        if ($resource_id === 'stats') {
            // GET /player/stats
            if ($request_method === 'GET') {
                $controller->getPlayerStats();
            } else {
                send_response(405, ['error' => 'Método no permitido']);
            }
        } elseif ($resource_id === 'move') {
            // POST /player/move
            if ($request_method === 'POST') {
                $controller->movePlayer($request_data);
            } else {
                send_response(405, ['error' => 'Método no permitido']);
            }
        } else {
            // GET /player
            if ($request_method === 'GET') {
                $controller->getPlayer();
            } 
            // PUT /player
            elseif ($request_method === 'PUT') {
                $controller->updatePlayer($request_data);
            } else {
                send_response(405, ['error' => 'Método no permitido']);
            }
        }
        break;
        
    case 'map':
        include_once 'controllers/map_controller.php';
        $controller = new MapController($conn);
        
        // GET /map
        if ($request_method === 'GET') {
            $controller->getMap();
        } else {
            send_response(405, ['error' => 'Método no permitido']);
        }
        break;
        
    case 'combat':
        include_once 'controllers/combat_controller.php';
        $controller = new CombatController($conn);
        
        switch ($resource_id) {
            case 'start':
                // POST /combat/start
                if ($request_method === 'POST') {
                    $controller->startCombat();
                } else {
                    send_response(405, ['error' => 'Método no permitido']);
                }
                break;
                
            case 'attack':
                // POST /combat/attack
                if ($request_method === 'POST') {
                    $controller->attackEnemy();
                } else {
                    send_response(405, ['error' => 'Método no permitido']);
                }
                break;
                
            case 'flee':
                // POST /combat/flee
                if ($request_method === 'POST') {
                    $controller->fleeCombat();
                } else {
                    send_response(405, ['error' => 'Método no permitido']);
                }
                break;
                
            case 'end':
                // POST /combat/end
                if ($request_method === 'POST') {
                    $controller->endCombat($request_data);
                } else {
                    send_response(405, ['error' => 'Método no permitido']);
                }
                break;
                
            default:
                send_response(404, ['error' => 'Endpoint no encontrado']);
                break;
        }
        break;
        
    case 'game':
        if ($resource_id === 'restart') {
            include_once 'controllers/game_controller.php';
            $controller = new GameController($conn);
            
            // POST /game/restart
            if ($request_method === 'POST') {
                $controller->restartGame();
            } else {
                send_response(405, ['error' => 'Método no permitido']);
            }
        } else {
            send_response(404, ['error' => 'Endpoint no encontrado']);
        }
        break;
        
    default:
        // Ruta no encontrada
        send_response(404, ['error' => "Endpoint $endpoint no encontrado"]);
        break;
}

// Función para enviar respuestas JSON
function send_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// Cerrar la conexión a la base de datos
$conn->close();
?>