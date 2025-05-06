<?php

/**
 * InventoryController - Controlador para usar los objetos del inventario
 */

class InventoryController
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function useItem($playerId, $objectId)
    {
        // Verificar si el jugador tiene el objeto
        $sql = "SELECT quantity FROM object_player WHERE playerId = ? AND objectId = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $playerId, $objectId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_response(400, ['error' => 'No tienes ese objeto.']);
        }

        $data = $result->fetch_assoc();
        if ($data['quantity'] <= 0) {
            send_response(400, ['error' => 'Cantidad insuficiente.']);
        }

        // Obtener tipo y efecto del objeto
        $sql = "SELECT type, effect_value FROM objects WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $objectId);
        $stmt->execute();
        $typeResult = $stmt->get_result();

        if ($typeResult->num_rows === 0) {
            send_response(404, ['error' => 'Objeto no válido.']);
        }

        $object = $typeResult->fetch_assoc();

        if ($object['type'] === 'potion') {
            // Aplicar efecto: aumentar salud
            $sql = "UPDATE player_stats SET health = LEAST(health + ?, max_health) WHERE playerId = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $object['effect_value'], $playerId);
            $stmt->execute();

            // Reducir cantidad de objeto
            $sql = "UPDATE object_player SET quantity = quantity - 1 WHERE playerId = ? AND objectId = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $playerId, $objectId);
            $stmt->execute();

            send_response(200, ['message' => 'Has usado una poción. Salud restaurada.']);
        } else {
            send_response(400, ['error' => 'Este objeto no se puede usar.']);
        }
    }
}
