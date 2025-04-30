<?php
/**
 * GameController - Controlador para funciones generales del juego
 */
class GameController {
    private $conn;
    private $player_id = 1; // ID fijo del jugador para simplificar
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Reinicia el juego (restablece al jugador y sus estadísticas)
     */
    public function restartGame() {
        // Restablecer datos del jugador
        $sql = "UPDATE players SET 
                level = 1, 
                health = 100,
                max_health = 100,
                experience = 0,
                strength = 10,
                position_x = 1,
                position_y = 1
                WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        
        if (!$stmt->execute()) {
            send_response(500, ['error' => 'Error al reiniciar el jugador']);
            return;
        }
        
        // Restablecer estadísticas
        $sql = "UPDATE player_stats SET 
                battles_won = 0,
                battles_lost = 0,
                steps_taken = 0
                WHERE player_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        
        if (!$stmt->execute()) {
            send_response(500, ['error' => 'Error al reiniciar las estadísticas']);
            return;
        }
        
        // Limpia la sesión si hay un combate activo
        session_start();
        $_SESSION['combat_active'] = false;
        unset($_SESSION['enemy']);
        
        // Obtener datos actualizados del jugador
        $sql = "SELECT * FROM players WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $player = $result->fetch_assoc();
        
        // Obtener estadísticas actualizadas
        $sql = "SELECT * FROM player_stats WHERE player_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        
        send_response(200, [
            'player' => $player,
            'stats' => $stats,
            'message' => 'Juego reiniciado correctamente'
        ]);
    }
    
    /**
     * Genera un nuevo mapa (borra el existente y crea uno nuevo)
     */
    public function regenerateMap() {
        // Eliminar mapa existente
        $sql = "TRUNCATE TABLE map_tiles";
        if (!$this->conn->query($sql)) {
            send_response(500, ['error' => 'Error al eliminar el mapa existente']);
            return;
        }
        
        // Instanciar el controlador del mapa para usar su función de generación
        include_once 'map_controller.php';
        $mapController = new MapController($this->conn);
        
        // Llama al método privado generateMap a través de getMap
        // que generará un nuevo mapa si no existe ninguno
        $mapController->getMap();
        
        send_response(200, ['message' => 'Mapa regenerado correctamente']);
    }
}
?>