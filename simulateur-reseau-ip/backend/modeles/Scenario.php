<?php
// =============================================================================
// MODÈLE : Scenario
// Auteur : Étudiant
// Description : Opérations CRUD sur l'ensemble des entités d'un scénario
//               (routeurs, interfaces, routes, switchs, réseaux, hôtes,
//               câblages). Isolé par utilisateur (multi-tenancy).
// =============================================================================

require_once __DIR__ . '/../noyau/BaseDeDonnees.php';

class Scenario {

    /** @var PDO Instance de connexion */
    private PDO $pdo;

    public function __construct() {
        $this->pdo = BaseDeDonnees::getInstance();
    }

    // =========================================================================
    // SCÉNARIOS
    // =========================================================================

    /**
     * Retourne tous les scénarios d'un utilisateur (isolation multi-tenancy).
     *
     * @param int $idUser Identifiant de l'utilisateur connecté
     * @return array Liste des scénarios
     */
    public function listerParUtilisateur(int $idUser): array {
        $requete = $this->pdo->prepare(
            'SELECT id_scenario, nom_scenario, description
             FROM SCENARIO
             WHERE id_user = :id_user
             ORDER BY id_scenario DESC'
        );
        $requete->execute([':id_user' => $idUser]);
        return $requete->fetchAll();
    }

    /**
     * Vérifie qu'un scénario appartient bien à l'utilisateur donné.
     * Sécurité : empêche l'accès aux scénarios d'autres utilisateurs.
     *
     * @param int $idScenario Identifiant du scénario
     * @param int $idUser     Identifiant de l'utilisateur
     * @return bool True si le scénario appartient à l'utilisateur
     */
    public function appartientA(int $idScenario, int $idUser): bool {
        $requete = $this->pdo->prepare(
            'SELECT 1 FROM SCENARIO
             WHERE id_scenario = :id_scenario AND id_user = :id_user'
        );
        $requete->execute([
            ':id_scenario' => $idScenario,
            ':id_user'     => $idUser,
        ]);
        return (bool)$requete->fetch();
    }

    /**
     * Crée un nouveau scénario vide pour un utilisateur.
     *
     * @param string $nom         Nom du scénario
     * @param string $description Description optionnelle
     * @param int    $idUser      Propriétaire du scénario
     * @return int Identifiant du scénario créé
     */
    public function creer(string $nom, string $description, int $idUser): int {
        $requete = $this->pdo->prepare(
            'INSERT INTO SCENARIO (nom_scenario, description, id_user)
             VALUES (:nom, :desc, :id_user)
             RETURNING id_scenario'
        );
        $requete->execute([
            ':nom'     => $nom,
            ':desc'    => $description,
            ':id_user' => $idUser,
        ]);
        return (int)$requete->fetchColumn();
    }

    /**
     * Supprime un scénario et toutes ses entités (cascade en BDD).
     *
     * @param int $idScenario Identifiant du scénario
     * @param int $idUser     Vérification d'appartenance
     * @return bool True si la suppression a réussi
     */
    public function supprimer(int $idScenario, int $idUser): bool {
        if (!$this->appartientA($idScenario, $idUser)) {
            return false;
        }

        $requete = $this->pdo->prepare(
            'DELETE FROM SCENARIO WHERE id_scenario = :id AND id_user = :id_user'
        );
        $requete->execute([
            ':id'      => $idScenario,
            ':id_user' => $idUser,
        ]);
        return true;
    }

    // =========================================================================
    // TOPOLOGIE COMPLÈTE (pour le rendu vis.js)
    // =========================================================================

    /**
     * Charge la topologie complète d'un scénario pour l'affichage vis.js.
     * Retourne tous les nœuds et liens nécessaires au frontend.
     *
     * @param int $idScenario Identifiant du scénario
     * @return array Topologie avec nœuds et liens
     */
    public function chargerTopologie(int $idScenario): array {
        return [
            'routeurs'   => $this->listerRouteurs($idScenario),
            'interfaces' => $this->listerInterfaces($idScenario),
            'routes'     => $this->listerRoutes($idScenario),
            'switchs'    => $this->listerSwitchs($idScenario),
            'reseaux'    => $this->listerReseaux($idScenario),
            'hotes'      => $this->listerHotes($idScenario),
            'cables'     => [
                'hote_switch'        => $this->listerCablesHoteSwitch($idScenario),
                'interface_switch'   => $this->listerCablesInterfaceSwitch($idScenario),
                'interface_interface'=> $this->listerCablesInterfaceInterface($idScenario),
            ],
        ];
    }

    // =========================================================================
    // ROUTEURS
    // =========================================================================

    public function listerRouteurs(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT id_routeur, nom, pos_x, pos_y FROM Routeur
             WHERE id_scenario = :id ORDER BY id_routeur'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function ajouterRouteur(int $idScenario, string $nom, float $x = 0, float $y = 0): int {
        $r = $this->pdo->prepare(
            'INSERT INTO Routeur (nom, pos_x, pos_y, id_scenario)
             VALUES (:nom, :x, :y, :id) RETURNING id_routeur'
        );
        $r->execute([':nom' => $nom, ':x' => $x, ':y' => $y, ':id' => $idScenario]);
        return (int)$r->fetchColumn();
    }

    public function modifierRouteur(int $idRouteur, string $nom): bool {
        $r = $this->pdo->prepare(
            'UPDATE Routeur SET nom = :nom WHERE id_routeur = :id'
        );
        return $r->execute([':nom' => $nom, ':id' => $idRouteur]);
    }

    public function mettreAJourPositionRouteur(int $idRouteur, float $x, float $y): bool {
        $r = $this->pdo->prepare(
            'UPDATE Routeur SET pos_x = :x, pos_y = :y WHERE id_routeur = :id'
        );
        return $r->execute([':x' => $x, ':y' => $y, ':id' => $idRouteur]);
    }

    public function supprimerRouteur(int $idRouteur): bool {
        // La cascade en BDD supprime les interfaces et routes associées
        $r = $this->pdo->prepare('DELETE FROM Routeur WHERE id_routeur = :id');
        return $r->execute([':id' => $idRouteur]);
    }

    // =========================================================================
    // INTERFACES
    // =========================================================================

    public function listerInterfaces(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT i.id_interface, host(i.adresse_ip) AS adresse_ip, i.masque, i.nom, i.id_routeur
             FROM INTERFACE i
             INNER JOIN Routeur ro ON ro.id_routeur = i.id_routeur
             WHERE ro.id_scenario = :id ORDER BY i.id_interface'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function ajouterInterface(int $idRouteur, string $nom, string $ip, int $masque): array {
        // Validation du masque
        if ($masque < 0 || $masque > 32) {
            return ['succes' => false, 'message' => "Masque CIDR invalide (0-32)."];
        }

        try {
            $r = $this->pdo->prepare(
                'INSERT INTO INTERFACE (adresse_ip, masque, nom, id_routeur)
                 VALUES (:ip::inet, :masque, :nom, :id) RETURNING id_interface'
            );
            $r->execute([':ip' => $ip, ':masque' => $masque, ':nom' => $nom, ':id' => $idRouteur]);
            return ['succes' => true, 'id' => (int)$r->fetchColumn()];
        } catch (PDOException $e) {
            error_log('[ERREUR Interface::ajouter] ' . $e->getMessage());
            return ['succes' => false, 'message' => "Format d'adresse IP invalide."];
        }
    }

    public function modifierInterface(int $idInterface, string $nom, string $ip, int $masque): array {
        if ($masque < 0 || $masque > 32) {
            return ['succes' => false, 'message' => "Masque CIDR invalide (0-32)."];
        }

        try {
            $r = $this->pdo->prepare(
                'UPDATE INTERFACE
                 SET adresse_ip = :ip::inet, masque = :masque, nom = :nom
                 WHERE id_interface = :id'
            );
            $r->execute([':ip' => $ip, ':masque' => $masque, ':nom' => $nom, ':id' => $idInterface]);
            return ['succes' => true];
        } catch (PDOException $e) {
            return ['succes' => false, 'message' => "Format d'adresse IP invalide."];
        }
    }

    public function supprimerInterface(int $idInterface): bool {
        $r = $this->pdo->prepare('DELETE FROM INTERFACE WHERE id_interface = :id');
        return $r->execute([':id' => $idInterface]);
    }

    // =========================================================================
    // ROUTES STATIQUES
    // =========================================================================

    public function listerRoutes(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT rs.id_route, host(rs.reseau_dest) AS reseau_dest, rs.masque_dest,
                    host(rs.next_hop) AS next_hop, rs.id_routeur
             FROM ROUTE_STATIQUE rs
             INNER JOIN Routeur ro ON ro.id_routeur = rs.id_routeur
             WHERE ro.id_scenario = :id ORDER BY rs.id_route'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function ajouterRoute(int $idRouteur, string $reseauDest, int $masqueDest, string $nextHop): array {
        if ($masqueDest < 0 || $masqueDest > 32) {
            return ['succes' => false, 'message' => "Masque de destination invalide."];
        }

        try {
            $r = $this->pdo->prepare(
                'INSERT INTO ROUTE_STATIQUE (reseau_dest, masque_dest, next_hop, id_routeur)
                 VALUES (:dest::inet, :masque, :hop::inet, :id) RETURNING id_route'
            );
            $r->execute([
                ':dest'   => $reseauDest,
                ':masque' => $masqueDest,
                ':hop'    => $nextHop,
                ':id'     => $idRouteur,
            ]);
            return ['succes' => true, 'id' => (int)$r->fetchColumn()];
        } catch (PDOException $e) {
            return ['succes' => false, 'message' => "Adresse IP invalide dans la route."];
        }
    }

    public function supprimerRoute(int $idRoute): bool {
        $r = $this->pdo->prepare('DELETE FROM ROUTE_STATIQUE WHERE id_route = :id');
        return $r->execute([':id' => $idRoute]);
    }

    // =========================================================================
    // SWITCHS
    // =========================================================================

    public function listerSwitchs(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT id_switch, nom, pos_x, pos_y FROM SWITCH
             WHERE id_scenario = :id ORDER BY id_switch'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function ajouterSwitch(int $idScenario, string $nom, float $x = 0, float $y = 0): int {
        $r = $this->pdo->prepare(
            'INSERT INTO SWITCH (nom, pos_x, pos_y, id_scenario)
             VALUES (:nom, :x, :y, :id) RETURNING id_switch'
        );
        $r->execute([':nom' => $nom, ':x' => $x, ':y' => $y, ':id' => $idScenario]);
        return (int)$r->fetchColumn();
    }

    public function modifierSwitch(int $idSwitch, string $nom): bool {
        $r = $this->pdo->prepare('UPDATE SWITCH SET nom = :nom WHERE id_switch = :id');
        return $r->execute([':nom' => $nom, ':id' => $idSwitch]);
    }

    public function mettreAJourPositionSwitch(int $idSwitch, float $x, float $y): bool {
        $r = $this->pdo->prepare(
            'UPDATE SWITCH SET pos_x = :x, pos_y = :y WHERE id_switch = :id'
        );
        return $r->execute([':x' => $x, ':y' => $y, ':id' => $idSwitch]);
    }

    public function supprimerSwitch(int $idSwitch): bool {
        $r = $this->pdo->prepare('DELETE FROM SWITCH WHERE id_switch = :id');
        return $r->execute([':id' => $idSwitch]);
    }

    // =========================================================================
    // RÉSEAUX
    // =========================================================================

    public function listerReseaux(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT id_reseau, host(adresse_reseau) AS adresse_reseau, masque, label
             FROM RESEAU WHERE id_scenario = :id ORDER BY id_reseau'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function ajouterReseau(int $idScenario, string $adresse, int $masque, string $label): array {
        try {
            $r = $this->pdo->prepare(
                'INSERT INTO RESEAU (adresse_reseau, masque, label, id_scenario)
                 VALUES (:addr::inet, :masque, :label, :id) RETURNING id_reseau'
            );
            $r->execute([':addr' => $adresse, ':masque' => $masque, ':label' => $label, ':id' => $idScenario]);
            return ['succes' => true, 'id' => (int)$r->fetchColumn()];
        } catch (PDOException $e) {
            return ['succes' => false, 'message' => "Adresse réseau invalide."];
        }
    }

    public function supprimerReseau(int $idReseau): bool {
        $r = $this->pdo->prepare('DELETE FROM RESEAU WHERE id_reseau = :id');
        return $r->execute([':id' => $idReseau]);
    }

    // =========================================================================
    // HÔTES
    // =========================================================================

    public function listerHotes(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT id_hote, nom, host(adresse_ip) AS adresse_ip, host(passerelle_ip) AS passerelle_ip,
                    pos_x, pos_y, id_reseau
             FROM HOTE WHERE id_scenario = :id ORDER BY id_hote'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function ajouterHote(
        int $idScenario, string $nom, string $ip, string $passerelle,
        ?int $idReseau, float $x = 0, float $y = 0
    ): array {
        // Validation : si un réseau est spécifié, vérifier que la passerelle est dans le même sous-réseau
        if ($idReseau && !$this->validerPasserelle($ip, $passerelle, $idReseau)) {
            return ['succes' => false, 'message' => "La passerelle n'appartient pas au même sous-réseau que l'hôte."];
        }

        try {
            $r = $this->pdo->prepare(
                'INSERT INTO HOTE (nom, adresse_ip, passerelle_ip, pos_x, pos_y, id_reseau, id_scenario)
                 VALUES (:nom, :ip::inet, :gw::inet, :x, :y, :reseau, :scenario)
                 RETURNING id_hote'
            );
            $r->execute([
                ':nom'      => $nom,
                ':ip'       => $ip,
                ':gw'       => $passerelle,
                ':x'        => $x,
                ':y'        => $y,
                ':reseau'   => $idReseau,
                ':scenario' => $idScenario,
            ]);
            return ['succes' => true, 'id' => (int)$r->fetchColumn()];
        } catch (PDOException $e) {
            return ['succes' => false, 'message' => "Format d'adresse IP invalide."];
        }
    }

    public function modifierHote(
        int $idHote, string $nom, string $ip, string $passerelle, ?int $idReseau
    ): array {
        if ($idReseau && !$this->validerPasserelle($ip, $passerelle, $idReseau)) {
            return ['succes' => false, 'message' => "La passerelle n'appartient pas au même sous-réseau que l'hôte."];
        }

        try {
            $r = $this->pdo->prepare(
                'UPDATE HOTE
                 SET nom = :nom, adresse_ip = :ip::inet, passerelle_ip = :gw::inet, id_reseau = :reseau
                 WHERE id_hote = :id'
            );
            $r->execute([':nom' => $nom, ':ip' => $ip, ':gw' => $passerelle, ':reseau' => $idReseau, ':id' => $idHote]);
            return ['succes' => true];
        } catch (PDOException $e) {
            return ['succes' => false, 'message' => "Format d'adresse IP invalide."];
        }
    }

    public function mettreAJourPositionHote(int $idHote, float $x, float $y): bool {
        $r = $this->pdo->prepare('UPDATE HOTE SET pos_x = :x, pos_y = :y WHERE id_hote = :id');
        return $r->execute([':x' => $x, ':y' => $y, ':id' => $idHote]);
    }

    public function supprimerHote(int $idHote): bool {
        $r = $this->pdo->prepare('DELETE FROM HOTE WHERE id_hote = :id');
        return $r->execute([':id' => $idHote]);
    }

    // =========================================================================
    // CÂBLAGES
    // =========================================================================

    public function listerCablesHoteSwitch(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT chs.id_switch, chs.id_hote
             FROM CABLER_HOTE_SWITCH chs
             INNER JOIN HOTE h ON h.id_hote = chs.id_hote
             WHERE h.id_scenario = :id'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function listerCablesInterfaceSwitch(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT cis.id_interface, cis.id_switch
             FROM CABLER_INTERFACE_SWITCH cis
             INNER JOIN SWITCH s ON s.id_switch = cis.id_switch
             WHERE s.id_scenario = :id'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function listerCablesInterfaceInterface(int $idScenario): array {
        $r = $this->pdo->prepare(
            'SELECT cii.id_interface, cii.id_interface_1
             FROM CABLER_INTERFACE_INTERFACE cii
             INNER JOIN INTERFACE i ON i.id_interface = cii.id_interface
             INNER JOIN Routeur ro ON ro.id_routeur = i.id_routeur
             WHERE ro.id_scenario = :id'
        );
        $r->execute([':id' => $idScenario]);
        return $r->fetchAll();
    }

    public function cablerHoteSwitch(int $idHote, int $idSwitch): bool {
        try {
            $r = $this->pdo->prepare(
                'INSERT INTO CABLER_HOTE_SWITCH (id_switch, id_hote) VALUES (:sw, :h)'
            );
            return $r->execute([':sw' => $idSwitch, ':h' => $idHote]);
        } catch (PDOException $e) {
            return false; // Liaison déjà existante
        }
    }

    public function decablerInterfaceSwitch(int $idInterface, int $idSwitch): bool {
        $r = $this->pdo->prepare(
            'DELETE FROM CABLER_INTERFACE_SWITCH WHERE id_interface = :i AND id_switch = :sw'
        );
        return $r->execute([':i' => $idInterface, ':sw' => $idSwitch]);
    }

    public function decablerHoteSwitch(int $idHote, int $idSwitch): bool {
        $r = $this->pdo->prepare(
            'DELETE FROM CABLER_HOTE_SWITCH WHERE id_switch = :sw AND id_hote = :h'
        );
        return $r->execute([':sw' => $idSwitch, ':h' => $idHote]);
    }

    public function cablerInterfaceSwitch(int $idInterface, int $idSwitch): bool {
        try {
            $r = $this->pdo->prepare(
                'INSERT INTO CABLER_INTERFACE_SWITCH (id_interface, id_switch) VALUES (:i, :sw)'
            );
            return $r->execute([':i' => $idInterface, ':sw' => $idSwitch]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function cablerInterfaceInterface(int $idInterface1, int $idInterface2): bool {
        try {
            $r = $this->pdo->prepare(
                'INSERT INTO CABLER_INTERFACE_INTERFACE (id_interface, id_interface_1)
                 VALUES (:i1, :i2)'
            );
            return $r->execute([':i1' => $idInterface1, ':i2' => $idInterface2]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function decablerInterfaceInterface(int $idInterface1, int $idInterface2): bool {
        $r = $this->pdo->prepare(
            'DELETE FROM CABLER_INTERFACE_INTERFACE
             WHERE (id_interface = :i1 AND id_interface_1 = :i2)
                OR (id_interface = :i2 AND id_interface_1 = :i1)'
        );
        return $r->execute([':i1' => $idInterface1, ':i2' => $idInterface2]);
    }

    // =========================================================================
    // UTILITAIRES PRIVÉS
    // =========================================================================

    /**
     * Valide que la passerelle appartient au même sous-réseau que l'hôte.
     * Algorithme : IPHote XOR Masque == IPGW XOR Masque (specs)
     *
     * @param string $ipHote      IP de l'hôte
     * @param string $ipPasserelle IP de la passerelle
     * @param int    $idReseau    Identifiant du réseau pour récupérer le masque
     * @return bool True si la passerelle est valide
     */
    private function validerPasserelle(string $ipHote, string $ipPasserelle, int $idReseau): bool {
        $reseau = $this->pdo->prepare(
            'SELECT masque FROM RESEAU WHERE id_reseau = :id'
        );
        $reseau->execute([':id' => $idReseau]);
        $data = $reseau->fetch();

        if (!$data) return false;

        $masque     = (int)$data['masque'];
        $masqueLong = $masque === 0 ? 0 : (0xFFFFFFFF << (32 - $masque)) & 0xFFFFFFFF;

        $longHote = ip2long(strtok($ipHote, '/'));
        $longGW   = ip2long(strtok($ipPasserelle, '/'));

        if ($longHote === false || $longGW === false) return false;

        // Vérification : les deux adresses doivent avoir le même préfixe réseau
        return ($longHote & $masqueLong) === ($longGW & $masqueLong);
    }
}
?>
