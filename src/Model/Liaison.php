<?php
// src/Model/Liaison.php

/**
 * Classe Liaison
 * Gère la connectivité physique et logique entre les différents équipements 
 * (Hôtes, Switchs, Interfaces de Routeurs) au sein d'un scénario réseau.
 */
class Liaison {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie si un commutateur dispose encore de ports disponibles.
     * La limite est fixée arbitrairement à 24 ports.
     * @param int $switch_id
     * @return bool True si au moins un port est libre
     */
    public function switchADesPortsLibres(int $switch_id): bool {
        // Compte les liaisons provenant d'hôtes
        $stmtH = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_switch WHERE switch_id = ?");
        $stmtH->execute([$switch_id]);
        $nbHotes = $stmtH->fetchColumn();

        // Compte les liaisons provenant d'interfaces de routeurs
        $stmtI = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_interface_switch WHERE switch_id = ?");
        $stmtI->execute([$switch_id]);
        $nbInter = $stmtI->fetchColumn();

        return ($nbHotes + $nbInter) < 24;
    }

    /**
     * Vérifie si un hôte possède déjà une connexion filaire.
     * Un hôte ne peut être connecté qu'à un seul équipement à la fois.
     * @param int $hote_id
     * @return bool
     */
    public function hoteEstDejaCable(int $hote_id): bool {
        // Vérification vers un Switch
        $stmt1 = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_switch WHERE hote_id = ?");
        $stmt1->execute([$hote_id]);
        if ($stmt1->fetchColumn() > 0) return true;

        // Vérification vers une Interface de Routeur
        $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_interface WHERE hote_id = ?");
        $stmt2->execute([$hote_id]);
        if ($stmt2->fetchColumn() > 0) return true;

        // Vérification vers un autre Hôte (câble croisé)
        $stmt3 = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_hote WHERE hote_1_id = ? OR hote_2_id = ?");
        $stmt3->execute([$hote_id, $hote_id]);
        if ($stmt3->fetchColumn() > 0) return true;

        return false;
    }

    /**
     * Connecte un hôte à un switch.
     * @return array Statut de l'opération et message d'erreur éventuel
     */
    public function creerLiaisonHoteSwitch(int $hote_id, int $switch_id): array {
        if ($this->hoteEstDejaCable($hote_id)) {
            return ["success" => false, "erreur" => "Cet hôte possède déjà une liaison active."];
        }
        if (!$this->switchADesPortsLibres($switch_id)) {
            return ["success" => false, "erreur" => "Le switch est saturé (24 ports max)."];
        }

        $sql = "INSERT INTO liaison_hote_switch (hote_id, switch_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $res = $this->pdo->prepare($sql)->execute([$hote_id, $switch_id]);
        return ["success" => $res];
    }

    /**
     * Connecte une interface de routeur à un switch.
     */
    public function creerLiaisonInterfaceSwitch(int $interface_id, int $switch_id): array {
        if (!$this->switchADesPortsLibres($switch_id)) {
            return ["success" => false, "erreur" => "Le switch est saturé."];
        }

        $sql = "INSERT INTO liaison_interface_switch (interface_id, switch_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $res = $this->pdo->prepare($sql)->execute([$interface_id, $switch_id]);
        return ["success" => $res];
    }

    /**
     * Liste toutes les liaisons Hôte-Switch pour un scénario donné.
     */
    public function listerLiaisonsHoteSwitch(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT lhs.hote_id AS hote_id, lhs.switch_id AS switch_id 
            FROM liaison_hote_switch lhs 
            JOIN hote h ON lhs.hote_id = h.id 
            WHERE h.scenario_id = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste toutes les liaisons entre Interfaces de Routeurs et Switchs pour un scénario donné.
     */
    public function listerLiaisonsInterfaceSwitch(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT lis.interface_id AS interface_id, lis.switch_id AS switch_id, ir.routeur_id AS routeur_id 
            FROM liaison_interface_switch lis 
            JOIN interface_routeur ir ON lis.interface_id = ir.id 
            JOIN routeur r ON ir.routeur_id = r.id 
            WHERE r.scenario_id = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Connecte directement un hôte à une interface de routeur.
     */
    public function creerLiaisonHoteInterface(int $hote_id, int $interface_id): array {
        if ($this->hoteEstDejaCable($hote_id)) {
            return ["success" => false, "erreur" => "Cet hôte possède déjà une liaison active."];
        }
        $sql = "INSERT INTO liaison_hote_interface (hote_id, interface_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $res = $this->pdo->prepare($sql)->execute([$hote_id, $interface_id]);
        return ["success" => $res];
    }

    /**
     * Connecte deux hôtes directement entre eux (Câble croisé).
     * Trie les IDs pour garantir l'unicité du couple en base.
     */
    public function creerLiaisonHoteHote(int $hote_1_id, int $hote_2_id): array {
        if ($this->hoteEstDejaCable($hote_1_id) || $this->hoteEstDejaCable($hote_2_id)) {
            return ["success" => false, "erreur" => "L'un des hôtes possède déjà une liaison active."];
        }
        $sql = "INSERT INTO liaison_hote_hote (hote_1_id, hote_2_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        // Prévention des doublons : on stocke toujours le plus petit ID en premier
        $id_min = min($hote_1_id, $hote_2_id);
        $id_max = max($hote_1_id, $hote_2_id);
        $res = $this->pdo->prepare($sql)->execute([$id_min, $id_max]);
        return ["success" => $res];
    }

    /**
     * Liste les liaisons directes Hôte-Interface pour un scénario.
     */
    public function listerLiaisonsHoteInterface(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT lhi.hote_id AS hote_id, lhi.interface_id AS interface_id, ir.routeur_id AS routeur_id 
            FROM liaison_hote_interface lhi
            JOIN hote h ON lhi.hote_id = h.id 
            JOIN interface_routeur ir ON lhi.interface_id = ir.id
            WHERE h.scenario_id = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste les liaisons directes entre deux hôtes pour un scénario.
     */
    public function listerLiaisonsHoteHote(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT lhh.hote_1_id AS hote_1_id, lhh.hote_2_id AS hote_2_id
            FROM liaison_hote_hote lhh
            JOIN hote h ON lhh.hote_1_id = h.id 
            WHERE h.scenario_id = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste les liaisons directes entre deux interfaces de routeurs (Liaison série ou directe).
     */
    public function listerLiaisonsInterfaceInterface(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT lii.interface_id AS interface_id, lii.interface_1_id AS interface_1_id, ir.routeur_id AS routeur_id, ir1.routeur_id AS routeur_1_id
            FROM liaison_interface_interface lii
            JOIN interface_routeur ir ON lii.interface_id = ir.id 
            JOIN interface_routeur ir1 ON lii.interface_1_id = ir1.id
            JOIN routeur r ON ir.routeur_id = r.id
            WHERE r.scenario_id = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Connecte deux interfaces de routeur entre elles.
     */
    public function creerLiaisonInterfaceInterface(int $interface_1_id, int $interface_2_id): array {
        $sql = "INSERT INTO liaison_interface_interface (interface_id, interface_1_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $id_min = min($interface_1_id, $interface_2_id);
        $id_max = max($interface_1_id, $interface_2_id);
        $res = $this->pdo->prepare($sql)->execute([$id_min, $id_max]);
        return ["success" => $res];
    }
}