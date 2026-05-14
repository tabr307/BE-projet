<?php
/**
 * src/Model/Scenario.php
 * Modèle aligné sur le schéma WBS 1.1 (PostgreSQL/MySQL).
 */
class Scenario {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère la liste simplifiée des scénarios d'un utilisateur.
     */
    public function lireScenariosParUtilisateur(int $utilisateurId): array {
        $stmt = $this->pdo->prepare("
            SELECT id, nom, description 
            FROM scenario 
            WHERE utilisateur_id = :uid 
            ORDER BY id DESC
        ");
        $stmt->execute([':uid' => $utilisateurId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un scénario spécifique avec vérification de propriété.
     */
    public function obtenirScenario(int $id, int $utilisateurId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT id, nom, description 
            FROM scenario 
            WHERE id = :id AND utilisateur_id = :uid
        ");
        $stmt->execute([':id' => $id, ':uid' => $utilisateurId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Persistance d'un nouveau scénario.
     */
    public function creerScenario(int $utilisateurId, string $nom, string $description = ''): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO scenario (utilisateur_id, nom, description) 
            VALUES (:uid, :nom, :desc)
        ");
        
        $stmt->execute([
            ':uid'  => $utilisateurId, 
            ':nom'  => trim($nom),
            ':desc' => trim($description)
        ]);
        
        // Extraction de l'ID via le pilote PDO de manière agnostique
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Suppression d'un scénario.
     */
    public function supprimerScenario(int $id, int $utilisateurId): bool {
        $stmt = $this->pdo->prepare("
            DELETE FROM scenario 
            WHERE id = :id AND utilisateur_id = :uid
        ");
        return $stmt->execute([':id' => $id, ':uid' => $utilisateurId]);
    }

    /**
     * Administration : Liste les scénarios vides (sans routeurs, switchs, ni hôtes).
     */
    public function listerScenariosVides(): array {
        $stmt = $this->pdo->query("
            SELECT s.id, s.nom, u.identifiant as proprietaire
            FROM scenario s
            JOIN utilisateur u ON s.utilisateur_id = u.id
            LEFT JOIN routeur r ON r.scenario_id = s.id
            LEFT JOIN switch sw ON sw.scenario_id = s.id
            LEFT JOIN hote h ON h.scenario_id = s.id
            WHERE r.id IS NULL AND sw.id IS NULL AND h.id IS NULL
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Administration : Supprime tous les scénarios vides.
     */
    public function purgerScenariosVides(): int {
        $vides = $this->listerScenariosVides();
        $count = 0;
        foreach ($vides as $v) {
            $stmt = $this->pdo->prepare("DELETE FROM scenario WHERE id = ?");
            if ($stmt->execute([$v['id']])) {
                $count++;
            }
        }
        return $count;
    }
}