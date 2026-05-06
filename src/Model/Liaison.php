<?php
/**
 * src/Model/Liaison.php
 * Implémentation stricte WBS 1.1 - Tables d'associations (N:M)
 */
class Liaison {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerLiaisonsHoteSwitch(int $sid): array {
        $stmt = $this->pdo->prepare("
            SELECT lhs.hote_id, lhs.switch_id 
            FROM liaison_hote_switch lhs
            JOIN hote h ON lhs.hote_id = h.id
            WHERE h.scenario_id = :sid
        ");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listerLiaisonsInterfaceSwitch(int $sid): array {
        $stmt = $this->pdo->prepare("
            SELECT lis.interface_id, lis.switch_id, ir.routeur_id 
            FROM liaison_interface_switch lis
            JOIN interface_routeur ir ON lis.interface_id = ir.id
            JOIN routeur r ON ir.routeur_id = r.id
            WHERE r.scenario_id = :sid
        ");
        $stmt->execute([':sid' => $sid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function creerLiaisonHoteSwitch(int $hote_id, int $switch_id): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO liaison_hote_switch (hote_id, switch_id) 
            VALUES (:hote_id, :switch_id)
        ");
        return $stmt->execute([':hote_id' => $hote_id, ':switch_id' => $switch_id]);
    }

    public function creerLiaisonInterfaceSwitch(int $interface_id, int $switch_id): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO liaison_interface_switch (interface_id, switch_id) 
            VALUES (:interface_id, :switch_id)
        ");
        return $stmt->execute([':interface_id' => $interface_id, ':switch_id' => $switch_id]);
    }
}