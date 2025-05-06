<?php

/**
 * PickUpController - Controlador para que el jugador recoja items
 */
class PickUpController
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function pickUpItem($playerId)
    {
        // Obtener posición del jugador
        $sql = "SELECT position_x, position_y FROM players WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $playerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $player = $result->fetch_assoc();

        if (!$player) {
            send_response(404, ['error' => 'Jugador no encontrado.']);
        }

        $posX = $player['position_x'];
        $posY = $player['position_y'];

        // Buscar objeto en esa casilla
        $sql = "
            SELECT om.id AS object_map_id, om.objectId, om.quantity
            FROM object_map om
            JOIN map_tiles mt ON om.tilesId = mt.id
            WHERE mt.x = ? AND mt.y = ? AND om.is_taken = 0
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $posX, $posY);
        $stmt->execute();
        $result = $stmt->get_result();
        $object = $result->fetch_assoc();

        if (!$object) {
            send_response(200, ['message' => 'No hay objeto para recoger en esta casilla.']);
        }

        // Verificar si el jugador ya tiene ese objeto
        $sql = "SELECT quantity FROM object_player WHERE playerId = ? AND objectId = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $playerId, $object['objectId']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Ya tiene el objeto, actualizar cantidad
            $row = $result->fetch_assoc();
            $newQuantity = $row['quantity'] + $object['quantity'];

            $sql = "UPDATE object_player SET quantity = ? WHERE playerId = ? AND objectId = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iii", $newQuantity, $playerId, $object['objectId']);
            $stmt->execute();
        } else {
            // No tiene el objeto, insertar nueva fila
            $sql = "INSERT INTO object_player (playerId, objectId, quantity) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iii", $playerId, $object['objectId'], $object['quantity']);
            $stmt->execute();
        }

        // Marcar el objeto como recogido
        $sql = "UPDATE object_map SET is_taken = 1 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $object['object_map_id']);
        $stmt->execute();

        send_response(200, ['message' => '¡Objeto recogido con éxito!']);
    }
}
