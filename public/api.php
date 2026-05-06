<?php
// public/api.php
header('Content-Type: application/json; charset=utf-8');

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
    $action = $_GET['action'] ?? null;

    if (!$action) {
        throw new Exception("Action non spécifiée.");
    }

    switch ($action) {
        case 'login':
            // Récupération des données POST classiques du formulaire
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';

            if (GestionnaireAuth::login($user, $pass, $pdo)) {
                // Redirection vers le tableau de bord en cas de succès
                header('Location: index.php?page=tableau-de-bord');
                exit;
            } else {
                // Redirection avec erreur
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
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';
            $modele = new Utilisateur($pdo);
            
            if ($modele->trouverParNom($user)) {
                header('Location: index.php?page=connexion&erreur=existe');
            } else {
                if ($modele->creer($user, $pass)) {
                    header('Location: index.php?page=connexion&message=success');
                } else {
                    header('Location: index.php?page=connexion&erreur=reg');
                }
            }
            exit;
            break;
        case 'charger_topologie':
            $sid = (int)($_GET['scenario_id'] ?? $donnees['scenario_id'] ?? 0);
            
            // Instanciation des modèles
            $res = new SousReseau($pdo);
            $rou = new Routeur($pdo);
            $swi = new Commutateur($pdo);
            $hot = new Hote($pdo);
            $lia = new Liaison($pdo);

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
            
        case 'ajouter_routeur':
            $m = new Routeur($pdo);
            $id = $m->ajouter($donnees['scenario_id'], $donnees['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_commutateur':
            $m = new Commutateur($pdo);
            $id = $m->ajouter($donnees['scenario_id'], $donnees['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        case 'ajouter_hote':
            $m = new Hote($pdo);
            $id = $m->ajouter($donnees['scenario_id'], $donnees['nom']);
            $reponse = ['statut' => 'succes', 'id' => $id];
            break;

        // Les autres cases (ajouter_routeur, etc.) restent pour les appels AJAX du JS
        default:
            $fluxBrut = file_get_contents('php://input');
            $donnees = json_decode($fluxBrut, true);
            // ... (reste de votre logique JSON pour le moteur visuel)
            break;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['statut' => 'erreur', 'message' => $e->getMessage()]);
}