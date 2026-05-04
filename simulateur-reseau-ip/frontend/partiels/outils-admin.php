<?php
// =============================================================================
// PARTIEL : outils-admin.php
// Auteur : Étudiant
// Description : Panel d'administration pour la gestion des utilisateurs.
//               Inclus uniquement pour les utilisateurs avec le rôle 'admin'.
// =============================================================================

// Vérification de sécurité supplémentaire
if (!GestionnaireAuth::estAdmin()) return;
?>

<!-- Bouton d'accès au panel admin dans la navigation -->
<div class="modal-fond" id="modal-admin" aria-hidden="true">
    <div class="modal modal-large" role="dialog" aria-labelledby="admin-titre">
        <div class="modal-entete">
            <h2 class="modal-titre" id="admin-titre">⚙ Administration des utilisateurs</h2>
            <button class="modal-fermer" data-modal="modal-admin">&times;</button>
        </div>
        <div class="modal-corps">

            <!-- Formulaire de création d'utilisateur -->
            <div class="admin-section">
                <h3 class="admin-section-titre">Créer un utilisateur</h3>
                <div class="grille-3">
                    <div class="champ-groupe">
                        <label for="admin-new-identifiant" class="champ-label">Identifiant *</label>
                        <input type="text" id="admin-new-identifiant" class="champ-input" placeholder="identifiant">
                    </div>
                    <div class="champ-groupe">
                        <label for="admin-new-mdp" class="champ-label">Mot de passe *</label>
                        <input type="password" id="admin-new-mdp" class="champ-input" placeholder="min. 6 caractères">
                    </div>
                    <div class="champ-groupe">
                        <label for="admin-new-role" class="champ-label">Rôle *</label>
                        <select id="admin-new-role" class="champ-input">
                            <option value="membre">Membre</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
                <button class="bouton bouton-principal" id="btn-admin-creer-user">
                    Créer l'utilisateur
                </button>
                <div id="admin-message" class="alerte" style="display:none; margin-top:0.75rem;"></div>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="admin-section">
                <h3 class="admin-section-titre">Utilisateurs existants</h3>
                <div id="admin-liste-users" class="table-conteneur">
                    <p class="texte-secondaire">Chargement...</p>
                </div>
            </div>

        </div>
        <div class="modal-pied">
            <button class="bouton bouton-secondaire" data-fermer="modal-admin">Fermer</button>
        </div>
    </div>
</div>

<script>
// =============================================================================
// SCRIPT : Panel d'administration
// =============================================================================

/**
 * Charge et affiche la liste des utilisateurs dans le panel admin.
 */
async function chargerUtilisateurs() {
    const conteneur = document.getElementById('admin-liste-users');
    if (!conteneur) return;

    try {
        const rep  = await fetch('/simulateur-reseau-ip/backend/api.php?action=lister_utilisateurs');
        const data = await rep.json();

        if (!Array.isArray(data) || data.length === 0) {
            conteneur.innerHTML = '<p class="texte-secondaire">Aucun utilisateur.</p>';
            return;
        }

        // Construction du tableau HTML
        let html = `
            <table class="tableau-admin">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Identifiant</th>
                        <th>Rôle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(user => {
            html += `
                <tr data-id="${user.id_user}">
                    <td>${user.id_user}</td>
                    <td>${escHtml(user.identifiant)}</td>
                    <td>
                        <select class="champ-input champ-input-sm select-role" data-id="${user.id_user}">
                            <option value="membre" ${user.role === 'membre' ? 'selected' : ''}>Membre</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                        </select>
                    </td>
                    <td class="actions-cellule">
                        <button class="bouton bouton-sm bouton-secondaire btn-reinit-mdp" data-id="${user.id_user}" data-nom="${escHtml(user.identifiant)}">
                            Réinit. MDP
                        </button>
                        <button class="bouton bouton-sm bouton-danger btn-suppr-user" data-id="${user.id_user}" data-nom="${escHtml(user.identifiant)}">
                            Supprimer
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        conteneur.innerHTML = html;

        // Événements sur la liste générée
        conteneur.querySelectorAll('.select-role').forEach(sel => {
            sel.addEventListener('change', modifierRole);
        });
        conteneur.querySelectorAll('.btn-reinit-mdp').forEach(btn => {
            btn.addEventListener('click', reinitialiserMdp);
        });
        conteneur.querySelectorAll('.btn-suppr-user').forEach(btn => {
            btn.addEventListener('click', supprimerUtilisateur);
        });

    } catch (e) {
        conteneur.innerHTML = '<p class="alerte alerte-erreur">Erreur de chargement.</p>';
    }
}

/**
 * Modifie le rôle d'un utilisateur.
 */
async function modifierRole(e) {
    const id   = parseInt(e.target.dataset.id);
    const role = e.target.value;

    const rep  = await fetch('/simulateur-reseau-ip/backend/api.php?action=modifier_role', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_user: id, role })
    });
    const data = await rep.json();
    afficherMessageAdmin(data.succes ? 'Rôle mis à jour.' : (data.message || 'Erreur.'), data.succes);
}

/**
 * Réinitialise le mot de passe d'un utilisateur après confirmation.
 */
async function reinitialiserMdp(e) {
    const id  = parseInt(e.target.dataset.id);
    const nom = e.target.dataset.nom;
    const mdp = prompt(`Nouveau mot de passe pour "${nom}" (min. 6 caractères) :`);

    if (!mdp) return;
    if (mdp.length < 6) {
        afficherMessageAdmin('Le mot de passe doit contenir au moins 6 caractères.', false);
        return;
    }

    const rep  = await fetch('/simulateur-reseau-ip/backend/api.php?action=reinitialiser_mdp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_user: id, nouveau_mdp: mdp })
    });
    const data = await rep.json();
    afficherMessageAdmin(data.succes ? 'Mot de passe réinitialisé.' : (data.message || 'Erreur.'), data.succes);
}

/**
 * Supprime un utilisateur après confirmation.
 */
async function supprimerUtilisateur(e) {
    const id  = parseInt(e.target.dataset.id);
    const nom = e.target.dataset.nom;

    if (!confirm(`Supprimer l'utilisateur "${nom}" et tous ses scénarios ?`)) return;

    const rep  = await fetch('/simulateur-reseau-ip/backend/api.php?action=supprimer_utilisateur', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_user: id })
    });
    const data = await rep.json();

    if (data.succes) {
        document.querySelector(`tr[data-id="${id}"]`)?.remove();
        afficherMessageAdmin('Utilisateur supprimé.', true);
    } else {
        afficherMessageAdmin(data.message || 'Erreur.', false);
    }
}

/**
 * Crée un nouvel utilisateur.
 */
document.getElementById('btn-admin-creer-user')?.addEventListener('click', async function() {
    const identifiant = document.getElementById('admin-new-identifiant').value.trim();
    const mdp         = document.getElementById('admin-new-mdp').value;
    const role        = document.getElementById('admin-new-role').value;

    if (!identifiant || !mdp) {
        afficherMessageAdmin('Tous les champs sont requis.', false);
        return;
    }

    const rep  = await fetch('/simulateur-reseau-ip/backend/api.php?action=creer_utilisateur', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifiant, mot_de_passe: mdp, role })
    });
    const data = await rep.json();

    if (data.succes) {
        afficherMessageAdmin(data.message || 'Utilisateur créé.', true);
        document.getElementById('admin-new-identifiant').value = '';
        document.getElementById('admin-new-mdp').value = '';
        chargerUtilisateurs(); // Rafraîchissement de la liste
    } else {
        afficherMessageAdmin(data.message || 'Erreur.', false);
    }
});

/**
 * Affiche un message dans le panel admin.
 * @param {string}  message  Texte à afficher
 * @param {boolean} succes   True = succès (vert), false = erreur (rouge)
 */
function afficherMessageAdmin(message, succes) {
    const el = document.getElementById('admin-message');
    if (!el) return;
    el.style.display = 'block';
    el.className     = `alerte ${succes ? 'alerte-succes' : 'alerte-erreur'}`;
    el.textContent   = message;
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

/**
 * Échappe les caractères HTML pour prévenir les XSS.
 * @param {string} str Chaîne à échapper
 * @returns {string} Chaîne échappée
 */
function escHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}

// Ouverture du panel admin (depuis le menu utilisateur)
document.querySelector('a[href*="panel=admin"]')?.addEventListener('click', function(e) {
    e.preventDefault();
    const modal = document.getElementById('modal-admin');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('actif');
    chargerUtilisateurs();
});

// Chargement automatique si le panel est demandé en paramètre URL
if (new URLSearchParams(window.location.search).get('panel') === 'admin') {
    const modal = document.getElementById('modal-admin');
    if (modal) {
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('actif');
        chargerUtilisateurs();
    }
}
</script>
