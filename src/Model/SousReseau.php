<?php
class SousReseau {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerParScenario(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, adresse_reseau, masque, scenario_id 
            FROM sous_reseau 
            WHERE scenario_id = :scenario_id
        ");
        $stmt->execute([':scenario_id' => $scenario_id]);
        return $stmt->fetchAll();
    }
}
?>