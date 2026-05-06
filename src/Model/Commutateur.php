<?php
class Commutateur {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerParScenario(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT id, nom, pos_x, pos_y FROM switch WHERE scenario_id = ?");
        $stmt->execute([$sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouter(int $sid, string $nom, $x = 150, $y = 150): int {
        $stmt = $this->pdo->prepare("INSERT INTO switch (scenario_id, nom, pos_x, pos_y) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$sid, $nom, $x, $y]);
        return (int)$stmt->fetchColumn();
    }

    public function renommer(int $id, string $nom): bool {
        return $this->pdo->prepare("UPDATE switch SET nom = ? WHERE id = ?")->execute([trim($nom), $id]);
    }

    public function mettreAJourPosition(int $id, int $x, int $y): bool {
        return $this->pdo->prepare("UPDATE switch SET pos_x = ?, pos_y = ? WHERE id = ?")->execute([$x, $y, $id]);
    }

    public function supprimer(int $id): bool {
        return $this->pdo->prepare("DELETE FROM switch WHERE id = ?")->execute([$id]);
    }
}