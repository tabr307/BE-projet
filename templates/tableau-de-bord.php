<?php
/**
 * frontend/vues/tableau-de-bord.php
 * Vue adaptée au nouveau schéma SQL (sans la colonne cree_le).
 */
require_once 'backend/noyau/BaseDeDonnees.php';
require_once 'backend/modeles/Scenario.php';

$pdo = BaseDeDonnees::obtenirInstance();
$scenarioModel = new Scenario($pdo);

// Récupération des scénarios (le modèle utilise déjà des alias id et nom)
$scenarios = $scenarioModel->lireScenariosParUtilisateur($_SESSION['utilisateur_id']);
?>

<div class="conteneur">
    <div class="en-tete-section">
        <h2>Mes scénarios</h2>
        <button type="button" id="btn-creer-scenario" class="btn-principal">
            + Nouveau scénario
        </button>
    </div>

    <div class="grille-scenarios">
        <?php foreach ($scenarios as $s): ?>
            <div class="carte-scenario">
                <div>
                    <h3>
                        <a href="index.php?page=editeur&id=<?php echo $s['id']; ?>" class="lien-titre">
                            <?php echo htmlspecialchars($s['nom']); ?>
                        </a>
                    </h3>
                    <!-- On retire la ligne 'Créé le' car la colonne n'existe plus en BDD -->
                    <p class="texte-muet">Scénario de simulation IP</p>
                </div>
                <div class="actions">
                    <button type="button" 
                            class="btn-outline btn-danger btn-supprimer-scenario" 
                            data-id="<?php echo $s['id']; ?>">
                        Supprimer
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="carte-scenario carte-nouveau" id="zone-nouveau-scenario" style="cursor: pointer;">
            <span class="plus-icon">+</span>
            <span class="texte-muet">Nouveau scénario</span>
        </div>
    </div>
</div>