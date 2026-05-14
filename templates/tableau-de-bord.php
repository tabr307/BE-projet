<?php
/**
 * frontend/vues/tableau-de-bord.php
 * Vue adaptée au nouveau schéma SQL (sans la colonne cree_le).
 */
require_once '../core/BaseDeDonnees.php';
require_once '../src/Model/Scenario.php';

$pdo = BaseDeDonnees::obtenirInstance();
$scenarioModel = new Scenario($pdo);

// Récupération des scénarios (le modèle utilise déjà des alias id et nom)
$scenarios = $scenarioModel->lireScenariosParUtilisateur($_SESSION['utilisateur_id']);

$estAdmin = ($_SESSION['role'] ?? 'membre') === 'admin';
$utilisateurs = [];
$scenariosVides = [];
if ($estAdmin) {
    require_once '../src/Model/Utilisateur.php';
    $userModel = new Utilisateur($pdo);
    $utilisateurs = $userModel->listerTous();
    $scenariosVides = $scenarioModel->listerScenariosVides();
}
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

    <?php if ($estAdmin): ?>
    <div class="en-tete-section" style="margin-top: 4rem;">
        <h2>Panneau d'Administration</h2>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- Utilisateurs -->
        <div style="background: var(--blanc); padding: 1.5rem; border-radius: 8px; border: 1px solid var(--bordure);">
            <h3>Utilisateurs Inscrits</h3>
            <ul style="list-style: none; padding: 0; margin-top: 1rem;">
                <?php foreach ($utilisateurs as $u): ?>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--bordure-douce);">
                        <span>
                            <strong><?= htmlspecialchars($u['identifiant']) ?></strong> 
                            <span style="font-size: 0.8rem; color: var(--texte-muet);">(<?= $u['role'] ?>)</span>
                        </span>
                        <?php if ($u['id'] !== $_SESSION['utilisateur_id'] && $u['role'] !== 'admin'): ?>
                            <button onclick="bannirUtilisateur(<?= $u['id'] ?>)" style="background: var(--danger); color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">Bannir</button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Purge -->
        <div style="background: var(--blanc); padding: 1.5rem; border-radius: 8px; border: 1px solid var(--bordure);">
            <h3>Scénarios Orphelins (Vides)</h3>
            <p style="margin-top: 1rem; color: var(--texte-muet);">
                Il y a actuellement <strong><?= count($scenariosVides) ?></strong> scénario(s) sans aucun équipement.
            </p>
            <?php if (count($scenariosVides) > 0): ?>
                <button onclick="purgerScenariosVides()" class="btn-principal" style="margin-top: 1rem; background: var(--danger);">
                    Purger tous les scénarios vides
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
    async function bannirUtilisateur(id) {
        if (confirm("Êtes-vous sûr de vouloir bannir cet utilisateur et supprimer toutes ses données ?")) {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'bannir_utilisateur', id_utilisateur: id })
            }).then(r => r.json());
            
            if (res.statut === 'succes') {
                location.reload();
            } else {
                alert("Erreur: " + res.message);
            }
        }
    }

    async function purgerScenariosVides() {
        if (confirm("Voulez-vous vraiment supprimer définitivement tous les scénarios vides ?")) {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'purger_scenarios_vides' })
            }).then(r => r.json());
            
            if (res.statut === 'succes') {
                alert(res.supprimes + " scénario(s) supprimé(s).");
                location.reload();
            } else {
                alert("Erreur: " + res.message);
            }
        }
    }
    </script>
    <?php endif; ?>

    <!-- MODALE DE SAISIE DE TEXTE POUR NOUVEAU SCENARIO -->
    <div id="modal-saisie-scenario" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:3000; display:flex; justify-content:center; align-items:center;">
        <div style="background:var(--blanc); padding:24px; border-radius:8px; width:400px; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0; color:#3b82f6; font-size:1.1rem; border-bottom:1px solid var(--bordure); padding-bottom:10px;">Nouveau Scénario</h3>
            <label id="texte-saisie-scenario" style="display:block; font-size:14px; color:var(--texte-principal); margin:15px 0 10px; font-weight:bold;">Entrez le nom du nouveau scénario :</label>
            <input type="text" id="input-saisie-scenario" style="width:100%; padding:10px; border:1px solid var(--bordure); border-radius:4px; font-size:14px; margin-bottom:20px; background:var(--bg-clair); color:var(--texte-principal);">
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button id="btn-annuler-saisie-scenario" type="button" style="padding:8px 16px; border:1px solid var(--bordure); background:var(--bg-clair); color:var(--texte-principal); border-radius:4px; cursor:pointer; font-weight:bold;">Annuler</button>
                <button id="btn-valider-saisie-scenario" type="button" style="padding:8px 16px; border:none; background:#3b82f6; color:#ffffff; border-radius:4px; cursor:pointer; font-weight:bold;">Créer</button>
            </div>
        </div>
    </div>

    <div id="modal-confirmation-scenario" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:3000; display:flex; justify-content:center; align-items:center;">
        <div style="background:var(--blanc); padding:24px; border-radius:8px; width:400px; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0; color:var(--danger, #ef4444); font-size:1.1rem; border-bottom:1px solid var(--bordure); padding-bottom:10px;">Confirmation requise</h3>
            <p style="font-size:14px; color:var(--texte-principal); margin:20px 0;">Voulez-vous vraiment supprimer ce scénario et tout son contenu ? Cette action est irréversible.</p>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button id="btn-annuler-confirmation-scenario" type="button" style="padding:8px 16px; border:1px solid var(--bordure); background:var(--bg-clair); color:var(--texte-principal); border-radius:4px; cursor:pointer; font-weight:bold;">Annuler</button>
                <button id="btn-valider-confirmation-scenario" type="button" style="padding:8px 16px; border:none; background:var(--danger, #ef4444); color:#ffffff; border-radius:4px; cursor:pointer; font-weight:bold;">Supprimer</button>
            </div>
        </div>
    </div>
</div>

