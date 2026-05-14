<?php
// src/Model/RouteStatique.php

class RouteStatique {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function determinerProchainSaut(int $routeur_id, string $ip_cible): ?string {
        $routes = $this->listerParRouteur($routeur_id);
        
        $meilleurNextHop = null;
        $meilleurMasque = -1;

        foreach ($routes as $route) {
            $correspond = CalculateurReseau::estDansMemeReseau(
                $ip_cible, 
                $route['reseau_dest'], 
                $route['masque_dest']
            );

            if ($correspond) {
                if ($route['masque_dest'] > $meilleurMasque) {
                    $meilleurMasque = $route['masque_dest'];
                    $meilleurNextHop = $route['next_hop'];
                }
            }
        }
        return $meilleurNextHop;
    }

    public function listerParRouteur(int $routeur_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, reseau_dest, masque_dest, next_hop, routeur_id 
            FROM route_statique 
            WHERE routeur_id = :routeur_id
            ORDER BY id ASC
        ");
        $stmt->execute([':routeur_id' => $routeur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouter(int $routeur_id, string $reseau_dest, int $masque_dest, string $next_hop): bool {
        if (!CalculateurReseau::validerIP($reseau_dest) || !CalculateurReseau::validerIP($next_hop)) {
            return false;
        }

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

    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM route_statique WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function modifier(int $id, string $reseau_dest, int $masque_dest, string $next_hop): bool {
        if (!CalculateurReseau::validerIP($reseau_dest) || !CalculateurReseau::validerIP($next_hop)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE route_statique 
            SET reseau_dest = :reseau_dest, masque_dest = :masque_dest, next_hop = :next_hop 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'          => $id,
            ':reseau_dest' => $reseau_dest,
            ':masque_dest' => $masque_dest,
            ':next_hop'    => $next_hop
        ]);
    }
}