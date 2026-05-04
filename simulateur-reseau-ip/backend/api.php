<?php
// =============================================================================
// API BACKEND : api.php
// Auteur : Étudiant
// Description : Point d'entrée unique pour les requêtes Fetch/AJAX du frontend.
//               Dispatche les actions vers les modèles appropriés.
//               Retourne toujours du JSON.
// =============================================================================

require_once __DIR__ . '/../configuration.php';
require_once __DIR__ . '/noyau/GestionnaireAuth.php';
require_once __DIR__ . '/noyau/MoteurRoutage.php';
require_once __DIR__ . '/modeles/Scenario.php';
require_once __DIR__ . '/modeles/Utilisateur.php';

// --- Définition du type de contenu de la réponse ---
header('Content-Type: application/json; charset=utf-8');

// --- Vérification de l'authentification (toutes les routes API sont protégées) ---
if (!GestionnaireAuth::estConnecte()) {
    http_response_code(401);
    echo json_encode(['erreur' => 'Non authentifié.']);
    exit;
}

// --- Récupération des paramètres de la requête ---
$methode = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? '';
$idUser  = GestionnaireAuth::getIdUtilisateur();

// Décodage du corps JSON pour les requêtes POST/PUT
$corps = [];
if (in_array($methode, ['POST', 'PUT', 'DELETE'])) {
    $json  = file_get_contents('php://input');
    $corps = json_decode($json, true) ?? [];
}

// Instanciation des modèles
$modeleScenario    = new Scenario();
$modeleUtilisateur = new Utilisateur();

// =============================================================================
// DISPATCH DES ACTIONS
// =============================================================================
try {
    switch ($action) {

        // ---- SCÉNARIOS ----

        case 'lister_scenarios':
            repondre($modeleScenario->listerParUtilisateur($idUser));
            break;

        case 'creer_scenario':
            $nom  = trim($corps['nom'] ?? '');
            $desc = trim($corps['description'] ?? '');
            if (!$nom) erreur("Le nom du scénario est requis.");
            $id = $modeleScenario->creer($nom, $desc, $idUser);
            repondre(['succes' => true, 'id_scenario' => $id]);
            break;

        case 'supprimer_scenario':
            $id = (int)($corps['id_scenario'] ?? 0);
            verifierAppartenance($id, $idUser, $modeleScenario);
            $ok = $modeleScenario->supprimer($id, $idUser);
            repondre(['succes' => $ok]);
            break;

        case 'charger_topologie':
            $id = (int)($_GET['id_scenario'] ?? 0);
            verifierAppartenance($id, $idUser, $modeleScenario);
            repondre($modeleScenario->chargerTopologie($id));
            break;

        // ---- ROUTEURS ----

        case 'ajouter_routeur':
            $id = (int)($corps['id_scenario'] ?? 0);
            verifierAppartenance($id, $idUser, $modeleScenario);
            $idR = $modeleScenario->ajouterRouteur($id, $corps['nom'] ?? 'Routeur', $corps['x'] ?? 100, $corps['y'] ?? 100);
            repondre(['succes' => true, 'id_routeur' => $idR]);
            break;

        case 'modifier_routeur':
            $idR = (int)($corps['id_routeur'] ?? 0);
            $ok  = $modeleScenario->modifierRouteur($idR, $corps['nom'] ?? '');
            repondre(['succes' => $ok]);
            break;

        case 'supprimer_routeur':
            $ok = $modeleScenario->supprimerRouteur((int)($corps['id_routeur'] ?? 0));
            repondre(['succes' => $ok]);
            break;

        case 'maj_position_routeur':
            $ok = $modeleScenario->mettreAJourPositionRouteur(
                (int)($corps['id_routeur'] ?? 0),
                (float)($corps['x'] ?? 0),
                (float)($corps['y'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        // ---- INTERFACES ----

        case 'ajouter_interface':
            $res = $modeleScenario->ajouterInterface(
                (int)($corps['id_routeur'] ?? 0),
                $corps['nom'] ?? '',
                $corps['adresse_ip'] ?? '',
                (int)($corps['masque'] ?? 24)
            );
            repondre($res);
            break;

        case 'modifier_interface':
            $res = $modeleScenario->modifierInterface(
                (int)($corps['id_interface'] ?? 0),
                $corps['nom'] ?? '',
                $corps['adresse_ip'] ?? '',
                (int)($corps['masque'] ?? 24)
            );
            repondre($res);
            break;

        case 'supprimer_interface':
            $ok = $modeleScenario->supprimerInterface((int)($corps['id_interface'] ?? 0));
            repondre(['succes' => $ok]);
            break;

        // ---- ROUTES STATIQUES ----

        case 'ajouter_route':
            $res = $modeleScenario->ajouterRoute(
                (int)($corps['id_routeur'] ?? 0),
                $corps['reseau_dest'] ?? '',
                (int)($corps['masque_dest'] ?? 0),
                $corps['next_hop'] ?? ''
            );
            repondre($res);
            break;

        case 'supprimer_route':
            $ok = $modeleScenario->supprimerRoute((int)($corps['id_route'] ?? 0));
            repondre(['succes' => $ok]);
            break;

        // ---- SWITCHS ----

        case 'ajouter_switch':
            $id  = (int)($corps['id_scenario'] ?? 0);
            verifierAppartenance($id, $idUser, $modeleScenario);
            $idS = $modeleScenario->ajouterSwitch($id, $corps['nom'] ?? 'Switch', $corps['x'] ?? 200, $corps['y'] ?? 200);
            repondre(['succes' => true, 'id_switch' => $idS]);
            break;

        case 'modifier_switch':
            $ok = $modeleScenario->modifierSwitch((int)($corps['id_switch'] ?? 0), $corps['nom'] ?? '');
            repondre(['succes' => $ok]);
            break;

        case 'supprimer_switch':
            $ok = $modeleScenario->supprimerSwitch((int)($corps['id_switch'] ?? 0));
            repondre(['succes' => $ok]);
            break;

        case 'maj_position_switch':
            $ok = $modeleScenario->mettreAJourPositionSwitch(
                (int)($corps['id_switch'] ?? 0),
                (float)($corps['x'] ?? 0),
                (float)($corps['y'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        // ---- RÉSEAUX ----

        case 'ajouter_reseau':
            $id  = (int)($corps['id_scenario'] ?? 0);
            verifierAppartenance($id, $idUser, $modeleScenario);
            $res = $modeleScenario->ajouterReseau(
                $id,
                $corps['adresse_reseau'] ?? '',
                (int)($corps['masque'] ?? 24),
                $corps['label'] ?? ''
            );
            repondre($res);
            break;

        case 'supprimer_reseau':
            $ok = $modeleScenario->supprimerReseau((int)($corps['id_reseau'] ?? 0));
            repondre(['succes' => $ok]);
            break;

        // ---- HÔTES ----

        case 'ajouter_hote':
            $id  = (int)($corps['id_scenario'] ?? 0);
            verifierAppartenance($id, $idUser, $modeleScenario);
            $res = $modeleScenario->ajouterHote(
                $id,
                $corps['nom'] ?? '',
                $corps['adresse_ip'] ?? '',
                $corps['passerelle_ip'] ?? '',
                !empty($corps['id_reseau']) ? (int)$corps['id_reseau'] : null,
                (float)($corps['x'] ?? 300),
                (float)($corps['y'] ?? 300)
            );
            repondre($res);
            break;

        case 'modifier_hote':
            $res = $modeleScenario->modifierHote(
                (int)($corps['id_hote'] ?? 0),
                $corps['nom'] ?? '',
                $corps['adresse_ip'] ?? '',
                $corps['passerelle_ip'] ?? '',
                !empty($corps['id_reseau']) ? (int)$corps['id_reseau'] : null
            );
            repondre($res);
            break;

        case 'supprimer_hote':
            $ok = $modeleScenario->supprimerHote((int)($corps['id_hote'] ?? 0));
            repondre(['succes' => $ok]);
            break;

        case 'maj_position_hote':
            $ok = $modeleScenario->mettreAJourPositionHote(
                (int)($corps['id_hote'] ?? 0),
                (float)($corps['x'] ?? 0),
                (float)($corps['y'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        // ---- CÂBLAGES ----

        case 'cabler_hote_switch':
            $ok = $modeleScenario->cablerHoteSwitch(
                (int)($corps['id_hote'] ?? 0),
                (int)($corps['id_switch'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        case 'decabler_hote_switch':
            $ok = $modeleScenario->decablerHoteSwitch(
                (int)($corps['id_hote'] ?? 0),
                (int)($corps['id_switch'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        case 'cabler_interface_switch':
            $ok = $modeleScenario->cablerInterfaceSwitch(
                (int)($corps['id_interface'] ?? 0),
                (int)($corps['id_switch'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        case 'cabler_interface_interface':
            $ok = $modeleScenario->cablerInterfaceInterface(
                (int)($corps['id_interface'] ?? 0),
                (int)($corps['id_interface_1'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        case 'decabler_interface_switch':
            $ok = $modeleScenario->decablerInterfaceSwitch(
                (int)($corps['id_interface'] ?? 0),
                (int)($corps['id_switch']    ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        case 'decabler_interface_interface':
            $ok = $modeleScenario->decablerInterfaceInterface(
                (int)($corps['id_interface'] ?? 0),
                (int)($corps['id_interface_1'] ?? 0)
            );
            repondre(['succes' => $ok]);
            break;

        // ---- SIMULATION IP ----

        case 'simuler':
            $idScenario = (int)($corps['id_scenario'] ?? 0);
            verifierAppartenance($idScenario, $idUser, $modeleScenario);

            $ipSource = trim($corps['ip_source'] ?? '');
            $ipDest   = trim($corps['ip_destination'] ?? '');

            if (!$ipSource || !$ipDest) {
                erreur("Les adresses IP source et destination sont requises.");
            }

            $moteur   = new MoteurRoutage($idScenario);
            $resultat = $moteur->simuler($ipSource, $ipDest);
            repondre($resultat);
            break;

        // ---- ADMINISTRATION (admin uniquement) ----

        case 'lister_utilisateurs':
            exigerAdmin();
            repondre($modeleUtilisateur->listerTous());
            break;

        case 'creer_utilisateur':
            exigerAdmin();
            $res = $modeleUtilisateur->creer(
                $corps['identifiant'] ?? '',
                $corps['mot_de_passe'] ?? '',
                $corps['role'] ?? 'membre'
            );
            repondre($res);
            break;

        case 'modifier_role':
            exigerAdmin();
            $res = $modeleUtilisateur->modifierRole(
                (int)($corps['id_user'] ?? 0),
                $corps['role'] ?? ''
            );
            repondre($res);
            break;

        case 'supprimer_utilisateur':
            exigerAdmin();
            $res = $modeleUtilisateur->supprimer((int)($corps['id_user'] ?? 0));
            repondre($res);
            break;

        case 'reinitialiser_mdp':
            exigerAdmin();
            $res = $modeleUtilisateur->reinitialiserMotDePasse(
                (int)($corps['id_user'] ?? 0),
                $corps['nouveau_mdp'] ?? ''
            );
            repondre($res);
            break;

        default:
            http_response_code(404);
            erreur("Action '$action' inconnue.");
    }

} catch (Exception $e) {
    error_log('[ERREUR API] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erreur' => 'Erreur interne du serveur.']);
}

// =============================================================================
// FONCTIONS UTILITAIRES
// =============================================================================

/**
 * Envoie une réponse JSON avec le code HTTP 200 et termine l'exécution.
 *
 * @param mixed $donnees Données à sérialiser en JSON
 */
function repondre(mixed $donnees): void {
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envoie une réponse d'erreur JSON (400) et termine l'exécution.
 *
 * @param string $message Message d'erreur
 */
function erreur(string $message): never {
    http_response_code(400);
    echo json_encode(['erreur' => $message]);
    exit;
}

/**
 * Vérifie que le scénario appartient à l'utilisateur connecté.
 * Protège contre l'accès aux données d'autres utilisateurs (multi-tenancy).
 */
function verifierAppartenance(int $idScenario, int $idUser, Scenario $modele): void {
    if ($idScenario && !$modele->appartientA($idScenario, $idUser)) {
        http_response_code(403);
        echo json_encode(['erreur' => 'Accès refusé à ce scénario.']);
        exit;
    }
}

/**
 * Vérifie que l'utilisateur courant est administrateur.
 */
function exigerAdmin(): void {
    if (!GestionnaireAuth::estAdmin()) {
        http_response_code(403);
        echo json_encode(['erreur' => 'Accès réservé aux administrateurs.']);
        exit;
    }
}
?>
