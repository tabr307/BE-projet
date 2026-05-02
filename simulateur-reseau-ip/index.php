<?php
/**
 * index.php
 * Routeur principal. Gère l'affichage selon l'état de la session réelle.
 */

// 1. Sécurisation de la session (doit être AVANT session_start)
session_set_cookie_params(['path' => '/']); 
session_start();

// 2. Mode Débogage (À désactiver en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'configuration.php';

// 3. Logique de connexion
$idUser = $_SESSION['utilisateur_id'] ?? null;
$estConnecte = isset($idUser);

// 4. Routage et Whitelist (Sécurité)
$page = $_GET['page'] ?? 'tableau-de-bord';
$pagesAutorisees = ['tableau-de-bord', 'editeur', 'connexion'];

// Si non connecté, on impose la page de connexion
if (!$estConnecte) {
    $page = 'connexion';
} 

// Si la page demandée n'existe pas dans la whitelist, retour au tableau de bord
if (!in_array($page, $pagesAutorisees)) {
    $page = 'tableau-de-bord';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulateur IP - <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $page))); ?></title>
    
    <!-- CSS Globaux -->
    <link rel="stylesheet" href="frontend/css/variables.css">
    <link rel="stylesheet" href="frontend/css/mise-en-page.css">
    <link rel="stylesheet" href="frontend/css/composants.css">
    
    <!-- CSS Spécifique à la page de connexion -->
    <?php if ($page === 'connexion'): ?>
        <link rel="stylesheet" href="frontend/css/connexion.css">
    <?php endif; ?>

    <!-- CSS Spécifique à l'éditeur (C'est ce qui manquait pour le layout flex) -->
    <?php if ($page === 'editeur'): ?>
        <link rel="stylesheet" href="frontend/css/editeur.css">
    <?php endif; ?>
</head>
<body>

    <?php 
    // On n'affiche l'entête que si l'utilisateur est connecté
    if ($estConnecte) {
        include_once 'frontend/partiels/entete.php';
    } 
    ?>

    <main id="app">
        <?php 
        $cheminVue = "frontend/vues/{$page}.php";
        if (file_exists($cheminVue)) {
            include_once $cheminVue;
        } else {
            echo "<div class='conteneur'><p>Erreur : La vue '{$page}' est introuvable.</p></div>";
        }
        ?>
    </main>

    <!-- Scripts globaux -->
    <script src="frontend/js/application-client.js"></script>
    
    <!-- Script spécifique à l'éditeur (vis.js) -->
    <?php if ($page === 'editeur'): ?>
        <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
        <script src="frontend/js/moteur-visuel.js"></script>
    <?php endif; ?>

</body>
</html>