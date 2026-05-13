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
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM liaison_hote_switch WHERE hote_id = ?");
        $stmt->execute([$hote_id]);
        return $stmt->fetchColumn() > 0;
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
}