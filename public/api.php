<?php
// public/api.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0); 

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
    GestionnaireAuth::initialiserSession();
    $utilisateurId = $_SESSION['utilisateur_id'] ?? 0;
    
    $pdo = BaseDeDonnees::obtenirInstance();
    
    $fluxBrut = file_get_contents('php://input');
    $donneesJSON = json_decode($fluxBrut, true) ?: [];
    $parametres = array_merge($_GET, $_POST, $donneesJSON);
    
    $action = $parametres['action'] ?? null;

    if (!$action) {
        throw new Exception("Action non spécifiée.");
    }

    $reponse = ['statut' => 'erreur', 'message' => 'Action non implémentée.'];

    switch ($action) {
        // --- SCENARIOS ---
        case 'creer_scenario':
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
            $succes = (new Scenario($pdo))->supprimerScenario($id, $utilisateurId);
            $reponse = ['statut' => 'succes', 'succes' => $succes];
            break;

        // --- AUTHENTIFICATION ---
        case 'login':
            if (GestionnaireAuth::login($parametres['username'] ?? '', $parametres['password'] ?? '', $pdo)) {
                header('Location: index.php?page=tableau-de-bord');
                exit;
            } else {
                header('Location: index.php?page=connexion&erreur=auth');
                exit;
            }
            break;

        case 'logout':
            GestionnaireAuth::logout();
            header('Location: index.php?page=connexion');
            exit;
            break;

        case 'register':
            $modele = new Utilisateur($pdo);
            $user = $parametres['username'] ?? '';
            if ($modele->trouverParNom($user)) {
                header('Location: index.php?page=connexion&erreur=existe');
            } else {
                if ($modele->creer($user, $parametres['password'] ?? '')) {
                    header('Location: index.php?page=connexion&message=success');
                } else {
                    header('Location: index.php?page=connexion&erreur=reg');
                }
            }
            exit;
            break;

        // --- LECTURE TOPOLOGIE (RESTITUTION GLOBALE) ---
        case 'charger_scenario':
            $sid = (int)($parametres['id'] ?? $parametres['scenario_id'] ?? 0);
            if ($sid === 0) throw new Exception("Identifiant de scénario invalide ou manquant.");
            
            $reponse = [
                'statut'      => 'succes',
                'nom'         => 'Scénario ' . $sid,
                'routeurs'    => (new Routeur($pdo))->listerParScenario($sid),
                'switchs'     => (new Commutateur($pdo))->listerParScenario($sid),
                'reseaux'     => (new SousReseau($pdo))->listerParScenario($sid),
                'hotes'       => (new Hote($pdo))->listerParScenario($sid),
                'liaisons_hs' => (new Liaison($pdo))->listerLiaisonsHoteSwitch($sid),
                'liaisons_is' => (new Liaison($pdo))->listerLiaisonsInterfaceSwitch($sid)
            ];
            break;
            
        // --- INSTANCIATION MATÉRIELLE ---
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
            $id = (new SousReseau($pdo))->ajouter((int)$parametres['scenario_id'], $parametres['nom'], '192.168.1.0/24');
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        // --- CONFIGURATION L2/L3 ---
        case 'configurer_hote':
            $idHote = (int)$parametres['id_hote'];
            $ip = trim($parametres['ip']);
            $passerelle = trim($parametres['passerelle']);
            $cidr = (int)($parametres['cidr'] ?? 24);
            $sousReseauId = isset($parametres['sous_reseau_id']) ? (int)$parametres['sous_reseau_id'] : null;

            if (!CalculateurReseau::validerIP($ip) || !CalculateurReseau::validerIP($passerelle)) {
                throw new Exception("Vecteur IP invalide. Non-conformité au format INET.");
            }
            if ($cidr < 0 || $cidr > 32) throw new Exception("Préfixe CIDR hors limites (0-32).");
            if (!CalculateurReseau::estDansMemeReseau($ip, $passerelle, $cidr)) {
                throw new Exception("Violation de topologie logique : La passerelle n'appartient pas au segment d'émission.");
            }

            (new Hote($pdo))->configurerReseau($idHote, $ip, $passerelle, $sousReseauId);
            $reponse = ['statut' => 'succes'];
            break;

        case 'configurer_sous_reseau':
            $idReseau = (int)$parametres['id_reseau'];
            $ipReseau = trim($parametres['ip_reseau']);
            $cidr = (int)$parametres['cidr'];

            if (!CalculateurReseau::validerIP($ipReseau)) throw new Exception("Format d'adresse de sous-réseau invalide.");
            if ($cidr < 0 || $cidr > 32) throw new Exception("Préfixe CIDR hors limites.");

            $blocCidr = $ipReseau . '/' . $cidr;
            (new SousReseau($pdo))->modifierCidr($idReseau, $blocCidr);
            $reponse = ['statut' => 'succes'];
            break;

        // --- INTERFACES & LIAISONS ---
        case 'lire_interfaces_routeur':
            $reponse = [
                'statut' => 'succes', 
                'interfaces' => (new InterfaceRouteur($pdo))->listerParRouteur((int)$parametres['id_routeur'])
            ];
            break;

        case 'creer_interface_routeur':
            $ip = trim($parametres['ip']);
            $masque = (int)$parametres['masque'];
            $nom = trim($parametres['nom'] ?? 'eth0');
            (new InterfaceRouteur($pdo))->ajouter((int)$parametres['id_routeur'], $nom, $ip, $masque);
            $reponse = ['statut' => 'succes'];
            break;

        case 'supprimer_interface':
            $stmt = $pdo->prepare("DELETE FROM interface_routeur WHERE id = ?");
            $stmt->execute([(int)$parametres['id_interface']]);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_hote_switch':
            $res = (new Liaison($pdo))->creerLiaisonHoteSwitch((int)$parametres['hote_id'], (int)$parametres['switch_id']);
            if (!$res['success']) throw new Exception($res['erreur']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_interface_switch':
            $res = (new Liaison($pdo))->creerLiaisonInterfaceSwitch((int)$parametres['interface_id'], (int)$parametres['switch_id']);
            if (!$res['success']) throw new Exception($res['erreur']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'supprimer_liaison':
            $parts = explode('_', $parametres['id']);
            $type = $parts[0];
            if ($type === 'lhs') {
                $stmt = $pdo->prepare("DELETE FROM liaison_hote_switch WHERE hote_id = ? AND switch_id = ?");
                $stmt->execute([(int)$parts[1], (int)$parts[2]]);
            } elseif ($type === 'lis') {
                $stmt = $pdo->prepare("DELETE FROM liaison_interface_switch WHERE interface_id = ? AND switch_id = ?");
                $stmt->execute([(int)$parts[1], (int)$parts[2]]);
            } else {
                throw new Exception("Vecteur de liaison non résolu.");
            }
            $reponse = ['statut' => 'succes'];
            break;

        // --- ROUTAGE STATIQUE ---
        case 'lire_routes':
            $routes = (new RouteStatique($pdo))->listerParRouteur((int)$parametres['id_routeur']);
            $reponse = ['statut' => 'succes', 'routes' => $routes];
            break;

        case 'ajouter_route':
            $reseauDest = trim($parametres['reseau_dest']);
            $masqueDest = (int)$parametres['masque_dest'];
            $nextHop = trim($parametres['next_hop']);
            $routeurId = (int)$parametres['id_routeur'];
            
            if (!(new RouteStatique($pdo))->ajouter($routeurId, $reseauDest, $masqueDest, $nextHop)) {
                throw new Exception("Vecteur IP invalide. Validation échouée pour la destination ou le Next Hop.");
            }
            
            $reponse = ['statut' => 'succes'];
            break;

        case 'supprimer_route':
            (new RouteStatique($pdo))->supprimer((int)$parametres['id_route']);
            $reponse = ['statut' => 'succes'];
            break;

        // --- MUTATION GÉNÉRIQUE ---
        case 'supprimer_equipement':
            $parts = explode('_', $parametres['id']);
            $type = $parts[0];
            $id = (int)$parts[1];
            
            if ($type === 'routeurs') { (new Routeur($pdo))->supprimer($id); }
            elseif ($type === 'switchs') { (new Commutateur($pdo))->supprimer($id); }
            elseif ($type === 'hotes') { (new Hote($pdo))->supprimer($id); }
            elseif ($type === 'reseaux') { (new SousReseau($pdo))->supprimer($id); }
            else { throw new Exception("Typage d'équipement non qualifié."); }
            
            $reponse = ['statut' => 'succes'];
            break;
            
        case 'renommer_equipement':
            $type = $parametres['type'];
            $id = (int)$parametres['id'];
            $nom = trim($parametres['nom']);
            
            if ($type === 'routeurs') { (new Routeur($pdo))->renommer($id, $nom); }
            elseif ($type === 'switchs') { (new Commutateur($pdo))->renommer($id, $nom); }
            elseif ($type === 'hotes') { (new Hote($pdo))->renommer($id, $nom); }
            elseif ($type === 'reseaux') { (new SousReseau($pdo))->renommer($id, $nom); }
            else { throw new Exception("Typage d'équipement non qualifié pour le renommage."); }
            
            $reponse = ['statut' => 'succes'];
            break;

        case 'mettre_a_jour_positions':
            $parts = explode('_', $parametres['id']);
            $type = $parts[0];
            $id = (int)$parts[1];
            $x = (int)$parametres['x'];
            $y = (int)$parametres['y'];

            if ($type === 'routeurs') { (new Routeur($pdo))->mettreAJourPosition($id, $x, $y); }
            elseif ($type === 'switchs') { (new Commutateur($pdo))->mettreAJourPosition($id, $x, $y); }
            elseif ($type === 'hotes') { (new Hote($pdo))->mettreAJourPosition($id, $x, $y); }
            elseif ($type === 'reseaux') { (new SousReseau($pdo))->mettreAJourPosition($id, $x, $y); }
            else { throw new Exception("Typage d'équipement non qualifié pour la géométrie."); }
            
            $reponse = ['statut' => 'succes'];
            break;
    }

    echo json_encode($reponse);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['statut' => 'erreur', 'message' => $e->getMessage()]);
}