<?php
class Hote {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerParScenario(int $scenario_id): array {
        $stmt = $this->pdo->prepare("SELECT id_hote AS id, nom, pos_x, pos_y, adresse_ip FROM HOTE WHERE id_scenario = :sid");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll();
    }

    public function ajouter(int $scenario_id, int $reseau_id, string $nom, string $ip): int {
        $x = rand(250, 400); 
        $y = rand(250, 400);
        $stmt = $this->pdo->prepare("INSERT INTO HOTE (id_scenario, id_reseau, nom, adresse_ip, pos_x, pos_y) VALUES (:sid, :srid, :nom, :ip, :x, :y) RETURNING id_hote");
        $stmt->execute([':sid' => $scenario_id, ':srid' => $reseau_id, ':nom' => $nom, ':ip' => $ip, ':x' => $x, ':y' => $y]);
        return (int) $stmt->fetchColumn();
    }

    public function renommer(int $id, string $nom): bool {
        return $this->pdo->prepare("UPDATE HOTE SET nom = :nom WHERE id_hote = :id")->execute([':nom' => trim($nom), ':id' => $id]);
    }

    public function mettreAJourPosition(int $id, int $x, int $y): bool {
        return $this->pdo->prepare("UPDATE HOTE SET pos_x = :x, pos_y = :y WHERE id_hote = :id")->execute([':x' => $x, ':y' => $y, ':id' => $id]);
    }

    public function supprimer(int $id): bool {
        return $this->pdo->prepare("DELETE FROM HOTE WHERE id_hote = ?")->execute([$id]);
    }
}
?>