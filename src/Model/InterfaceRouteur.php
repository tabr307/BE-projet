<?php
class InterfaceRouteur {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerParRouteur(int $routeur_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, adresse_ip, masque, routeur_id 
            FROM interface_routeur 
            WHERE routeur_id = :routeur_id
        ");
        $stmt->execute([':routeur_id' => $routeur_id]);
        return $stmt->fetchAll();
    }

    public function ajouter(int $routeur_id, string $adresse_ip, int $masque): int {
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
?>