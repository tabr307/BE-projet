<?php
/**
 * public/index.php
 * Contrôleur frontal unique. Gère la session, l'authentification et le routage des vues.
 */

// 1. Sécurisation de la session (Stricte, avant instanciation)
session_set_cookie_params(['path' => '/']); 
session_start();

// Mode Débogage (À commenter en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Importation des dépendances du noyau
require_once __DIR__ . '/../config/configuration.php';
require_once __DIR__ . '/../core/BaseDeDonnees.php';
require_once __DIR__ . '/../core/GestionnaireAuth.php';

// 3. Initialisation de l'accès aux données et de l'authentification
try {
    $pdo = BaseDeDonnees::obtenirInstance();
    $auth = new GestionnaireAuth($pdo);
} catch (Exception $e) {
    die("Erreur critique d'initialisation : " . $e->getMessage());
}

// 4. Sécurisation du routage (Whitelist)
$pageDemande = $_GET['page'] ?? 'tableau-de-bord';
$pagesAutorisees = ['tableau-de-bord', 'editeur', 'connexion'];

$page = in_array($pageDemande, $pagesAutorisees) ? $pageDemande : 'tableau-de-bord';

// 5. Contrôle d'accès (Redirection forcée si non authentifié)
if (!$auth->estConnecte() && $page !== 'connexion') {
    header('Location: index.php?page=connexion');
    exit;
}

// 6. Assemblage du DOM
// La variable $page est transmise aux templates pour l'injection dynamique des CSS/JS
require_once __DIR__ . '/../templates/entete.php';

switch ($page) {
    case 'editeur':
        require_once __DIR__ . '/../templates/editeur.php';
        break;
    case 'tableau-de-bord':
        require_once __DIR__ . '/../templates/tableau-de-bord.php';
        break;
    case 'connexion':
        require_once __DIR__ . '/../templates/connexion.php';
        break;
}

require_once __DIR__ . '/../templates/pied-de-page.php';
?>