<?php
// public/api.php

// Force la réponse en JSON avec encodage UTF-8
header('Content-Type: application/json; charset=utf-8');

// Masque les erreurs PHP dans la réponse HTTP (elles ne doivent pas polluer le JSON)
ini_set('display_errors', 0); 

// Chargement des dépendances 
require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/BaseDeDonnees.php';
require_once __DIR__ . '/../src/Model/Routeur.php';
require_once __DIR__ . '/../src/Model/Commutateur.php';
require_once __DIR__ . '/../src/Model/Hote.php';
require_once __DIR__ . '/../src/Model/SousReseau.php';
require_once __DIR__ . '/../src/Model/Liaison.php';
require_once __DIR__ . '/../src/Model/InterfaceRouteur.php';
require_once __DIR__ . '/../src/Model/RouteStatique.php';
require_once __DIR__ . '/../core/GestionnaireAuth.php';
require_once __DIR__ . '/../src/Model/Utilisateur.php';
require_once __DIR__ . '/../src/Model/CalculateurReseau.php';
require_once __DIR__ . '/../src/Model/Scenario.php';

try {
    // Démarre ou reprend la session sécurisée
    GestionnaireAuth::initialiserSession();

    // Récupère l'ID de l'utilisateur connecté (0 = anonyme)
    $utilisateurId = $_SESSION['utilisateur_id'] ?? 0;

    // Connexion unique à la base de données (Singleton)
    $pdo = BaseDeDonnees::obtenirInstance();

    // Lecture et fusion des paramètres entrants 
    // Accepte GET, POST et corps JSON — le corps JSON a priorité en dernier
    $fluxBrut    = file_get_contents('php://input');
    $donneesJSON = json_decode($fluxBrut, true) ?: [];
    $parametres  = array_merge($_GET, $_POST, $donneesJSON);

    $action = $parametres['action'] ?? null;

    if (!$action) {
        throw new Exception("Action non spécifiée.");
    }

    // Réponse par défaut si aucun case ne la remplace
    $reponse = ['statut' => 'erreur', 'message' => 'Action non implémentée.'];

    switch ($action) {

        // ════════════════════════════════════════════════════════════════════
        // SCÉNARIOS
        // ════════════════════════════════════════════════════════════════════

        case 'creer_scenario':
            // Seul un utilisateur connecté peut créer un scénario
            if ($utilisateurId === 0) throw new Exception("Action refusée : Authentification requise.");
            $nom = trim($parametres['nom'] ?? '');
            if (empty($nom)) throw new Exception("Le nom du scénario est obligatoire.");
            $id = (new Scenario($pdo))->creerScenario($utilisateurId, $nom);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'supprimer_scenario':
            if ($utilisateurId === 0) throw new Exception("Action refusée : Authentification requise.");
            $id = (int)($parametres['id'] ?? 0);
            if ($id === 0) throw new Exception("ID de scénario invalide.");
            // Passe l'utilisateurId pour vérifier que le scénario lui appartient
            $succes = (new Scenario($pdo))->supprimerScenario($id, $utilisateurId);
            $reponse = ['statut' => 'succes', 'succes' => $succes];
            break;

        // ════════════════════════════════════════════════════════════════════
        // AUTHENTIFICATION
        // ════════════════════════════════════════════════════════════════════

        case 'login':
            // Tente la connexion ; redirige vers le tableau de bord ou vers la page d'erreur
            if (GestionnaireAuth::login($parametres['username'] ?? '', $parametres['password'] ?? '', $pdo)) {
                header('Location: index.php?page=tableau-de-bord');
                exit;
            } else {
                header('Location: index.php?page=connexion&erreur=auth');
                exit;
            }
            break;

        case 'logout':
            // Détruit la session et redirige vers la page de connexion
            GestionnaireAuth::logout();
            header('Location: index.php?page=connexion');
            exit;
            break;

        case 'register':
            $modele = new Utilisateur($pdo);
            $user   = $parametres['username'] ?? '';
            if ($modele->trouverParNom($user)) {
                // L'identifiant est déjà pris
                header('Location: index.php?page=connexion&erreur=existe');
            } else {
                if ($modele->inscrire($user, $parametres['password'] ?? '')) {
                    header('Location: index.php?page=connexion&message=success');
                } else {
                    header('Location: index.php?page=connexion&erreur=reg');
                }
            }
            exit;
            break;

        // ════════════════════════════════════════════════════════════════════
        // LECTURE TOPOLOGIE — RESTITUTION GLOBALE DU SCÉNARIO
        // ════════════════════════════════════════════════════════════════════

        case 'charger_scenario':
            $sid = (int)($parametres['id'] ?? $parametres['scenario_id'] ?? 0);
            if ($sid === 0) throw new Exception("Identifiant de scénario invalide ou manquant.");

            // Retourne la totalité de la topologie en une seule réponse JSON
            $reponse = [
                'statut'      => 'succes',
                'nom'         => 'Scénario ' . $sid,
                'routeurs'    => (new Routeur($pdo))->listerParScenario($sid),
                'switchs'     => (new Commutateur($pdo))->listerParScenario($sid),
                'reseaux'     => (new SousReseau($pdo))->listerParScenario($sid),
                'hotes'       => (new Hote($pdo))->listerParScenario($sid),
                'liaisons_hs' => (new Liaison($pdo))->listerLiaisonsHoteSwitch($sid),       // Hôte <-> Switch
                'liaisons_is' => (new Liaison($pdo))->listerLiaisonsInterfaceSwitch($sid),  // Interface <-> Switch
                'liaisons_hi' => (new Liaison($pdo))->listerLiaisonsHoteInterface($sid),    // Hôte <-> Interface
                'liaisons_hh' => (new Liaison($pdo))->listerLiaisonsHoteHote($sid),         // Hôte <-> Hôte
                'liaisons_ii' => (new Liaison($pdo))->listerLiaisonsInterfaceInterface($sid) // Interface <-> Interface
            ];
            break;

        // ════════════════════════════════════════════════════════════════════
        // INSTANCIATION MATÉRIELLE — AJOUT D'ÉQUIPEMENTS
        // ════════════════════════════════════════════════════════════════════

        case 'ajouter_routeur':
            $id = (new Routeur($pdo))->ajouter((int)$parametres['scenario_id'], $parametres['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_commutateur':
            $id = (new Commutateur($pdo))->ajouter((int)$parametres['scenario_id'], $parametres['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_hote':
            $id = (new Hote($pdo))->ajouter((int)$parametres['scenario_id'], $parametres['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_sous_reseau':
            // Bloc CIDR par défaut à 192.168.1.0/24 — modifiable ensuite via configurer_sous_reseau
            $id = (new SousReseau($pdo))->ajouter((int)$parametres['scenario_id'], $parametres['nom'], '192.168.1.0/24');
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        // ════════════════════════════════════════════════════════════════════
        // CONFIGURATION L2 / L3
        // ════════════════════════════════════════════════════════════════════

        case 'configurer_hote':
            $idHote       = (int)$parametres['id_hote'];
            $nomInterface = trim($parametres['nom_interface'] ?? 'eth0');
            $ip           = trim($parametres['ip']);
            $passerelle   = trim($parametres['passerelle']);
            $cidr         = (int)($parametres['cidr'] ?? 24);
            $sousReseauId = isset($parametres['sous_reseau_id']) ? (int)$parametres['sous_reseau_id'] : null;

            // Validation des adresses IP
            if (!CalculateurReseau::validerIP($ip) || !CalculateurReseau::validerIP($passerelle)) {
                throw new Exception("Vecteur IP invalide. Non-conformité au format INET.");
            }
            if ($cidr < 0 || $cidr > 32) throw new Exception("Préfixe CIDR hors limites (0-32).");

            // La passerelle doit être dans le même sous-réseau que l'hôte
            if (!CalculateurReseau::estDansMemeReseau($ip, $passerelle, $cidr)) {
                throw new Exception("Violation de topologie logique : La passerelle n'appartient pas au segment d'émission.");
            }

            (new Hote($pdo))->configurerReseau($idHote, $ip, $passerelle, $nomInterface, $sousReseauId);
            $reponse = ['statut' => 'succes'];
            break;

        case 'configurer_sous_reseau':
            $idReseau = (int)$parametres['id_reseau'];
            $ipReseau = trim($parametres['ip_reseau']);
            $cidr     = (int)$parametres['cidr'];

            if (!CalculateurReseau::validerIP($ipReseau)) throw new Exception("Format d'adresse de sous-réseau invalide.");
            if ($cidr < 0 || $cidr > 32) throw new Exception("Préfixe CIDR hors limites.");

            // Construit le bloc CIDR complet (ex: "192.168.10.0/24")
            $blocCidr = $ipReseau . '/' . $cidr;
            (new SousReseau($pdo))->modifierCidr($idReseau, $blocCidr);
            $reponse = ['statut' => 'succes'];
            break;

        // ════════════════════════════════════════════════════════════════════
        // INTERFACES ROUTEUR & LIAISONS
        // ════════════════════════════════════════════════════════════════════

        case 'lire_interfaces_routeur':
            $reponse = [
                'statut'     => 'succes',
                'interfaces' => (new InterfaceRouteur($pdo))->listerParRouteur((int)$parametres['id_routeur'])
            ];
            break;

        case 'creer_interface_routeur':
            $ip    = trim($parametres['ip']);
            $masque = (int)$parametres['masque'];
            $nom   = trim($parametres['nom'] ?? 'eth0');
            (new InterfaceRouteur($pdo))->ajouter((int)$parametres['id_routeur'], $nom, $ip, $masque);
            $reponse = ['statut' => 'succes'];
            break;

        case 'modifier_interface_routeur':
            $id     = (int)$parametres['id_interface'];
            $ip     = trim($parametres['ip']);
            $masque = (int)$parametres['masque'];
            $nom    = trim($parametres['nom']);
            if (!(new InterfaceRouteur($pdo))->modifier($id, $nom, $ip, $masque)) {
                throw new Exception("Vecteur IP invalide pour la modification de l'interface.");
            }
            $reponse = ['statut' => 'succes'];
            break;

        case 'supprimer_interface':
            // Suppression directe sans passer par un modèle dédié
            $stmt = $pdo->prepare("DELETE FROM interface_routeur WHERE id = ?");
            $stmt->execute([(int)$parametres['id_interface']]);
            $reponse = ['statut' => 'succes'];
            break;

        // Création de liaisons 
        // Chaque cas délègue au modèle Liaison qui gère les contraintes d'unicité

        case 'creer_liaison_hote_switch':
            $res = (new Liaison($pdo))->creerLiaisonHoteSwitch((int)$parametres['hote_id'], (int)$parametres['switch_id']);
            if (!$res['success']) throw new Exception($res['erreur']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_hote_interface':
            $res = (new Liaison($pdo))->creerLiaisonHoteInterface((int)$parametres['hote_id'], (int)$parametres['interface_id']);
            if (!$res['success']) throw new Exception($res['erreur']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_hote_hote':
            $res = (new Liaison($pdo))->creerLiaisonHoteHote((int)$parametres['hote_1_id'], (int)$parametres['hote_2_id']);
            if (!$res['success']) throw new Exception($res['erreur']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_interface_switch':
            $res = (new Liaison($pdo))->creerLiaisonInterfaceSwitch((int)$parametres['interface_id'], (int)$parametres['switch_id']);
            if (!$res['success']) throw new Exception($res['erreur']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_interface_interface':
            $res = (new Liaison($pdo))->creerLiaisonInterfaceInterface((int)$parametres['interface_1_id'], (int)$parametres['interface_2_id']);
            if (!$res['success']) throw new Exception($res['erreur']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'supprimer_liaison':
            // L'ID de liaison encode le type et les IDs concernés (ex: "lhs_3_7")
            // Format : <préfixe>_<id1>_<id2>
            $parts = explode('_', $parametres['id']);
            $type  = $parts[0];

            if ($type === 'lhs') {
                // Hôte <-> Switch
                $stmt = $pdo->prepare("DELETE FROM liaison_hote_switch WHERE hote_id = ? AND switch_id = ?");
                $stmt->execute([(int)$parts[1], (int)$parts[2]]);
            } elseif ($type === 'lis') {
                // Interface <-> Switch
                $stmt = $pdo->prepare("DELETE FROM liaison_interface_switch WHERE interface_id = ? AND switch_id = ?");
                $stmt->execute([(int)$parts[1], (int)$parts[2]]);
            } elseif ($type === 'lhi') {
                // Hôte <-> Interface
                $stmt = $pdo->prepare("DELETE FROM liaison_hote_interface WHERE hote_id = ? AND interface_id = ?");
                $stmt->execute([(int)$parts[1], (int)$parts[2]]);
            } elseif ($type === 'lhh') {
                // Hôte <-> Hôte
                $stmt = $pdo->prepare("DELETE FROM liaison_hote_hote WHERE hote_1_id = ? AND hote_2_id = ?");
                $stmt->execute([(int)$parts[1], (int)$parts[2]]);
            } elseif ($type === 'lii') {
                // Interface <-> Interface
                $stmt = $pdo->prepare("DELETE FROM liaison_interface_interface WHERE interface_id = ? AND interface_1_id = ?");
                $stmt->execute([(int)$parts[1], (int)$parts[2]]);
            } else {
                throw new Exception("Vecteur de liaison non résolu.");
            }
            $reponse = ['statut' => 'succes'];
            break;

        // ════════════════════════════════════════════════════════════════════
        // ROUTAGE STATIQUE
        // ════════════════════════════════════════════════════════════════════

        case 'lire_routes':
            $routes  = (new RouteStatique($pdo))->listerParRouteur((int)$parametres['id_routeur']);
            $reponse = ['statut' => 'succes', 'routes' => $routes];
            break;

        case 'ajouter_route':
            $reseauDest = trim($parametres['reseau_dest']);
            $masqueDest = (int)$parametres['masque_dest'];
            $nextHop    = trim($parametres['next_hop']);
            $routeurId  = (int)$parametres['id_routeur'];

            // Le modèle valide les IPs et retourne false si invalide
            if (!(new RouteStatique($pdo))->ajouter($routeurId, $reseauDest, $masqueDest, $nextHop)) {
                throw new Exception("Vecteur IP invalide. Validation échouée pour la destination ou le Next Hop.");
            }
            $reponse = ['statut' => 'succes'];
            break;

        case 'modifier_route':
            $idRoute    = (int)$parametres['id_route'];
            $reseauDest = trim($parametres['reseau_dest']);
            $masqueDest = (int)$parametres['masque_dest'];
            $nextHop    = trim($parametres['next_hop']);

            if (!(new RouteStatique($pdo))->modifier($idRoute, $reseauDest, $masqueDest, $nextHop)) {
                throw new Exception("Vecteur IP invalide. Validation échouée lors de la modification de la route.");
            }
            $reponse = ['statut' => 'succes'];
            break;

        case 'supprimer_route':
            (new RouteStatique($pdo))->supprimer((int)$parametres['id_route']);
            $reponse = ['statut' => 'succes'];
            break;

        // ════════════════════════════════════════════════════════════════════
        // MOTEUR DE SIMULATION WBS 4.0
        // ════════════════════════════════════════════════════════════════════

        case 'simuler_routage':
            // Chargement à la demande du moteur (lourd, inutile pour les autres actions)
            require_once __DIR__ . '/../core/MoteurRoutage.php';

            $scenarioId   = (int)($parametres['scenario_id'] ?? 0);
            $hoteSourceId = (int)($parametres['id_source']   ?? 0);
            $ipDest       = trim($parametres['ip_dest']      ?? '');

            if ($scenarioId === 0 || $hoteSourceId === 0 || empty($ipDest)) {
                throw new Exception("Paramètres de simulation manquants (scenario_id, id_source, ip_dest).");
            }
            if (!CalculateurReseau::validerIP($ipDest)) {
                throw new Exception("L'adresse IP de destination est invalide.");
            }

            // Délègue entièrement au moteur ; retourne { statut, trace, [message] }
            $resultat = MoteurRoutage::simulerAcheminement($pdo, $scenarioId, $hoteSourceId, $ipDest);
            $reponse  = $resultat;
            break;

        // ════════════════════════════════════════════════════════════════════
        // MUTATIONS GÉNÉRIQUES
        // ════════════════════════════════════════════════════════════════════

        case 'supprimer_equipement':
            // L'ID est au format "<type>_<id>" (ex: "routeurs_4") — identique au nœud vis.js
            $parts = explode('_', $parametres['id']);
            $type  = $parts[0];
            $id    = (int)$parts[1];

            if ($type === 'routeurs')     { (new Routeur($pdo))->supprimer($id); }
            elseif ($type === 'switchs')  { (new Commutateur($pdo))->supprimer($id); }
            elseif ($type === 'hotes')    { (new Hote($pdo))->supprimer($id); }
            elseif ($type === 'reseaux')  { (new SousReseau($pdo))->supprimer($id); }
            else { throw new Exception("Typage d'équipement non qualifié."); }

            $reponse = ['statut' => 'succes'];
            break;

        case 'renommer_equipement':
            $type = $parametres['type'];
            $id   = (int)$parametres['id'];
            $nom  = trim($parametres['nom']);

            if ($type === 'routeurs')     { (new Routeur($pdo))->renommer($id, $nom); }
            elseif ($type === 'switchs')  { (new Commutateur($pdo))->renommer($id, $nom); }
            elseif ($type === 'hotes')    { (new Hote($pdo))->renommer($id, $nom); }
            elseif ($type === 'reseaux')  { (new SousReseau($pdo))->renommer($id, $nom); }
            else { throw new Exception("Typage d'équipement non qualifié pour le renommage."); }

            $reponse = ['statut' => 'succes'];
            break;

        // ════════════════════════════════════════════════════════════════════
        // ADMINISTRATION
        // ════════════════════════════════════════════════════════════════════

        case 'bannir_utilisateur':
            // Réservé aux administrateurs — interdit de se bannir soi-même
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                throw new Exception("Action refusée : Droits administrateur requis.");
            }
            $idCible = (int)($parametres['id_utilisateur'] ?? 0);
            if ($idCible === 0 || $idCible === $utilisateurId) {
                throw new Exception("Utilisateur invalide ou vous-même.");
            }
            (new Utilisateur($pdo))->supprimer($idCible);
            $reponse = ['statut' => 'succes'];
            break;

        case 'purger_scenarios_vides':
            // Supprime les scénarios sans aucun équipement (maintenance BDD)
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                throw new Exception("Action refusée : Droits administrateur requis.");
            }
            $count   = (new Scenario($pdo))->purgerScenariosVides();
            $reponse = ['statut' => 'succes', 'supprimes' => $count];
            break;

        case 'mettre_a_jour_positions':
            // Sauvegarde la position vis.js d'un nœud après un drag (format id: "routeurs_4")
            $parts = explode('_', $parametres['id']);
            $type  = $parts[0];
            $id    = (int)$parts[1];
            $x     = (int)$parametres['x'];
            $y     = (int)$parametres['y'];

            if ($type === 'routeurs')     { (new Routeur($pdo))->mettreAJourPosition($id, $x, $y); }
            elseif ($type === 'switchs')  { (new Commutateur($pdo))->mettreAJourPosition($id, $x, $y); }
            elseif ($type === 'hotes')    { (new Hote($pdo))->mettreAJourPosition($id, $x, $y); }
            elseif ($type === 'reseaux')  { (new SousReseau($pdo))->mettreAJourPosition($id, $x, $y); }
            else { throw new Exception("Typage d'équipement non qualifié pour la géométrie."); }

            $reponse = ['statut' => 'succes'];
            break;
    }

    // Sérialise et envoie la réponse JSON finale
    echo json_encode($reponse);

} catch (Exception $e) {
    // Toute exception non gérée remonte ici avec un code HTTP 400
    // Le message est exposé au client — attention à ne pas y inclure de données sensibles
    http_response_code(400);
    echo json_encode(['statut' => 'erreur', 'message' => $e->getMessage()]);
}