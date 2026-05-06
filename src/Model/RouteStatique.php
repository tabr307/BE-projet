<?php
// src/Model/RouteStatique.php
// Modèle pour la table route_statique (WBS 3.0 & 4.0)

namespace App\Model;

use App\Core\BaseDeDonnees;
use PDO;

class RouteStatique {
    private PDO $pdo;

    public function __construct() {
        // Récupération de la connexion via la classe Core
        $this->pdo = BaseDeDonnees::getConnexion();
    }

    // ==========================================================
    // LOGIQUE DEV 3 : ALGORITHME LONGUEUR DE PRÉFIXE (LPM)
    // ==========================================================

    // Détermine la meilleure route pour une IP de destination (WBS 4.0)
    public function determinerProchainSaut(int $routeur_id, string $ip_cible): ?string {
        // 1. On charge la table de routage du routeur en mémoire
        $routes = $this->listerParRouteur($routeur_id);
        
        $meilleurNextHop = null;
        $meilleurMasque = -1; // Utilisé pour comparer la précision des masques

        foreach ($routes as $route) {
            // Utilisation de ton CalculateurReseau (Opérations bitwise)
            $correspond = CalculateurReseau::estDansMemeReseau(
                $ip_cible, 
                $route['reseau_dest'], 
                $route['masque_dest']
            );

            if ($correspond) {
                // Algorithme LPM : si le masque est plus long, c'est une route plus précise
                if ($route['masque_dest'] > $meilleurMasque) {
                    $meilleurMasque = $route['masque_dest'];
                    $meilleurNextHop = $route['next_hop'];
                }
            }
        }

        return $meilleurNextHop; // Retourne l'IP du voisin ou rien si pas de route
    }

    // ==========================================================
    // LOGIQUE DEV 2 : ACTIONS SUR LA BASE DE DONNÉES
    // ==========================================================

    // Récupère toutes les routes d'un routeur spécifique
    public function listerParRouteur(int $routeur_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, reseau_dest, masque_dest, next_hop, routeur_id 
            FROM route_statique 
            WHERE routeur_id = :routeur_id
        ");
        $stmt->execute([':routeur_id' => $routeur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajoute une nouvelle route avec validation (Dev 3)
    public function ajouter(int $routeur_id, string $reseau_dest, int $masque_dest, string $next_hop): bool {
        // Validation Dev 3 : Vérifie si les IP sont correctes avant d'insérer
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

    // Supprime une route de la table
    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM route_statique WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}