<?php
class Commutateur {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerParScenario(int $scenario_id): array {
        $stmt = $this->pdo->prepare("SELECT id_switch AS id, nom, pos_x, pos_y FROM SWITCH WHERE id_scenario = :sid");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll();
    }

    public function ajouter(int $scenario_id, string $nom): int {
        $x = rand(150, 300); 
        $y = rand(150, 300);
        $stmt = $this->pdo->prepare("INSERT INTO SWITCH (id_scenario, nom, pos_x, pos_y) VALUES (:sid, :nom, :x, :y) RETURNING id_switch");
        $stmt->execute([':sid' => $scenario_id, ':nom' => $nom, ':x' => $x, ':y' => $y]);
        return (int) $stmt->fetchColumn();
    }

    public function renommer(int $id, string $nom): bool {
        return $this->pdo->prepare("UPDATE SWITCH SET nom = :nom WHERE id_switch = :id")->execute([':nom' => trim($nom), ':id' => $id]);
    }

    public function mettreAJourPosition(int $id, int $x, int $y): bool {
        return $this->pdo->prepare("UPDATE SWITCH SET pos_x = :x, pos_y = :y WHERE id_switch = :id")->execute([':x' => $x, ':y' => $y, ':id' => $id]);
    }

    public function supprimer(int $id): bool {
        return $this->pdo->prepare("DELETE FROM SWITCH WHERE id_switch = ?")->execute([$id]);
    }
}
?>