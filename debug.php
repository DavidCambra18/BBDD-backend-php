<?php
header('Access-Control-Allow-Origin: http://127.0.0.1:5500');
header('Content-Type: application/json');

// Información del servidor
$server_info = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'],
    'Document Root' => $_SERVER['DOCUMENT_ROOT'],
    'Request URI' => $_SERVER['REQUEST_URI'],
    'Script Filename' => $_SERVER['SCRIPT_FILENAME'],
    'MySQL' => extension_loaded('mysqli') ? 'Loaded' : 'Not loaded'
];

// Verificar si el archivo index.php existe
$index_path = __DIR__ . '/index.php';
$player_controller_path = __DIR__ . '/controllers/player_controller.php';
$file_checks = [
    'index.php exists' => file_exists($index_path),
    'index.php readable' => is_readable($index_path),
    'player_controller.php exists' => file_exists($player_controller_path),
    'player_controller.php readable' => is_readable($player_controller_path)
];

// Intentar conexión a la base de datos
$db_status = [];
try {
    $conn = new mysqli('localhost', 'root', '', 'rpg_game');
    $db_status['connection'] = $conn->connect_error ? 'Error: ' . $conn->connect_error : 'Success';
    
    if (!$conn->connect_error) {
        // Verificar tabla players
        $result = $conn->query("SHOW TABLES LIKE 'players'");
        $db_status['players_table_exists'] = $result->num_rows > 0 ? 'Yes' : 'No';
        
        if ($result->num_rows > 0) {
            $result = $conn->query("SELECT COUNT(*) as count FROM players");
            $row = $result->fetch_assoc();
            $db_status['player_count'] = $row['count'];
        }
    }
} catch (Exception $e) {
    $db_status['connection'] = 'Exception: ' . $e->getMessage();
}

echo json_encode([
    'server_info' => $server_info,
    'file_checks' => $file_checks,
    'database_status' => $db_status
], JSON_PRETTY_PRINT);
?>