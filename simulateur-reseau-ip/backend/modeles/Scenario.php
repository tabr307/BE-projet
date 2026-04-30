<?php
// backend/modeles/Scenario.php

class Scenario {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ==============================================================================
    // WBS 1.4.2 : API CRUD - ENTITÉ ROUTEURS
    // ==============================================================================
    public function creerRouteur(int $scenarioId, string $nom): int {
        $sql = "INSERT INTO routeur (scenario_id, nom) VALUES (:scenario_id, :nom) RETURNING id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':scenario_id' => $scenarioId, ':nom' => trim($nom)]);
        return (int) $stmt->fetchColumn();
    }

    public function lireRouteurs(int $scenarioId): array {
        $sql = "SELECT id, nom FROM routeur WHERE scenario_id = :scenario_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':scenario_id' => $scenarioId]);
        return $stmt->fetchAll();
    }

    public function mettreAJourRouteur(int $id, string $nom): bool {
        $sql = "UPDATE routeur SET nom = :nom WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':nom' => trim($nom), ':id' => $id]);
    }

    public function supprimerRouteur(int $id): bool {
        $sql = "DELETE FROM routeur WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // ==============================================================================
    // WBS 1.4.3 : API CRUD - ENTITÉ SWITCHS
    // ==============================================================================
    public function creerSwitch(int $sousReseauId, string $nom): int {
        $sql = "INSERT INTO switch (sous_reseau_id, nom) VALUES (:sous_reseau_id, :nom) RETURNING id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sous_reseau_id' => $sousReseauId, ':nom' => trim($nom)]);
        return (int) $stmt->fetchColumn();
    }

    public function lireSwitchs(int $sousReseauId): array {
        $sql = "SELECT id, nom FROM switch WHERE sous_reseau_id = :sous_reseau_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sous_reseau_id' => $sousReseauId]);
        return $stmt->fetchAll();
    }

    public function mettreAJourSwitch(int $id, string $nom): bool {
        $sql = "UPDATE switch SET nom = :nom WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':nom' => trim($nom), ':id' => $id]);
    }

    public function supprimerSwitch(int $id): bool {
        $sql = "DELETE FROM switch WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // ==============================================================================
    // WBS 1.4.4 : API CRUD - ENTITÉ SOUS-RÉSEAUX
    // ==============================================================================
    public function creerSousReseau(int $scenarioId, string $nom, string $blocCidr): int {
        $sql = "INSERT INTO sous_reseau (scenario_id, nom, bloc_cidr) VALUES (:scenario_id, :nom, :bloc_cidr) RETURNING id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':scenario_id' => $scenarioId, 
            ':nom' => trim($nom), 
            ':bloc_cidr' => trim($blocCidr)
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function lireSousReseaux(int $scenarioId): array {
        $sql = "SELECT id, nom, bloc_cidr FROM sous_reseau WHERE scenario_id = :scenario_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':scenario_id' => $scenarioId]);
        return $stmt->fetchAll();
    }

    public function mettreAJourSousReseau(int $id, string $nom, string $blocCidr): bool {
        $sql = "UPDATE sous_reseau SET nom = :nom, bloc_cidr = :bloc_cidr WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nom' => trim($nom), 
            ':bloc_cidr' => trim($blocCidr), 
            ':id' => $id
        ]);
    }

    public function supprimerSousReseau(int $id): bool {
        $sql = "DELETE FROM sous_reseau WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    // ==============================================================================
    // WBS 1.4.5 : API CRUD - ENTITÉ HÔTES
    // ==============================================================================
    public function creerHote(int $sousReseauId, string $nom, string $adresseIp, ?string $passerelleDefaut): int {
        $sql = "INSERT INTO hote (sous_reseau_id, nom, adresse_ip, passerelle_defaut) VALUES (:sous_reseau_id, :nom, :adresse_ip, :passerelle_defaut) RETURNING id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sous_reseau_id' => $sousReseauId,
            ':nom' => trim($nom),
            ':adresse_ip' => trim($adresseIp),
            ':passerelle_defaut' => $passerelleDefaut ? trim($passerelleDefaut) : null
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function lireHotes(int $sousReseauId): array {
        $sql = "SELECT id, nom, adresse_ip, passerelle_defaut FROM hote WHERE sous_reseau_id = :sous_reseau_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sous_reseau_id' => $sousReseauId]);
        return $stmt->fetchAll();
    }

    public function mettreAJourHote(int $id, string $nom, string $adresseIp, ?string $passerelleDefaut): bool {
        $sql = "UPDATE hote SET nom = :nom, adresse_ip = :adresse_ip, passerelle_defaut = :passerelle_defaut WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':nom' => trim($nom),
            ':adresse_ip' => trim($adresseIp),
            ':passerelle_defaut' => $passerelleDefaut ? trim($passerelleDefaut) : null,
            ':id' => $id
        ]);
    }

    public function supprimerHote(int $id): bool {
        $sql = "DELETE FROM hote WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}