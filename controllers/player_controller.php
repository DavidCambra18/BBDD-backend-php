<?php
/**
 * PlayerController - Controlador para gestionar acciones relacionadas con el jugador
 */
class PlayerController {
    private $conn;
    private $player_id = 1; // ID fijo del jugador para simplificar (en un juego real tendríamos sistema de usuarios)
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Obtiene los datos del jugador
     */
    public function getPlayer() {
        $sql = "SELECT * FROM players WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $player = $result->fetch_assoc();
            send_response(200, $player);
        } else {
            send_response(404, ['error' => 'Jugador no encontrado']);
        }
    }
    
    /**
     * Obtiene las estadísticas del jugador
     */
    public function getPlayerStats() {
        $sql = "SELECT * FROM player_stats WHERE player_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stats = $result->fetch_assoc();
            send_response(200, $stats);
        } else {
            send_response(404, ['error' => 'Estadísticas no encontradas']);
        }
    }
    
    /**
     * Actualiza los datos del jugador
     */
    public function updatePlayer($data) {
        if (empty($data)) {
            send_response(400, ['error' => 'No se proporcionaron datos para actualizar']);
            return;
        }
        
        // Campos permitidos para actualizar
        $allowed_fields = ['name', 'health', 'experience', 'level', 'strength', 'max_health'];
        
        $updates = [];
        $types = '';
        $values = [];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                
                // Determinar el tipo de dato
                if (is_int($data[$field])) {
                    $types .= 'i'; // integer
                } else {
                    $types .= 's'; // string
                }
                
                $values[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            send_response(400, ['error' => 'No se proporcionaron campos válidos para actualizar']);
            return;
        }
        
        // Añadir el ID del jugador
        $types .= 'i';
        $values[] = $this->player_id;
        
        $sql = "UPDATE players SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        
        // Convertir array a referencias para bind_param
        $params = [];
        $params[] = &$types;
        
        foreach ($values as $key => $value) {
            $params[] = &$values[$key];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $params);
        
        if ($stmt->execute()) {
            // Obtener jugador actualizado
            $sql = "SELECT * FROM players WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $this->player_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $player = $result->fetch_assoc();
            
            send_response(200, $player);
        } else {
            send_response(500, ['error' => 'Error al actualizar el jugador']);
        }
    }
    
    /**
     * Mueve al jugador en el mapa
     */
    public function movePlayer($data) {
        if (!isset($data['direction'])) {
            send_response(400, ['error' => 'No se proporcionó una dirección']);
            return;
        }
        
        // Obtener posición actual del jugador
        $sql = "SELECT position_x, position_y FROM players WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_response(404, ['error' => 'Jugador no encontrado']);
            return;
        }
        
        $player = $result->fetch_assoc();
        $new_x = $player['position_x'];
        $new_y = $player['position_y'];
        
        file_put_contents('direction_log.txt', 
        date('Y-m-d H:i:s') . " - Received direction: " . 
        (isset($data['direction']) ? $data['direction'] : 'NO DIRECTION') . 
        "\nOriginal position: " . ($new_x) . ", " . ($new_y) ."\nFull data: " . json_encode($data) . "\n", 
        FILE_APPEND);
        // Calcular nueva posición basada en la dirección
        switch ($data['direction']) {
            case 'up':
                $new_y -= 1;
                break;
            case 'down':
                $new_y += 1;
                break;
            case 'left':
                $new_x -= 1;
                break;
            case 'right':
                $new_x += 1;
                break;
            default:
                send_response(400, ['error' => 'Dirección no válida']);
                return;
        }
        
        // Validar que la nueva posición esté dentro del mapa
        if ($new_x < 0 || $new_y < 0 || $new_x >= 10 || $new_y >= 10) {
            send_response(400, ['error' => 'Movimiento fuera de los límites del mapa']);
            return;
        }
        
        // Verificar si la casilla es accesible (no agua por ejemplo)
        $sql = "SELECT * FROM map_tiles WHERE x = ? AND y = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $new_x, $new_y);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_response(400, ['error' => 'Casilla no encontrada']);
            return;
        }
        
        $tile = $result->fetch_assoc();
        
        // Si es agua, no permitir el movimiento
        if ($tile['type'] === 'water') {
            send_response(400, ['error' => 'No puedes moverte a través del agua']);
            return;
        }
        
        // Actualizar la posición del jugador
        $sql = "UPDATE players SET position_x = ?, position_y = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iii", $new_x, $new_y, $this->player_id);
        
        if ($stmt->execute()) {
            // Actualizar estadísticas (incrementar pasos)
            $sql = "UPDATE player_stats SET steps_taken = steps_taken + 1 WHERE player_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $this->player_id);
            $stmt->execute();
            
            // Verificar si hay un encuentro con enemigo
            $encounter = $this->checkEncounter($tile);
            
            // Obtener jugador actualizado
            $sql = "SELECT * FROM players WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $this->player_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $updated_player = $result->fetch_assoc();
            
            $response = [
                'player' => $updated_player,
                'tile' => $tile
            ];
            
            // Añadir información del encuentro si hay uno
            if ($encounter) {
                $response['encounter'] = $encounter;
            }
            
            send_response(200, $response);
        } else {
            send_response(500, ['error' => 'Error al mover al jugador']);
        }
    }
    
    /**
     * Verifica si hay un encuentro con enemigo en la casilla actual
     */
    private function checkEncounter($tile) {
        // Si la casilla tiene probabilidad de encuentro
        if ($tile['enemy_chance'] > 0) {
            // Generar número aleatorio entre 0 y 1
            $random = mt_rand() / mt_getrandmax();
            
            // Si el número aleatorio es menor que la probabilidad, hay encuentro
            if ($random < $tile['enemy_chance']) {
                // Obtener un enemigo aleatorio basado en el nivel del jugador
                return $this->getRandomEnemy();
            }
        }
        
        return null;
    }
    
    /**
     * Obtiene un enemigo aleatorio basado en el nivel del jugador
     */
    private function getRandomEnemy() {
        // Obtener nivel del jugador
        $sql = "SELECT level FROM players WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $player = $result->fetch_assoc();
        
        $player_level = $player['level'];
        
        // Calcular rango de niveles para enemigos (nivel del jugador ±1)
        $min_level = max(1, $player_level - 1);
        $max_level = $player_level + 1;
        
        // Obtener un enemigo aleatorio en ese rango de niveles
        $sql = "SELECT * FROM enemies WHERE level BETWEEN ? AND ? ORDER BY RAND() LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $min_level, $max_level);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['enemy' => $result->fetch_assoc()];
        }
        
        // Si no hay enemigos en ese rango, obtener cualquier enemigo
        $sql = "SELECT * FROM enemies ORDER BY RAND() LIMIT 1";
        $stmt = $this->conn->query($sql);
        
        if ($stmt->num_rows > 0) {
            return ['enemy' => $stmt->fetch_assoc()];
        }
        
        return null;
    }
}