<?php
// src/Model/InterfaceRouteur.php

class InterfaceRouteur {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function estDestinationDirecte(int $routeur_id, string $ip_cible): bool {
        $interfaces = $this->listerParRouteur($routeur_id);

        foreach ($interfaces as $interface) {
            if (CalculateurReseau::estDansMemeReseau($ip_cible, $interface['adresse_ip'], $interface['masque'])) {
                return true;
            }
        }
        return false;
    }

    public function listerParRouteur(int $routeur_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id, adresse_ip, masque, nom, routeur_id 
            FROM interface_routeur 
            WHERE routeur_id = :routeur_id
        ");
        $stmt->execute([':routeur_id' => $routeur_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouter(int $routeur_id, string $nom, string $adresse_ip, int $masque): int {
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
}