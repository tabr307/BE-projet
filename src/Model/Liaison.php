<?php
// src/Model/Liaison.php

class Liaison {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function switchADesPortsLibres(int $switch_id): bool {
        $stmtH = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_switch WHERE switch_id = ?");
        $stmtH->execute([$switch_id]);
        $nbHotes = $stmtH->fetchColumn();

        $stmtI = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_interface_switch WHERE switch_id = ?");
        $stmtI->execute([$switch_id]);
        $nbInter = $stmtI->fetchColumn();

        return ($nbHotes + $nbInter) < 24;
    }

    public function hoteEstDejaCable(int $hote_id): bool {
        $stmt1 = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_switch WHERE hote_id = ?");
        $stmt1->execute([$hote_id]);
        if ($stmt1->fetchColumn() > 0) return true;

        $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_interface WHERE hote_id = ?");
        $stmt2->execute([$hote_id]);
        if ($stmt2->fetchColumn() > 0) return true;

        $stmt3 = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_hote WHERE hote_1_id = ? OR hote_2_id = ?");
        $stmt3->execute([$hote_id, $hote_id]);
        if ($stmt3->fetchColumn() > 0) return true;

        return false;
    }

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

    public function creerLiaisonInterfaceSwitch(int $interface_id, int $switch_id): array {
        if (!$this->switchADesPortsLibres($switch_id)) {
            return ["success" => false, "erreur" => "Le switch est saturé."];
        }

        $sql = "INSERT INTO liaison_interface_switch (interface_id, switch_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $res = $this->pdo->prepare($sql)->execute([$interface_id, $switch_id]);
        return ["success" => $res];
    }

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

    public function creerLiaisonHoteInterface(int $hote_id, int $interface_id): array {
        if ($this->hoteEstDejaCable($hote_id)) {
            return ["success" => false, "erreur" => "Cet hôte possède déjà une liaison active."];
        }
        $sql = "INSERT INTO liaison_hote_interface (hote_id, interface_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $res = $this->pdo->prepare($sql)->execute([$hote_id, $interface_id]);
        return ["success" => $res];
    }

    public function creerLiaisonHoteHote(int $hote_1_id, int $hote_2_id): array {
        if ($this->hoteEstDejaCable($hote_1_id) || $this->hoteEstDejaCable($hote_2_id)) {
            return ["success" => false, "erreur" => "L'un des hôtes possède déjà une liaison active."];
        }
        $sql = "INSERT INTO liaison_hote_hote (hote_1_id, hote_2_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        // On trie les IDs pour éviter les doublons inversés si jamais
        $id_min = min($hote_1_id, $hote_2_id);
        $id_max = max($hote_1_id, $hote_2_id);
        $res = $this->pdo->prepare($sql)->execute([$id_min, $id_max]);
        return ["success" => $res];
    }

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

    public function creerLiaisonInterfaceInterface(int $interface_1_id, int $interface_2_id): array {
        $sql = "INSERT INTO liaison_interface_interface (interface_id, interface_1_id) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $id_min = min($interface_1_id, $interface_2_id);
        $id_max = max($interface_1_id, $interface_2_id);
        $res = $this->pdo->prepare($sql)->execute([$id_min, $id_max]);
        return ["success" => $res];
    }
}