<?php
// public/api.php
// Contrôleur API principal - Intégration WBS 3.0, 4.0 et Auth
header('Content-Type: application/json; charset=utf-8');

// --- 1. INCLUSIONS DES CORES ET CONFIGS ---
require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/BaseDeDonnees.php';
require_once __DIR__ . '/../core/GestionnaireAuth.php';

// --- 2. INCLUSIONS DES MODÈLES (DEV 2) ---
require_once __DIR__ . '/../src/Model/Routeur.php';
require_once __DIR__ . '/../src/Model/Commutateur.php';
require_once __DIR__ . '/../src/Model/Hote.php';
require_once __DIR__ . '/../src/Model/SousReseau.php';
require_once __DIR__ . '/../src/Model/Liaison.php';
require_once __DIR__ . '/../src/Model/Utilisateur.php';

// --- 3. INCLUSIONS DES MODÈLES (DEV 3 - TON TRAVAIL) ---
require_once __DIR__ . '/../src/Model/CalculateurReseau.php';
require_once __DIR__ . '/../src/Model/RouteStatique.php';
require_once __DIR__ . '/../src/Model/InterfaceRouteur.php';

use App\Model\CalculateurReseau;
use App\Model\RouteStatique;
use App\Model\InterfaceRouteur;
use App\Model\Liaison;

try {
    $pdo = BaseDeDonnees::obtenirInstance();
    
    // Détection de l'action (soit via GET, soit via le corps JSON)
    $action = $_GET['action'] ?? null;
    $fluxBrut = file_get_contents('php://input');
    $donnees = json_decode($fluxBrut, true);

    if (!$action && isset($donnees['action'])) {
        $action = $donnees['action'];
    }

    if (!$action) {
        throw new Exception("Action non spécifiée.");
    }

    $reponse = [];

    switch ($action) {
        // ==========================================================
        // AUTHENTIFICATION (Géré par Dev 2 / Dev 1)
        // ==========================================================
        case 'login':
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';
            if (GestionnaireAuth::login($user, $pass, $pdo)) {
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

        // ==========================================================
        // TOPOLOGIE ET CHARGEMENT (Intégration Dev 2 & 3)
        // ==========================================================
        case 'charger_topologie':
            $sid = (int)($_GET['scenario_id'] ?? $donnees['scenario_id'] ?? 0);
            
            $res = new App\Model\SousReseau($pdo);
            $rou = new App\Model\Routeur($pdo);
            $swi = new App\Model\Commutateur($pdo);
            $hot = new App\Model\Hote($pdo);
            $lia = new Liaison(); // Utilise le constructeur avec connexion auto

            $reponse = [
                'statut' => 'succes',
                'donnees' => [
                    'sous_reseaux' => $res->listerParScenario($sid),
                    'routeurs'     => $rou->listerParScenario($sid),
                    'switchs'      => $swi->listerParScenario($sid),
                    'hotes'        => $hot->listerParScenario($sid),
                    'liaisons'     => [
                        'hote_switch'      => $lia->listerLiaisonsHoteSwitch($sid),
                        'interface_switch' => $lia->listerLiaisonsInterfaceSwitch($sid)
                    ]
                ]
            ];
            break;

        // ==========================================================
        // LOGIQUE RÉSEAU AVANCÉE (WBS 4.0 - TON CŒUR DE MÉTIER)
        // ==========================================================
        
        // Câblage avec vérification des ports physiques
        case 'cabler_hote':
            $lia = new Liaison();
            $res = $lia->cablerHoteSwitch((int)$donnees['id_hote'], (int)$donnees['id_switch']);
            $reponse = $res['success'] 
                ? ['statut' => 'succes'] 
                : ['statut' => 'erreur', 'message' => $res['erreur']];
            break;

        // Ajout de route avec validation bitwise des IP
        case 'ajouter_route':
            $rs = new RouteStatique();
            $success = $rs->ajouter(
                (int)$donnees['routeur_id'], 
                $donnees['destination'], 
                (int)$donnees['masque'], 
                $donnees['passerelle']
            );
            $reponse = $success 
                ? ['statut' => 'succes'] 
                : ['statut' => 'erreur', 'message' => 'Validation réseau échouée (IP ou Masque invalide)'];
            break;

        // Test de simulation : Quel chemin prend le paquet ? (LPM)
        case 'simuler_routage':
            $rs = new RouteStatique();
            $prochainHop = $rs->determinerProchainSaut((int)$donnees['routeur_id'], $donnees['ip_cible']);
            $reponse = [
                'statut' => 'succes',
                'next_hop' => $prochainHop ?? 'Réseau inaccessible'
            ];
            break;

        // ==========================================================
        // CRUD ÉLÉMENTS DE BASE (Dev 2)
        // ==========================================================
        case 'ajouter_routeur':
            $m = new App\Model\Routeur($pdo);
            $id = $m->ajouter((int)$donnees['scenario_id'], $donnees['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_commutateur':
            $m = new App\Model\Commutateur($pdo);
            $id = $m->ajouter((int)$donnees['scenario_id'], $donnees['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_hote':
            $m = new App\Model\Hote($pdo);
            $id = $m->ajouter((int)$donnees['scenario_id'], $donnees['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        default:
            $reponse = ['statut' => 'erreur', 'message' => "L'action '$action' n'est pas reconnue."];
            break;
    }

    echo json_encode($reponse);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['statut' => 'erreur', 'message' => $e->getMessage()]);
}