<?php
// =============================================================================
// POINT D'ENTRÉE : index.php
// Auteur : Étudiant
// Description : Contrôleur frontal unique. Gère le routage des vues,
//               la connexion et la déconnexion des utilisateurs.
// =============================================================================

require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/backend/noyau/GestionnaireAuth.php';

// --- Traitement du formulaire de connexion (action POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'connexion') {
        $identifiant = trim($_POST['identifiant'] ?? '');
        $motDePasse  = $_POST['mot_de_passe'] ?? '';

        if (GestionnaireAuth::connecter($identifiant, $motDePasse)) {
            header('Location: /simulateur-reseau-ip/?vue=tableau-de-bord');
        } else {
            header('Location: /simulateur-reseau-ip/?vue=connexion&erreur=1');
        }
        exit;
    }

    // Pour action=inscription : laisser passer vers la vue connexion.php
    // qui gère elle-même l'inscription dans son propre bloc PHP
    if ($_POST['action'] === 'inscription') {
        // On charge la vue connexion qui traitera l'inscription
        require_once __DIR__ . '/configuration.php';
        require_once __DIR__ . '/frontend/vues/connexion.php';
        exit;
    }
}

// --- Traitement de la déconnexion ---
if (isset($_GET['action']) && $_GET['action'] === 'deconnecter') {
    GestionnaireAuth::deconnecter();
    header('Location: /simulateur-reseau-ip/?vue=connexion&deconnecte=1');
    exit;
}

// --- Routage des vues ---
$vue = $_GET['vue'] ?? '';

// Redirection automatique selon l'état de connexion
if (empty($vue)) {
    if (GestionnaireAuth::estConnecte()) {
        header('Location: /simulateur-reseau-ip/?vue=tableau-de-bord');
    } else {
        header('Location: /simulateur-reseau-ip/?vue=connexion');
    }
    exit;
}

// --- Inclusion de la vue demandée ---
$vuesFichiers = [
    'connexion'        => __DIR__ . '/frontend/vues/connexion.php',
    'inscription'      => __DIR__ . '/frontend/vues/inscription.php',
    'tableau-de-bord'  => __DIR__ . '/frontend/vues/tableau-de-bord.php',
    'editeur'          => __DIR__ . '/frontend/vues/editeur.php',
];

// Vues publiques (accessibles sans connexion)
$vuesPubliques = ['connexion', 'inscription'];

if (array_key_exists($vue, $vuesFichiers)) {
    // Protection des vues privées
    if (!in_array($vue, $vuesPubliques)) {
        GestionnaireAuth::exigerConnexion();
    }
    require_once $vuesFichiers[$vue];
} else {
    // Vue introuvable : redirection vers l'accueil
    header('Location: /simulateur-reseau-ip/');
    exit;
}
?>
