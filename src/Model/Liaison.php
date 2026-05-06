<?php
namespace App\Model;

use App\Core\BaseDeDonnees;
use PDO;

class Liaison {
    private PDO $pdo;

    public function __construct() {
        // On récupère la connexion via ta classe Core réparée
        $this->pdo = BaseDeDonnees::getConnexion();
    }

    // ==========================================================
    // LOGIQUE DEV 3 : VÉRIFICATION DES RÈGLES RÉSEAU
    // ==========================================================

    //Vérifie si un Switch a encore des ports libres (Limite fixée à 24)

    public function switchADesPortsLibres(int $switch_id): bool {
        // 1. Compter les hôtes branchés
        $sqlHotes = "SELECT COUNT(*) FROM CABLER_HOTE_SWITCH WHERE id_switch = ?";
        $stmtH = $this->pdo->prepare($sqlHotes);
        $stmtH->execute([$switch_id]);
        $nbHotes = $stmtH->fetchColumn();

        // 2. Compter les interfaces routeurs branchées
        $sqlInter = "SELECT COUNT(*) FROM CABLER_INTERFACE_SWITCH WHERE id_switch = ?";
        $stmtI = $this->pdo->prepare($sqlInter);
        $stmtI->execute([$switch_id]);
        $nbInter = $stmtI->fetchColumn();

        // Total physique (on considère un switch 24 ports standard)
        return ($nbHotes + $nbInter) < 24;
    }

    // Vérifie si un Hôte est déjà relié

    public function hoteEstDejaCable(int $id_hote): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM CABLER_HOTE_SWITCH WHERE id_hote = ?");
        $stmt->execute([$id_hote]);
        return $stmt->fetchColumn() > 0;
    }

    // ==========================================================
    // LOGIQUE DEV 2 : ACTIONS SQL (MODIFIÉES PAR DEV 3)
    // ==========================================================

    public function cablerHoteSwitch(int $hote_id, int $switch_id): array {
        // Sécurité DEV 3 : On empêche le câblage si c'est physiquement impossible
        if ($this->hoteEstDejaCable($hote_id)) {
            return ["success" => false, "erreur" => "Cet hôte possède déjà une liaison active."];
        }
        if (!$this->switchADesPortsLibres($switch_id)) {
            return ["success" => false, "erreur" => "Le switch est saturé (24 ports max)."];
        }

        $sql = "INSERT INTO CABLER_HOTE_SWITCH (id_hote, id_switch) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $res = $this->pdo->prepare($sql)->execute([$hote_id, $switch_id]);
        return ["success" => $res];
    }

    public function cablerInterfaceSwitch(int $interface_id, int $switch_id): array {
        // Sécurité DEV 3
        if (!$this->switchADesPortsLibres($switch_id)) {
            return ["success" => false, "erreur" => "Le switch est saturé."];
        }

        $sql = "INSERT INTO CABLER_INTERFACE_SWITCH (id_interface, id_switch) VALUES (?, ?) ON CONFLICT DO NOTHING";
        $res = $this->pdo->prepare($sql)->execute([$interface_id, $switch_id]);
        return ["success" => $res];
    }

    public function listerLiaisonsHoteSwitch(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT chs.id_hote AS hote_id, chs.id_switch AS switch_id 
            FROM CABLER_HOTE_SWITCH chs 
            JOIN HOTE h ON chs.id_hote = h.id_hote 
            WHERE h.id_scenario = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listerLiaisonsInterfaceSwitch(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT cis.id_interface AS interface_id, cis.id_switch AS switch_id, ir.id_routeur AS routeur_id 
            FROM CABLER_INTERFACE_SWITCH cis 
            JOIN INTERFACE ir ON cis.id_interface = ir.id_interface 
            JOIN Routeur r ON ir.id_routeur = r.id_routeur 
            WHERE r.id_scenario = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decablerHoteSwitch(int $hote_id, int $switch_id): bool {
        return $this->pdo->prepare("DELETE FROM CABLER_HOTE_SWITCH WHERE id_hote = ? AND id_switch = ?")->execute([$hote_id, $switch_id]);
    }

    public function decablerInterfaceSwitch(int $interface_id, int $switch_id): bool {
        return $this->pdo->prepare("DELETE FROM CABLER_INTERFACE_SWITCH WHERE id_interface = ? AND id_switch = ?")->execute([$interface_id, $switch_id]);
    }
}