<?php
/**
 * src/Model/Routeur.php
 * Modèle CRUD pour la table routeur (WBS 3.0)
 */

class Routeur {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère tous les routeurs d'un scénario précis.
     */
    public function listerParScenario(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, nom, pos_x, pos_y, scenario_id 
            FROM routeur 
            WHERE scenario_id = :scenario_id
        ");
        $stmt->execute([':scenario_id' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un routeur sur le canevas
     */
    public function ajouter(int $scenario_id, string $nom, float $pos_x, float $pos_y): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO routeur (scenario_id, nom, pos_x, pos_y) 
            VALUES (:scenario_id, :nom, :pos_x, :pos_y) 
            RETURNING id
        ");
        $stmt->execute([
            ':scenario_id' => $scenario_id,
            ':nom'         => $nom,
            ':pos_x'       => $pos_x,
            ':pos_y'       => $pos_y
        ]);
        // PostgreSQL permet de récupérer l'ID généré via RETURNING
        return (int) $stmt->fetchColumn(); 
    }
}
?>