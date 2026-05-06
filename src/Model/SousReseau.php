<?php
/**
 * src/Model/SousReseau.php
 * Implémentation stricte WBS 1.1 - Table "sous_reseau"
 */
class SousReseau {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerParScenario(int $sid): array {
        $stmt = $this->pdo->prepare("
            SELECT id, nom, bloc_cidr 
            FROM sous_reseau 
            WHERE scenario_id = :sid
        ");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouter(int $sid, string $nom, string $bloc_cidr = '192.168.1.0/24'): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO sous_reseau (scenario_id, nom, bloc_cidr) 
            VALUES (:sid, :nom, :bloc_cidr) 
            RETURNING id
        ");
        $stmt->execute([
            ':sid' => $sid,
            ':nom' => trim($nom),
            ':bloc_cidr' => $bloc_cidr
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function renommer(int $id, string $nom): bool {
        $stmt = $this->pdo->prepare("UPDATE sous_reseau SET nom = :nom WHERE id = :id");
        return $stmt->execute([':nom' => trim($nom), ':id' => $id]);
    }

    public function modifierCidr(int $id, string $bloc_cidr): bool {
        $stmt = $this->pdo->prepare("UPDATE sous_reseau SET bloc_cidr = :bloc_cidr WHERE id = :id");
        return $stmt->execute([':bloc_cidr' => $bloc_cidr, ':id' => $id]);
    }

    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sous_reseau WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}