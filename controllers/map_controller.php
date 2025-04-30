<?php
/**
 * MapController - Controlador para gestionar el mapa del juego
 */
class MapController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Obtiene todos los datos del mapa
     */
    public function getMap() {
        $sql = "SELECT * FROM map_tiles ORDER BY y, x";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows > 0) {
            $tiles = [];
            
            while ($row = $result->fetch_assoc()) {
                $tiles[] = $row;
            }
            
            send_response(200, $tiles);
        } else {
            // Si no hay mapa, generar uno automáticamente
            $this->generateMap();
            $this->getMap(); // Llamar de nuevo a este método para obtener el mapa generado
        }
    }
    
    /**
     * Genera un mapa aleatorio
     */
    private function generateMap() {
        // Dimensiones del mapa
        $width = 10;
        $height = 10;
        
        // Tipos de terreno con sus probabilidades de encuentro
        $terrain_types = [
            'grass' => 0.2,
            'forest' => 0.4,
            'mountain' => 0.1,
            'water' => 0.0
        ];
        
        // Crear el mapa
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Elegir un tipo de terreno aleatorio
                $type = $this->getRandomTerrainType($terrain_types, $x, $y, $width, $height);
                
                // Obtener la probabilidad de encuentro para este tipo de terreno
                $enemy_chance = $terrain_types[$type];
                
                // Insertar la casilla en la base de datos
                $sql = "INSERT INTO map_tiles (x, y, type, enemy_chance) VALUES (?, ?, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("iiss", $x, $y, $type, $enemy_chance);
                $stmt->execute();
            }
        }
    }
    
    /**
     * Obtiene un tipo de terreno aleatorio con algunas reglas para que el mapa sea coherente
     */
    private function getRandomTerrainType($terrain_types, $x, $y, $width, $height) {
        // Crear bordes de agua alrededor del mapa
        if ($x == 0 || $y == 0 || $x == $width - 1 || $y == $height - 1) {
            return 'water';
        }
        
        // Terreno aleatorio para el resto del mapa
        $rand = mt_rand(1, 100);
        
        if ($rand <= 60) {
            return 'grass';
        } elseif ($rand <= 85) {
            return 'forest';
        } elseif ($rand <= 95) {
            return 'mountain';
        } else {
            return 'water';
        }
    }
    
    /**
     * Obtiene una casilla específica del mapa
     */
    public function getTile($x, $y) {
        $sql = "SELECT * FROM map_tiles WHERE x = ? AND y = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $x, $y);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        } else {
            return null;
        }
    }
}
?>