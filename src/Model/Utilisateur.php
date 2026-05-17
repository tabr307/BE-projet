<?php

/**
 * Classe Utilisateur
 * Gère l'authentification, l'inscription et l'administration des comptes utilisateurs.
 */
class Utilisateur {
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
     * Recherche un utilisateur par son identifiant unique
     * @param string $username Nom d'utilisateur
     * @return array|null Données de l'utilisateur ou null si non trouvé
     */
    public function trouverParNom(string $username): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM utilisateur WHERE identifiant = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Crée un nouveau compte utilisateur avec le rôle 'membre'
     * @param string $username Identifiant choisi
     * @param string $password Mot de passe en clair (sera haché)
     * @return bool True en cas de succès, False si l'identifiant existe déjà ou erreur SQL
     */
    public function inscrire(string $username, string $password): bool {
        // Hachage du mot de passe (Note : SHA256 est utilisé ici, 
        // mais password_hash() est généralement recommandé pour une meilleure sécurité)
        $hash = hash('sha256', $password);
        $stmt = $this->pdo->prepare("INSERT INTO utilisateur (identifiant, mot_de_passe_hash, role) VALUES (:username, :hash, 'membre')");
        
        try {
            return $stmt->execute([
                ':username' => $username,
                ':hash' => $hash
            ]);
        } catch (PDOException $e) {
            // Gestion d'erreur (ex: violation de contrainte d'unicité sur l'identifiant)
            return false;
        }
    }

    /**
     * Récupère la liste de tous les utilisateurs inscrits
     * @return array Tableau contenant l'id, l'identifiant et le rôle de chaque utilisateur
     */
    public function listerTous(): array {
        $stmt = $this->pdo->query("SELECT id, identifiant, role FROM utilisateur ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime un compte utilisateur par son ID
     * Sécurité : Empêche la suppression des comptes ayant le rôle 'admin'
     * @param int $id ID de l'utilisateur à supprimer
     * @return bool Succès ou échec de la suppression
     */
    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM utilisateur WHERE id = :id AND role != 'admin'");
        return $stmt->execute([':id' => $id]);
    }
}
?>