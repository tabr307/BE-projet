<?php
class Hote {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerParScenario(int $sid): array {
        $stmt = $this->pdo->prepare("SELECT id, nom, adresse_ip, passerelle_ip, pos_x, pos_y, sous_reseau_id FROM hote WHERE scenario_id = ?");
        $stmt->execute([$sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouter(int $sid, string $nom, $x = 200, $y = 200): int {
        // Insertion simplifiée : les IPs sont gérées plus tard par la configuration
        $stmt = $this->pdo->prepare("INSERT INTO hote (scenario_id, nom, pos_x, pos_y) VALUES (?, ?, ?, ?) RETURNING id");
        $stmt->execute([$sid, $nom, $x, $y]);
        return (int)$stmt->fetchColumn();
    }

    public function renommer(int $id, string $nom): bool {
        return $this->pdo->prepare("UPDATE hote SET nom = ? WHERE id = ?")->execute([trim($nom), $id]);
    }

    public function mettreAJourPosition(int $id, int $x, int $y): bool {
        return $this->pdo->prepare("UPDATE hote SET pos_x = ?, pos_y = ? WHERE id = ?")->execute([$x, $y, $id]);
    }

    public function configurerReseau(int $id, string $ip, string $passerelle, ?int $sous_reseau_id): bool {
        $sql = "UPDATE hote SET adresse_ip = ?, passerelle_ip = ?, sous_reseau_id = ? WHERE id = ?";
        return $this->pdo->prepare($sql)->execute([$ip, $passerelle, $sous_reseau_id, $id]);
    }

    public function supprimer(int $id): bool {
        return $this->pdo->prepare("DELETE FROM hote WHERE id = ?")->execute([$id]);
    }
}