<?php
// backend/api.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/noyau/BaseDeDonnees.php';
require_once __DIR__ . '/modeles/Scenario.php';

try {
    $pdo = BaseDeDonnees::obtenirInstance();
    $modeleScenario = new Scenario($pdo);

    // Extraction du flux JSON pour les méthodes POST/PUT/DELETE
    $donneesEntrantes = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Détection de l'action (Priorité au payload JSON, fallback sur GET)
    $action = $donneesEntrantes['action'] ?? filter_input(INPUT_GET, 'action');

    if (!$action) {
        throw new InvalidArgumentException("Paramètre de routage 'action' manquant.");
    }

    $reponse = [];

    // Routage conditionnel strict
    switch ($action) {
        // ==============================================================================
        // WBS 1.4.2 : ROUTEURS
        // ==============================================================================
        case 'creer_routeur':
            $reponse = ['id' => $modeleScenario->creerRouteur($donneesEntrantes['scenario_id'], $donneesEntrantes['nom'])];
            break;
        case 'lire_routeurs':
            $scenario_id = filter_input(INPUT_GET, 'scenario_id', FILTER_VALIDATE_INT);
            $reponse = $modeleScenario->lireRouteurs($scenario_id);
            break;
        case 'mettre_a_jour_routeur':
            $reponse = ['succes' => $modeleScenario->mettreAJourRouteur($donneesEntrantes['id'], $donneesEntrantes['nom'])];
            break;
        case 'supprimer_routeur':
            $reponse = ['succes' => $modeleScenario->supprimerRouteur($donneesEntrantes['id'])];
            break;

        // ==============================================================================
        // WBS 1.4.3 : SWITCHS
        // ==============================================================================
        case 'creer_switch':
            $reponse = ['id' => $modeleScenario->creerSwitch($donneesEntrantes['sous_reseau_id'], $donneesEntrantes['nom'])];
            break;
        case 'lire_switchs':
            $sous_reseau_id = filter_input(INPUT_GET, 'sous_reseau_id', FILTER_VALIDATE_INT);
            $reponse = $modeleScenario->lireSwitchs($sous_reseau_id);
            break;
        case 'mettre_a_jour_switch':
            $reponse = ['succes' => $modeleScenario->mettreAJourSwitch($donneesEntrantes['id'], $donneesEntrantes['nom'])];
            break;
        case 'supprimer_switch':
            $reponse = ['succes' => $modeleScenario->supprimerSwitch($donneesEntrantes['id'])];
            break;

        // ==============================================================================
        // WBS 1.4.4 : SOUS-RÉSEAUX
        // ==============================================================================
        case 'creer_sous_reseau':
            $reponse = ['id' => $modeleScenario->creerSousReseau($donneesEntrantes['scenario_id'], $donneesEntrantes['nom'], $donneesEntrantes['bloc_cidr'])];
            break;
        case 'lire_sous_reseaux':
            $scenario_id = filter_input(INPUT_GET, 'scenario_id', FILTER_VALIDATE_INT);
            $reponse = $modeleScenario->lireSousReseaux($scenario_id);
            break;
        case 'mettre_a_jour_sous_reseau':
            $reponse = ['succes' => $modeleScenario->mettreAJourSousReseau($donneesEntrantes['id'], $donneesEntrantes['nom'], $donneesEntrantes['bloc_cidr'])];
            break;
        case 'supprimer_sous_reseau':
            $reponse = ['succes' => $modeleScenario->supprimerSousReseau($donneesEntrantes['id'])];
            break;

        // ==============================================================================
        // WBS 1.4.5 : HÔTES
        // ==============================================================================
        case 'creer_hote':
            $reponse = ['id' => $modeleScenario->creerHote($donneesEntrantes['sous_reseau_id'], $donneesEntrantes['nom'], $donneesEntrantes['adresse_ip'], $donneesEntrantes['passerelle_defaut'] ?? null)];
            break;
        case 'lire_hotes':
            $sous_reseau_id = filter_input(INPUT_GET, 'sous_reseau_id', FILTER_VALIDATE_INT);
            $reponse = $modeleScenario->lireHotes($sous_reseau_id);
            break;
        case 'mettre_a_jour_hote':
            $reponse = ['succes' => $modeleScenario->mettreAJourHote($donneesEntrantes['id'], $donneesEntrantes['nom'], $donneesEntrantes['adresse_ip'], $donneesEntrantes['passerelle_defaut'] ?? null)];
            break;
        case 'supprimer_hote':
            $reponse = ['succes' => $modeleScenario->supprimerHote($donneesEntrantes['id'])];
            break;

        // ==============================================================================
        // FALLBACK
        // ==============================================================================
        default:
            http_response_code(400);
            throw new OutOfBoundsException("Vecteur d'action non reconnu par le routeur : {$action}");
    }

    echo json_encode($reponse, JSON_THROW_ON_ERROR);

} catch (InvalidArgumentException | OutOfBoundsException $e) {
    http_response_code(400);
    echo json_encode(['erreur' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erreur API Globale : " . $e->getMessage()); // Journalisation interne
    echo json_encode(['erreur' => 'Erreur critique du serveur API. Vérifiez les logs.']);
}