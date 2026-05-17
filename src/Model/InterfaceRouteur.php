<?php
// src/Model/InterfaceRouteur.php

/**
 * Classe InterfaceRouteur
 * Gère la configuration et la logique métier des interfaces physiques/logiques d'un routeur.
 */
class InterfaceRouteur {
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
     * Vérifie si une adresse IP cible appartient à l'un des réseaux 
     * directement connectés aux interfaces du routeur.
     * @param int $routeur_id ID du routeur à tester
     * @param string $ip_cible L'adresse IP de destination
     * @return bool True si la destination est sur un réseau local au routeur
     */
    public function estDestinationDirecte(int $routeur_id, string $ip_cible): bool {
        // Récupération de toutes les interfaces configurées pour ce routeur
        $interfaces = $this->listerParRouteur($routeur_id);

        foreach ($interfaces as $interface) {
            // Utilisation d'une classe utilitaire pour comparer les réseaux (IP + Masque)
            if (CalculateurReseau::estDansMemeReseau($ip_cible, $interface['adresse_ip'], $interface['masque'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Liste toutes les interfaces configurées pour un routeur donné
     * @param int $routeur_id
     * @return array Tableau associatif des interfaces (id, ip, masque, nom)
     */
    public function listerParRouteur(int $routeur_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, adresse_ip, masque, nom, routeur_id 
            FROM interface_routeur 
            WHERE routeur_id = :routeur_id
        ");
        $stmt->execute([':routeur_id' => $routeur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute une nouvelle interface à un routeur
     * @param int $routeur_id ID du routeur parent
     * @param string $nom Nom de l'interface (ex: eth0, gigabit0/1)
     * @param string $adresse_ip Adresse IPv4
     * @param int $masque Masque de sous-réseau (format CIDR, ex: 24)
     * @return int L'identifiant de l'interface créée ou 0 en cas d'erreur de validation
     */
    public function ajouter(int $routeur_id, string $nom, string $adresse_ip, int $masque): int {
        // Validation formatage IP avant insertion
        if (!CalculateurReseau::validerIP($adresse_ip)) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO interface_routeur (routeur_id, nom, adresse_ip, masque) 
            VALUES (:routeur_id, :nom, :adresse_ip, :masque) RETURNING id
        ");
        $stmt->execute([
            ':routeur_id' => $routeur_id,
            ':nom'        => trim($nom),
            ':adresse_ip' => $adresse_ip,
            ':masque'     => $masque
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Modifie les paramètres d'une interface existante
     * @param int $id ID de l'interface à modifier
     * @param string $nom Nouveau nom
     * @param string $adresse_ip Nouvelle IP
     * @param int $masque Nouveau masque
     * @return bool Succès ou échec de la mise à jour
     */
    public function modifier(int $id, string $nom, string $adresse_ip, int $masque): bool {
        // Validation formatage IP avant mise à jour
        if (!CalculateurReseau::validerIP($adresse_ip)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE interface_routeur 
            SET nom = :nom, adresse_ip = :adresse_ip, masque = :masque 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'         => $id,
            ':nom'        => trim($nom),
            ':adresse_ip' => $adresse_ip,
            ':masque'     => $masque
        ]);
    }
}