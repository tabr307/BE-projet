<?php
// src/Model/RouteStatique.php

/**
 * Classe RouteStatique
 * Gère la table de routage d'un routeur, permettant de définir manuellement 
 * le chemin (prochain saut) pour atteindre des réseaux distants.
 */
class RouteStatique {
    // Instance de connexion à la base de données
    private PDO $pdo;

    /**
     * Constructeur : Initialise la connexion PDO
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Analyse la table de routage pour trouver l'adresse du prochain routeur (Next Hop)
     * Utilise l'algorithme de correspondance la plus longue (Longest Prefix Match).
     * @param int $routeur_id ID du routeur effectuant l'analyse
     * @param string $ip_cible Adresse IP de destination du paquet
     * @return string|null L'IP du prochain saut ou null si aucune route n'est trouvée
     */
    public function determinerProchainSaut(int $routeur_id, string $ip_cible): ?string {
        $routes = $this->listerParRouteur($routeur_id);
        
        $meilleurNextHop = null;
        $meilleurMasque = -1;

        foreach ($routes as $route) {
            // Vérifie si l'IP cible appartient au réseau de la route actuelle
            $correspond = CalculateurReseau::estDansMemeReseau(
                $ip_cible, 
                $route['reseau_dest'], 
                $route['masque_dest']
            );

            if ($correspond) {
                // Algorithme LPM : On privilégie la route la plus précise (masque le plus élevé)
                if ($route['masque_dest'] > $meilleurMasque) {
                    $meilleurMasque = $route['masque_dest'];
                    $meilleurNextHop = $route['next_hop'];
                }
            }
        }
        return $meilleurNextHop;
    }

    /**
     * Récupère toutes les routes statiques configurées pour un routeur précis
     * @param int $routeur_id
     * @return array
     */
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

    /**
     * Ajoute une nouvelle route statique dans la table de routage
     * @param int $routeur_id ID du routeur source
     * @param string $reseau_dest Adresse du réseau de destination (ex: 10.0.0.0)
     * @param int $masque_dest Masque CIDR (ex: 8)
     * @param string $next_hop Adresse IP du routeur suivant
     * @return bool Succès ou échec de l'insertion
     */
    public function ajouter(int $routeur_id, string $reseau_dest, int $masque_dest, string $next_hop): bool {
        // Validation de la syntaxe des adresses IP
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

    /**
     * Supprime une route statique par son identifiant
     * @param int $id
     * @return bool
     */
    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM route_statique WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Modifie une route statique existante
     * @param int $id ID de la route à modifier
     * @param string $reseau_dest Nouvelle destination
     * @param int $masque_dest Nouveau masque
     * @param string $next_hop Nouveau prochain saut
     * @return bool Succès de la mise à jour
     */
    public function modifier(int $id, string $reseau_dest, int $masque_dest, string $next_hop): bool {
        // Validation des adresses IP avant modification
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