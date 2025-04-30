<?php
/**
 * CombatController - Controlador para gestionar combates
 */
class CombatController {
    private $conn;
    private $player_id = 1; // ID fijo del jugador para simplificar
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->ensureSessionStarted();
    }
    
    /**
     * Asegura que la sesión esté iniciada
     */
    private function ensureSessionStarted() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Inicia un combate basado en la posición actual del jugador
     */
    public function startCombat() {
        
        if (isset($_SESSION['combat_active']) && $_SESSION['combat_active']) {
            send_response(400, ['error' => 'Ya hay un combate activo']);
            return;
        }
        
        // Obtener el jugador
        $player = $this->getPlayer();
        if (!$player) {
            send_response(404, ['error' => 'Jugador no encontrado']);
            return;
        }
        
        // Obtener la casilla actual
        $sql = "SELECT * FROM map_tiles WHERE x = ? AND y = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $player['position_x'], $player['position_y']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            send_response(404, ['error' => 'Casilla no encontrada']);
            return;
        }
        
        $tile = $result->fetch_assoc();
        
        // Verificar probabilidad de encuentro
        if ($tile['enemy_chance'] <= 0) {
            send_response(400, ['error' => 'No hay probabilidad de encuentro en esta casilla']);
            return;
        }
        
        // Obtener un enemigo aleatorio basado en el nivel del jugador
        $enemy = $this->getRandomEnemy($player['level']);
        if (!$enemy) {
            send_response(404, ['error' => 'No se encontró ningún enemigo']);
            return;
        }
        
        // Guardar información del combate en la sesión
        $_SESSION['combat_active'] = true;
        $_SESSION['enemy'] = $enemy;
        
        send_response(200, [
            'player' => $player,
            'enemy' => $enemy,
            'tile' => $tile
        ]);
    }
    
    /**
     * Realiza un ataque en el combate actual
     */
    public function attackEnemy() {
      
        
        // Verificar si hay un combate activo
        if (!isset($_SESSION['combat_active']) || !$_SESSION['combat_active']) {
            send_response(400, ['error' => 'No hay un combate activo']);
            return;
        }
        
        // Obtener datos del jugador y enemigo
        $player = $this->getPlayer();
        $enemy = $_SESSION['enemy'];
        
        if (!$player || !$enemy) {
            send_response(404, ['error' => 'Datos de combate no encontrados']);
            return;
        }
        
        // Simular tiradas de dados (1-20) modificadas por la fuerza
        $player_roll = mt_rand(1, 20) + floor($player['strength'] / 5);
        $enemy_roll = mt_rand(1, 20) + floor($enemy['strength'] / 5);
        
        // Calcular daño
        $player_damage = 0;
        $enemy_damage = 0;
        
        if ($player_roll >= $enemy_roll) {
            // El jugador golpea
            $player_damage = mt_rand(1, 6) + floor($player['strength'] / 10);
            $enemy['health'] -= $player_damage;
        } else {
            // El enemigo golpea
            $enemy_damage = mt_rand(1, 4) + floor($enemy['strength'] / 10);
            $player['health'] -= $enemy_damage;
        }
        
        // Actualizar salud del jugador en la base de datos
        $sql = "UPDATE players SET health = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $player['health'], $this->player_id);
        $stmt->execute();
        
        // Actualizar enemigo en la sesión
        $_SESSION['enemy'] = $enemy;
        
        // Preparar respuesta
        $response = [
            'player' => $player,
            'enemy' => $enemy,
            'rolls' => [
                'player' => $player_roll,
                'enemy' => $enemy_roll
            ],
            'damage' => [
                'player_to_enemy' => $player_damage,
                'enemy_to_player' => $enemy_damage
            ]
        ];
        
        send_response(200, $response);
    }
    
    /**
     * Intenta huir del combate actual
     */
    public function fleeCombat() {


            // Debug session state
    error_log("Session state before check: " . json_encode([
        'session_id' => session_id(),
        'combat_active' => isset($_SESSION['combat_active']) ? $_SESSION['combat_active'] : 'not set',
        'has_enemy' => isset($_SESSION['enemy']) ? 'yes' : 'no'
    ]));
        
        // Verificar si hay un combate activo
        if (!isset($_SESSION['combat_active']) || !$_SESSION['combat_active']) {
            send_response(400, ['error' => 'No hay un combate activo']);
            return;
        }
        
        // Obtener datos del jugador y enemigo
        $player = $this->getPlayer();
        $enemy = $_SESSION['enemy'];
        
        if (!$player || !$enemy) {
            send_response(404, ['error' => 'Datos de combate no encontrados']);
            return;
        }
        
        // Tirada para huir (éxito si es > 10)
        $flee_roll = mt_rand(1, 20);
        $success = $flee_roll > 10;
        
        $damage = 0;
        
        // Si no logra huir, el enemigo golpea automáticamente
        if (!$success) {
            $damage = mt_rand(1, 4) + floor($enemy['strength'] / 10);
            $player['health'] -= $damage;
            
            // Actualizar salud del jugador en la base de datos
            $sql = "UPDATE players SET health = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $player['health'], $this->player_id);
            $stmt->execute();
        } else {
            // Si logra huir, finalizar el combate
            $_SESSION['combat_active'] = false;
            unset($_SESSION['enemy']);
        }
        
        // Preparar respuesta
        $response = [
            'success' => $success,
            'roll' => $flee_roll,
            'player' => $player,
            'damage' => $damage
        ];
        
        send_response(200, $response);
    }
    
    /**
     * Finaliza el combate actual y procesa resultados
     */
    public function endCombat($data) {
       
        
        // Verificar si hay un combate activo
        if (!isset($_SESSION['combat_active']) || !$_SESSION['combat_active']) {
            send_response(400, ['error' => 'No hay un combate activo']);
            return;
        }
        
        if (!isset($data['result'])) {
            send_response(400, ['error' => 'No se proporcionó un resultado de combate']);
            return;
        }
        
        $result = $data['result'];
        $player = $this->getPlayer();
        $enemy = $_SESSION['enemy'];
        
        if (!$player || !$enemy) {
            send_response(404, ['error' => 'Datos de combate no encontrados']);
            return;
        }
        
        $level_up = false;
        
        // Procesar resultados según el tipo de finalización
        switch ($result) {
            case 'win':
                // Otorgar experiencia
                $player['experience'] += $enemy['experience_reward'];
                
                // Verificar si sube de nivel (cada 100 puntos de exp)
                if ($player['experience'] >= $player['level'] * 100) {
                    $player['level'] += 1;
                    $player['max_health'] += 10;
                    $player['health'] = $player['max_health']; // Recuperar toda la salud al subir de nivel
                    $player['strength'] += 2;
                    $level_up = true;
                }
                
                // Actualizar estadísticas
                $sql = "UPDATE player_stats SET battles_won = battles_won + 1 WHERE player_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $this->player_id);
                $stmt->execute();
                break;
                
            case 'lose':
                // Actualizar estadísticas
                $sql = "UPDATE player_stats SET battles_lost = battles_lost + 1 WHERE player_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $this->player_id);
                $stmt->execute();
                
                // Reiniciar al jugador con salud mínima
                $player['health'] = 1;
                break;
                
            case 'flee':
                // No hay recompensas ni penalizaciones específicas por huir
                break;
                
            default:
                send_response(400, ['error' => 'Resultado de combate no válido']);
                return;
        }
        
        // Actualizar datos del jugador en la base de datos
        $sql = "UPDATE players SET level = ?, health = ?, max_health = ?, experience = ?, strength = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiiiii", $player['level'], $player['health'], $player['max_health'], 
                         $player['experience'], $player['strength'], $this->player_id);
        $stmt->execute();
        
        // Obtener estadísticas actualizadas
        $sql = "SELECT * FROM player_stats WHERE player_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        
        // Finalizar el combate
        $_SESSION['combat_active'] = false;
        unset($_SESSION['enemy']);
        
        // Preparar respuesta
        $response = [
            'player' => $player,
            'stats' => $stats,
            'levelUp' => $level_up
        ];
        
        send_response(200, $response);
    }
    
    /**
     * Obtiene los datos del jugador
     */
    private function getPlayer() {
        $sql = "SELECT * FROM players WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        } else {
            return null;
        }
    }
    
    /**
     * Obtiene un enemigo aleatorio basado en el nivel del jugador
     */
    private function getRandomEnemy($player_level) {
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
            return $result->fetch_assoc();
        }
        
        // Si no hay enemigos en ese rango, obtener cualquier enemigo
        $sql = "SELECT * FROM enemies ORDER BY RAND() LIMIT 1";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
}
?>