<?php
class Liaison {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function listerLiaisonsHoteSwitch(int $scenario_id): array {
        $stmt = $this->pdo->prepare("
            SELECT chs.id_hote AS hote_id, chs.id_switch AS switch_id 
            FROM CABLER_HOTE_SWITCH chs 
            JOIN HOTE h ON chs.id_hote = h.id_hote 
            WHERE h.id_scenario = :sid
        ");
        $stmt->execute([':sid' => $scenario_id]);
        return $stmt->fetchAll();
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
        return $stmt->fetchAll();
    }

    public function cablerHoteSwitch(int $hote_id, int $switch_id): bool {
        $sql = "INSERT INTO CABLER_HOTE_SWITCH (id_hote, id_switch) VALUES (?, ?) ON CONFLICT (id_switch, id_hote) DO NOTHING";
        return $this->pdo->prepare($sql)->execute([$hote_id, $switch_id]);
    }

    public function decablerHoteSwitch(int $hote_id, int $switch_id): bool {
        return $this->pdo->prepare("DELETE FROM CABLER_HOTE_SWITCH WHERE id_hote = ? AND id_switch = ?")->execute([$hote_id, $switch_id]);
    }

    public function cablerInterfaceSwitch(int $interface_id, int $switch_id): bool {
        $sql = "INSERT INTO CABLER_INTERFACE_SWITCH (id_interface, id_switch) VALUES (?, ?) ON CONFLICT (id_interface, id_switch) DO NOTHING";
        return $this->pdo->prepare($sql)->execute([$interface_id, $switch_id]);
    }

    public function decablerInterfaceSwitch(int $interface_id, int $switch_id): bool {
        return $this->pdo->prepare("DELETE FROM CABLER_INTERFACE_SWITCH WHERE id_interface = ? AND id_switch = ?")->execute([$interface_id, $switch_id]);
    }
}
?>