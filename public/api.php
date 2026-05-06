<?php
// public/api.php
header('Content-Type: application/json; charset=utf-8');

// Désactivation de la sortie HTML des erreurs pour préserver l'intégrité du flux JSON
ini_set('display_errors', 0); 

require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/BaseDeDonnees.php';
require_once __DIR__ . '/../src/Model/Routeur.php';
require_once __DIR__ . '/../src/Model/Commutateur.php';
require_once __DIR__ . '/../src/Model/Hote.php';
require_once __DIR__ . '/../src/Model/SousReseau.php';
require_once __DIR__ . '/../src/Model/Liaison.php';
require_once __DIR__ . '/../core/GestionnaireAuth.php';
require_once __DIR__ . '/../src/Model/Utilisateur.php';

try {
    $pdo = BaseDeDonnees::obtenirInstance();
    
    // Agrégation standardisée des paramètres (URL, Formulaire, et Payload JSON)
    $fluxBrut = file_get_contents('php://input');
    $donneesJSON = json_decode($fluxBrut, true) ?: [];
    $parametres = array_merge($_GET, $_POST, $donneesJSON);
    
    $action = $parametres['action'] ?? null;

    if (!$action) {
        throw new Exception("Action non spécifiée.");
    }

    $reponse = ['statut' => 'erreur', 'message' => 'Action non implémentée.'];

    switch ($action) {
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

        case 'charger_scenario':
            $sid = (int)($parametres['id'] ?? $parametres['scenario_id'] ?? 0);
            if ($sid === 0) throw new Exception("Identifiant de scénario invalide ou manquant.");
            
            $res = new SousReseau($pdo);
            $rou = new Routeur($pdo);
            $swi = new Commutateur($pdo);
            $hot = new Hote($pdo);
            $lia = new Liaison($pdo);

            // Structure aplatie correspondant strictement au mapping JavaScript
            $reponse = [
                'statut'      => 'succes',
                'nom'         => 'Scénario ' . $sid,
                'routeurs'    => $rou->listerParScenario($sid),
                'switchs'     => $swi->listerParScenario($sid),
                'reseaux'     => $res->listerParScenario($sid),
                'hotes'       => $hot->listerParScenario($sid),
                'liaisons_hs' => $lia->listerLiaisonsHoteSwitch($sid),
                'liaisons_is' => $lia->listerLiaisonsInterfaceSwitch($sid)
            ];
            break;
            
        case 'ajouter_routeur':
            $m = new Routeur($pdo);
            $id = $m->ajouter($parametres['scenario_id'], $parametres['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_commutateur':
            $m = new Commutateur($pdo);
            $id = $m->ajouter($parametres['scenario_id'], $parametres['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_hote':
            $m = new Hote($pdo);
            $id = $m->ajouter($parametres['scenario_id'], $parametres['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;
            
        case 'ajouter_sous_reseau':
            $m = new SousReseau($pdo);
            $id = $m->ajouter($parametres['scenario_id'], $parametres['nom'], '192.168.1.0/24'); // Valeur CIDR par défaut obligatoire selon le schéma SQL
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;
        case 'lire_interfaces_routeur':
            $stmt = $pdo->prepare("SELECT id, adresse_ip, masque, nom FROM interface_routeur WHERE routeur_id = ?");
            $stmt->execute([(int)$parametres['id_routeur']]);
            $reponse = ['statut' => 'succes', 'interfaces' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'creer_interface_routeur':
            $stmt = $pdo->prepare("INSERT INTO interface_routeur (routeur_id, nom, adresse_ip, masque) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                (int)$parametres['id_routeur'], 
                trim($parametres['nom']), 
                trim($parametres['ip']), 
                (int)$parametres['masque']
            ]);
            $reponse = ['statut' => 'succes'];
            break;

        case 'supprimer_interface':
            $stmt = $pdo->prepare("DELETE FROM interface_routeur WHERE id = ?");
            $stmt->execute([(int)$parametres['id_interface']]);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_hote_switch':
            $liaison = new Liaison($pdo);
            $liaison->creerLiaisonHoteSwitch((int)$parametres['hote_id'], (int)$parametres['switch_id']);
            $reponse = ['statut' => 'succes'];
            break;

        case 'creer_liaison_interface_switch':
            $liaison = new Liaison($pdo);
            $liaison->creerLiaisonInterfaceSwitch((int)$parametres['interface_id'], (int)$parametres['switch_id']);
            $reponse = ['statut' => 'succes'];
            break;

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
                throw new Exception("Vecteur de liaison non reconnu.");
            }
            
            $reponse = ['statut' => 'succes'];
            break;
    }

    // Émission du flux de sortie JSON
    echo json_encode($reponse);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['statut' => 'erreur', 'message' => $e->getMessage()]);
}