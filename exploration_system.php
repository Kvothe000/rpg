// exploration_system.php
class ExplorationSystem {
    public function registrar_descoberta($player_id, $regiao, $conexao) {
        $sql = "INSERT INTO player_exploracao (player_id, regiao, data_descoberta) VALUES (?, ?, NOW())";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("is", $player_id, $regiao);
        return $stmt->execute();
    }
    
    public function get_conquistas($player_id, $conexao) {
        $sql = "SELECT * FROM conquistas WHERE id IN (
            SELECT conquista_id FROM player_conquistas WHERE player_id = ?
        )";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("i", $player_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}