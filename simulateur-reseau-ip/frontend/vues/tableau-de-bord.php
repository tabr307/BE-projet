<?php
// =============================================================================
// VUE : tableau-de-bord.php
// Auteur : Étudiant
// Description : Liste des scénarios réseau de l'utilisateur connecté.
// =============================================================================

require_once __DIR__ . '/../../backend/noyau/GestionnaireAuth.php';
require_once __DIR__ . '/../../backend/modeles/Scenario.php';

GestionnaireAuth::exigerConnexion();

$idUser    = GestionnaireAuth::getIdUtilisateur();
$estAdmin  = GestionnaireAuth::estAdmin();
$modele    = new Scenario();
$scenarios = $modele->listerParUtilisateur($idUser);

require_once __DIR__ . '/../partiels/entete.php';
?>

<main class="contenu-principal">
    <div class="conteneur">

        <!-- En-tête -->
        <div class="page-entete">
            <div>
                <h1 class="page-titre">Mes Scénarios</h1>
                <p class="page-sous-titre" id="compteur-scenarios">
                    <?= count($scenarios) ?> scénario<?= count($scenarios) > 1 ? 's' : '' ?> réseau configuré<?= count($scenarios) > 1 ? 's' : '' ?>
                </p>
            </div>
            <button class="bouton bouton-principal" id="btn-nouveau-scenario">
                + Nouveau scénario
            </button>
        </div>

        <!-- Grille des scénarios -->
        <?php if (empty($scenarios)): ?>
            <div class="etat-vide" id="etat-vide">
                <div class="etat-vide-icone">📡</div>
                <h2>Aucun scénario pour l'instant</h2>
                <p>Créez votre premier scénario pour commencer à simuler des topologies réseau.</p>
                <button class="bouton bouton-principal" id="btn-nouveau-scenario-vide">
                    Créer mon premier scénario
                </button>
            </div>
        <?php else: ?>
            <div class="grille-scenarios" id="grille-scenarios">
                <?php foreach ($scenarios as $scenario): ?>
                    <div class="carte-scenario" data-id="<?= $scenario['id_scenario'] ?>">
                        <div class="carte-scenario-corps">
                            <div class="carte-scenario-header">
                                <span class="carte-scenario-type">Scénario réseau</span>
                            </div>
                            <h3 class="carte-scenario-nom">
                                <?= htmlspecialchars($scenario['nom_scenario']) ?>
                            </h3>
                            <?php if (!empty($scenario['description'])): ?>
                                <p class="carte-scenario-desc">
                                    <?= htmlspecialchars($scenario['description']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="carte-scenario-actions">
                            <a href="/simulateur-reseau-ip/?vue=editeur&id_scenario=<?= $scenario['id_scenario'] ?>"
                               class="bouton bouton-principal bouton-sm">
                                Ouvrir l'éditeur
                            </a>
                            <button
                                class="bouton bouton-danger bouton-sm btn-supprimer-scenario"
                                data-id="<?= $scenario['id_scenario'] ?>"
                                data-nom="<?= htmlspecialchars($scenario['nom_scenario']) ?>">
                                Supprimer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Modal : Nouveau scénario -->
<div class="modal-fond" id="modal-nouveau-scenario" aria-hidden="true">
    <div class="modal" role="dialog" aria-labelledby="modal-titre">
        <div class="modal-entete">
            <h2 class="modal-titre" id="modal-titre">Nouveau scénario</h2>
            <button class="modal-fermer" aria-label="Fermer">&times;</button>
        </div>
        <div class="modal-corps">
            <div class="champ-groupe">
                <label for="nom-scenario" class="champ-label">Nom du scénario *</label>
                <input type="text" id="nom-scenario" class="champ-input"
                       placeholder="ex: Réseau d'entreprise" maxlength="100">
            </div>
            <div class="champ-groupe">
                <label for="desc-scenario" class="champ-label">Description (optionnelle)</label>
                <textarea id="desc-scenario" class="champ-input champ-textarea"
                          placeholder="Décrivez brièvement ce scénario..." maxlength="255" rows="3"></textarea>
            </div>
        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire modal-annuler">Annuler</button>
            <button class="bouton bouton-principal" id="btn-confirmer-scenario">Créer</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partiels/pied-de-page.php'; ?>

<script>
// =============================================================================
// SCRIPT : Tableau de bord
// =============================================================================

// --- Mise à jour du compteur ---
function mettreAJourCompteur() {
    const cartes  = document.querySelectorAll('.carte-scenario');
    const nb      = cartes.length;
    const el      = document.getElementById('compteur-scenarios');
    if (el) {
        el.textContent = `${nb} scénario${nb > 1 ? 's' : ''} réseau configuré${nb > 1 ? 's' : ''}`;
    }
}

// --- Modal Nouveau scénario ---
function ouvrirModalScenario() {
    const modal = document.getElementById('modal-nouveau-scenario');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('actif');
    document.getElementById('nom-scenario').focus();
}

function fermerModalScenario() {
    const modal = document.getElementById('modal-nouveau-scenario');
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('actif');
    document.getElementById('nom-scenario').value = '';
    document.getElementById('desc-scenario').value = '';
    document.getElementById('nom-scenario').classList.remove('champ-erreur');
}

document.getElementById('btn-nouveau-scenario')?.addEventListener('click', ouvrirModalScenario);
document.getElementById('btn-nouveau-scenario-vide')?.addEventListener('click', ouvrirModalScenario);
document.querySelector('.modal-fermer')?.addEventListener('click', fermerModalScenario);
document.querySelector('.modal-annuler')?.addEventListener('click', fermerModalScenario);
document.getElementById('modal-nouveau-scenario')?.addEventListener('click', e => {
    if (e.target === document.getElementById('modal-nouveau-scenario')) fermerModalScenario();
});

// Validation à la touche Entrée
document.getElementById('nom-scenario')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('btn-confirmer-scenario')?.click();
    document.getElementById('nom-scenario').classList.remove('champ-erreur');
});

// --- Création d'un scénario ---
document.getElementById('btn-confirmer-scenario')?.addEventListener('click', async function() {
    const nom  = document.getElementById('nom-scenario').value.trim();
    const desc = document.getElementById('desc-scenario').value.trim();

    if (!nom) {
        document.getElementById('nom-scenario').classList.add('champ-erreur');
        return;
    }

    this.disabled = true;
    this.textContent = 'Création...';

    try {
        const reponse = await fetch('/simulateur-reseau-ip/backend/api.php?action=creer_scenario', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nom, description: desc })
        });
        const data = await reponse.json();

        if (data.id_scenario) {
            window.location.href = `/simulateur-reseau-ip/?vue=editeur&id_scenario=${data.id_scenario}`;
        } else {
            alert(data.erreur || 'Erreur lors de la création.');
            this.disabled = false;
            this.textContent = 'Créer';
        }
    } catch (e) {
        alert('Erreur de communication avec le serveur.');
        this.disabled = false;
        this.textContent = 'Créer';
    }
});

// --- Suppression d'un scénario (CORRECTION : mise à jour DOM sans reload) ---
function ajouterEcouteursSuppression() {
    document.querySelectorAll('.btn-supprimer-scenario').forEach(btn => {
        // Éviter les doublons d'écouteurs
        btn.replaceWith(btn.cloneNode(true));
    });

    document.querySelectorAll('.btn-supprimer-scenario').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id  = this.dataset.id;
            const nom = this.dataset.nom;

            if (!confirm(`Supprimer le scénario "${nom}" ? Cette action est irréversible.`)) return;

            this.disabled = true;
            this.textContent = '...';

            try {
                const reponse = await fetch('/simulateur-reseau-ip/backend/api.php?action=supprimer_scenario', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_scenario: parseInt(id) })
                });
                const data = await reponse.json();

                if (data.succes) {
                    // 1. Supprimer la carte du DOM
                    const carte = document.querySelector(`.carte-scenario[data-id="${id}"]`);
                    carte?.remove();

                    // 2. Mettre à jour le compteur immédiatement
                    mettreAJourCompteur();

                    // 3. Si plus aucun scénario → afficher l'état vide sans recharger
                    const restantes = document.querySelectorAll('.carte-scenario');
                    if (restantes.length === 0) {
                        const grille = document.getElementById('grille-scenarios');
                        if (grille) {
                            grille.innerHTML = '';
                            grille.id = ''; // on désactive la grille
                        }
                        // Afficher l'état vide dynamiquement
                        const conteneur = document.querySelector('.conteneur');
                        const etatVide = document.createElement('div');
                        etatVide.className = 'etat-vide';
                        etatVide.innerHTML = `
                            <div class="etat-vide-icone">📡</div>
                            <h2>Aucun scénario pour l'instant</h2>
                            <p>Créez votre premier scénario pour commencer à simuler des topologies réseau.</p>
                            <button class="bouton bouton-principal" onclick="ouvrirModalScenario()">
                                Créer mon premier scénario
                            </button>
                        `;
                        conteneur.appendChild(etatVide);
                        mettreAJourCompteur();
                    }
                } else {
                    alert(data.erreur || 'Erreur lors de la suppression.');
                    this.disabled = false;
                    this.textContent = 'Supprimer';
                }
            } catch (e) {
                alert('Erreur de communication avec le serveur.');
                this.disabled = false;
                this.textContent = 'Supprimer';
            }
        });
    });
}

// Initialisation des écouteurs
ajouterEcouteursSuppression();
</script>
