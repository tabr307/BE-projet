<?php
class Utilisateur {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function trouverParNom(string $username): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM utilisateur WHERE identifiant = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public function inscrire(string $username, string $password): bool {
        $hash = hash('sha256', $password);
        $stmt = $this->pdo->prepare("INSERT INTO utilisateur (identifiant, mot_de_passe_hash, role) VALUES (:username, :hash, 'membre')");
        
        try {
            return $stmt->execute([
                ':username' => $username,
                ':hash' => $hash
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function listerTous(): array {
        $stmt = $this->pdo->query("SELECT id, identifiant, role FROM utilisateur ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function supprimer(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM utilisateur WHERE id = :id AND role != 'admin'");
        return $stmt->execute([':id' => $id]);
    }
}
?>