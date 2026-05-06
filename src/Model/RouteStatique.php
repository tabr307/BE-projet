<?php
/**
 * src/Model/RouteStatique.php
 * Modèle CRUD pour la table route_statique (WBS 3.0)
 */

class RouteStatique {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Récupère toutes les routes statiques configurées sur un routeur spécifique.
     * Cette méthode est critique pour l'algorithme LPM du Dev 3.
     */
    public function listerParRouteur(int $routeur_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, reseau_dest, masque_dest, next_hop, routeur_id 
            FROM route_statique 
            WHERE routeur_id = :routeur_id
        ");
        $stmt->execute([':routeur_id' => $routeur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute une nouvelle route (Appelé par api.php lors d'un POST)
     */
    public function ajouter(int $routeur_id, string $reseau_dest, int $masque_dest, string $next_hop): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO route_statique (routeur_id, reseau_dest, masque_dest, next_hop) 
            VALUES (:routeur_id, :reseau_dest, :masque_dest, :next_hop)
        ");
        return $stmt->execute([
            ':routeur_id'  => $routeur_id,
            ':reseau_dest' => $reseau_dest,
            ':masque_dest' => $masque_dest,
            ':next_hop'    => $next_hop
        ]);
    }

    /**
     * Supprime une route
     */
    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM route_statique WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
?>