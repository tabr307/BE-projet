<?php
/**
 * backend/modeles/Utilisateur.php
 * Modèle aligné sur le nouveau MLD (identifiant, mot_de_passe_hash).
 */
class Utilisateur {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Cherche un utilisateur par son pseudo.
     */
    public function trouverParNom(string $username): ?array {
        // FIX : On utilise la colonne 'identifiant' selon le nouveau schéma
        $stmt = $this->pdo->prepare("SELECT * FROM UTILISATEUR WHERE identifiant = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Inscrit un nouvel utilisateur (hachage SHA-256).
     */
    public function inscrire(string $username, string $password): bool {
        $hash = hash('sha256', $password);
        
        // FIX : On utilise 'identifiant' et 'mot_de_passe_hash'
        $stmt = $this->pdo->prepare("INSERT INTO UTILISATEUR (identifiant, mot_de_passe_hash, role) VALUES (:username, :hash, 'classique')");
        
        try {
            return $stmt->execute([
                ':username' => $username,
                ':hash' => $hash
            ]);
        } catch (PDOException $e) {
            // En cas de doublon (contrainte UNIQUE sur l'identifiant)
            return false;
        }
    }
}