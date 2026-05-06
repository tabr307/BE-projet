<?php
// src/Model/InterfaceRouteur.php
// Modèle pour la table interface_routeur (WBS 3.0 & 4.0)

namespace App\Model;

use App\Core\BaseDeDonnees;
use PDO;

class InterfaceRouteur {
    private PDO $pdo;

    public function __construct() {
        // Récupération de la connexion via la classe Core
        $this->pdo = BaseDeDonnees::getConnexion();
    }

    // ==========================================================
    // LOGIQUE DEV 3 : VÉRIFICATION RÉSEAU (WBS 4.0)
    // ==========================================================

    // Vérifie si une IP cible appartient à un réseau directement branché au routeur
    public function estDestinationDirecte(int $routeur_id, string $ip_cible): bool {
        // On récupère les interfaces physiques du routeur
        $interfaces = $this->listerParRouteur($routeur_id);

        foreach ($interfaces as $interface) {
            // Utilisation du CalculateurReseau pour voir si l'IP est dans le sous-réseau de l'interface
            if (CalculateurReseau::estDansMemeReseau($ip_cible, $interface['adresse_ip'], $interface['masque'])) {
                return true;
            }
        }
        return false;
    }

    // ==========================================================
    // LOGIQUE DEV 2 : ACTIONS SUR LA BASE DE DONNÉES (CRUD)
    // ==========================================================

    // Récupère toutes les interfaces d'un routeur spécifique
    public function listerParRouteur(int $routeur_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, adresse_ip, masque, routeur_id 
            FROM interface_routeur 
            WHERE routeur_id = :routeur_id
        ");
        $stmt->execute([':routeur_id' => $routeur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajoute une nouvelle interface avec validation d'IP (Dev 3)
    public function ajouter(int $routeur_id, string $adresse_ip, int $masque): int {
        // Validation Dev 3 : On refuse l'insertion si l'IP n'est pas valide
        if (!CalculateurReseau::validerIP($adresse_ip)) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO interface_routeur (routeur_id, adresse_ip, masque) 
            VALUES (:routeur_id, :adresse_ip, :masque) RETURNING id
        ");
        $stmt->execute([
            ':routeur_id' => $routeur_id,
            ':adresse_ip' => $adresse_ip,
            ':masque'     => $masque
        ]);
        return (int) $stmt->fetchColumn();
    }
}