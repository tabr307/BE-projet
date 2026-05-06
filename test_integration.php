<?php
require_once __DIR__ . '/config/configuration.php';
require_once __DIR__ . '/core/BaseDeDonnees.php';
require_once __DIR__ . '/core/MoteurRoutage.php';
require_once __DIR__ . '/src/Model/RouteStatique.php'; 

try {
    $pdo = BaseDeDonnees::obtenirInstance();
    $modeleRoute = new RouteStatique($pdo);
    
    // --- 0. PRÉPARATION DU TERRAIN (Satisfaction des clés étrangères) ---
    // Le Dev 1 a déjà créé l'utilisateur ID 1 (admin) lors de l'amorçage.
    // On crée un scénario ID 1 s'il n'existe pas
    $pdo->exec("INSERT INTO scenario (id, nom, utilisateur_id) VALUES (1, 'Test LPM', 1) ON CONFLICT (id) DO NOTHING");
    // On crée un routeur ID 1 s'il n'existe pas
    $pdo->exec("INSERT INTO routeur (id, nom, pos_x, pos_y, scenario_id) VALUES (1, 'R1', 0, 0, 1) ON CONFLICT (id) DO NOTHING");


    // --- 1. INJECTION DE DONNÉES ---
    // On nettoie les anciennes routes du routeur 1 pour un test propre
    $pdo->exec("DELETE FROM route_statique WHERE routeur_id = 1");

    // Ajout d'une route par défaut (Masque /0)
    $modeleRoute->ajouter(1, '0.0.0.0', 0, '10.0.0.1');
    
    // Ajout d'une route spécifique (Masque /24)
    $modeleRoute->ajouter(1, '192.168.1.0', 24, '10.0.0.254');


    // --- 2. RÉCUPÉRATION DES ROUTES (WBS 3.0) ---
    $tableRoutage = $modeleRoute->listerParRouteur(1); 
    
    
    // --- 3. TEST DU MOTEUR ALGORITHMIQUE (WBS 4.0) ---
    $ipCible = '192.168.1.50';
    echo "================================================\n";
    echo "[TEST] Résolution LPM pour l'IP : $ipCible\n";
    echo "================================================\n";
    
    $routeChoisie = MoteurRoutage::executerLPM($ipCible, $tableRoutage);
    
    if ($routeChoisie) {
        echo "[SUCCÈS] Meilleure route trouvée !\n";
        echo " -> Redirection vers : " . $routeChoisie['next_hop'] . "\n";
        echo " -> Règle appliquée  : Masque /" . $routeChoisie['masque_dest'] . "\n";
    } else {
        echo "[ÉCHEC] Aucune route correspondante (Drop packet).\n";
    }

} catch (Exception $e) {
    echo "[ERREUR CRITIQUE] L'intégration a échoué : " . $e->getMessage() . "\n";
}
?>