/**
 * =============================================================================
 * APPLICATION-CLIENT.JS
 * Auteur : Étudiant
 * Description : Gestion CRUD des équipements, modals, panneau de propriétés
 *               et résultats de simulation dans l'éditeur de topologie.
 *
 * DÉPENDANCE : moteur-visuel.js doit être chargé AVANT ce fichier.
 *              La variable globale `moteurVisuel` est utilisée ici.
 * =============================================================================
 */

'use strict';

// =============================================================================
// UTILITAIRES GLOBAUX
// =============================================================================

/**
 * Échappe les caractères HTML pour prévenir les injections XSS.
 * @param {string} str
 * @returns {string}
 */
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

/**
 * Affiche une notification toast temporaire.
 * @param {string} message
 * @param {'succes'|'erreur'|'info'} type
 */
function afficherNotification(message, type = 'info') {
    const notif = document.createElement('div');
    notif.className = `notif-toast notif-${type}`;
    notif.textContent = message;
    document.body.appendChild(notif);
    /* Animation d'entrée */
    requestAnimationFrame(() => notif.classList.add('notif-visible'));
    setTimeout(() => {
        notif.classList.remove('notif-visible');
        setTimeout(() => notif.remove(), 350);
    }, 3200);
}

/** Raccourci erreur */
function afficherErreur(msg) {
    afficherNotification(msg || 'Une erreur est survenue.', 'erreur');
}

// =============================================================================
// MODULE : API — Wrapper fetch centralisé
// =============================================================================

/**
 * Requête POST JSON vers l'API backend.
 * @param {string} action
 * @param {Object} donnees
 * @returns {Promise<Object>}
 */
async function appelAPI(action, donnees = {}) {
    const reponse = await fetch(`${API_BASE_URL}?action=${encodeURIComponent(action)}`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(donnees),
    });
    const data = await reponse.json();
    if (data.erreur) throw new Error(data.erreur);
    return data;
}

// =============================================================================
// MODULE : MODALS
// =============================================================================

function ouvrirModal(idModal) {
    const m = document.getElementById(idModal);
    if (!m) return;
    m.setAttribute('aria-hidden', 'false');
    m.classList.add('actif');
    m.querySelector('input[type="text"], input[type="number"], select')?.focus();
}

function fermerModal(idModal) {
    const m = document.getElementById(idModal);
    if (!m) return;
    m.setAttribute('aria-hidden', 'true');
    m.classList.remove('actif');
    m.querySelectorAll('input:not([type="hidden"]), textarea').forEach(c => {
        c.value = '';
        c.classList.remove('champ-erreur');
    });
    m.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
}

// Fermeture au clic sur le fond
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-fond') && e.target.classList.contains('actif')) {
        fermerModal(e.target.id);
    }
});

// =============================================================================
// GUARD : vérifie que moteurVisuel est prêt
// =============================================================================

/**
 * Retourne true si le moteur visuel est initialisé.
 * Affiche une erreur explicite sinon.
 */
function moteurPret() {
    if (moteurVisuel) return true;
    afficherErreur('Le moteur visuel n\'est pas encore chargé. Patientez un instant.');
    return false;
}

// =============================================================================
// MODULE : ROUTEURS
// =============================================================================

function ouvrirModalRouteur(idRouteur = null, nomActuel = '') {
    document.getElementById('routeur-id').value  = idRouteur || '';
    document.getElementById('routeur-nom').value = nomActuel;
    document.getElementById('modal-routeur-titre').textContent =
        idRouteur ? 'Modifier le routeur' : 'Ajouter un routeur';
    ouvrirModal('modal-routeur');
}

async function validerRouteur() {
    const id  = document.getElementById('routeur-id').value;
    const nom = document.getElementById('routeur-nom').value.trim();
    if (!nom) { document.getElementById('routeur-nom').classList.add('champ-erreur'); return; }

    try {
        if (id) {
            /* Modification */
            const data = await appelAPI('modifier_routeur', { id_routeur: parseInt(id), nom });
            if (data.succes) {
                moteurVisuel?.renommerNoeud(`R${id}`, nom);
                /* Mise à jour topologie locale */
                const r = (window.topologieCourante.routeurs || []).find(r => r.id_routeur == id);
                if (r) r.nom = nom;
                fermerModal('modal-routeur');
                afficherNotification('Routeur renommé.', 'succes');
            }
        } else {
            /* Création */
            if (!moteurPret()) return;
            const x = 120 + Math.random() * 500;
            const y = 100 + Math.random() * 350;
            const data = await appelAPI('ajouter_routeur', { id_scenario: SCENARIO_ID, nom, x, y });
            if (data.id_routeur) {
                const nouv = { id_routeur: data.id_routeur, nom, pos_x: x, pos_y: y };
                window.topologieCourante.routeurs = window.topologieCourante.routeurs || [];
                window.topologieCourante.routeurs.push(nouv);
                moteurVisuel.ajouterNoeudRouteur({ id: `R${data.id_routeur}`, id_db: data.id_routeur, label: nom, x, y });
                fermerModal('modal-routeur');
                afficherNotification(`Routeur "${nom}" ajouté.`, 'succes');
            }
        }
    } catch (e) { afficherErreur(e.message); }
}

async function supprimerRouteur(idRouteur) {
    if (!confirm('Supprimer ce routeur ? Ses interfaces et routes seront aussi supprimées.')) return;
    try {
        const data = await appelAPI('supprimer_routeur', { id_routeur: idRouteur });
        if (data.succes) {
            moteurVisuel?.supprimerNoeud(`R${idRouteur}`);
            window.topologieCourante.routeurs = (window.topologieCourante.routeurs || []).filter(r => r.id_routeur !== idRouteur);
            fermerPanneau();
            afficherNotification('Routeur supprimé.', 'succes');
        }
    } catch (e) { afficherErreur(e.message); }
}

// =============================================================================
// MODULE : SWITCHS
// =============================================================================

async function validerSwitch() {
    const id  = document.getElementById('switch-id').value;
    const nom = document.getElementById('switch-nom').value.trim();
    if (!nom) { document.getElementById('switch-nom').classList.add('champ-erreur'); return; }

    try {
        if (id) {
            const data = await appelAPI('modifier_switch', { id_switch: parseInt(id), nom });
            if (data.succes) {
                moteurVisuel?.renommerNoeud(`SW${id}`, nom);
                const s = (window.topologieCourante.switchs || []).find(s => s.id_switch == id);
                if (s) s.nom = nom;
                fermerModal('modal-switch');
                afficherNotification('Switch renommé.', 'succes');
            }
        } else {
            if (!moteurPret()) return;
            const x = 150 + Math.random() * 450;
            const y = 150 + Math.random() * 300;
            const data = await appelAPI('ajouter_switch', { id_scenario: SCENARIO_ID, nom, x, y });
            if (data.id_switch) {
                window.topologieCourante.switchs = window.topologieCourante.switchs || [];
                window.topologieCourante.switchs.push({ id_switch: data.id_switch, nom, pos_x: x, pos_y: y });
                moteurVisuel.ajouterNoeudSwitch({ id: `SW${data.id_switch}`, id_db: data.id_switch, label: nom, x, y });
                fermerModal('modal-switch');
                afficherNotification(`Switch "${nom}" ajouté.`, 'succes');
            }
        }
    } catch (e) { afficherErreur(e.message); }
}

async function supprimerSwitch(idSwitch) {
    if (!confirm('Supprimer ce switch ? Les câblages associés seront supprimés.')) return;
    try {
        const data = await appelAPI('supprimer_switch', { id_switch: idSwitch });
        if (data.succes) {
            moteurVisuel?.supprimerNoeud(`SW${idSwitch}`);
            window.topologieCourante.switchs = (window.topologieCourante.switchs || []).filter(s => s.id_switch !== idSwitch);
            fermerPanneau();
            afficherNotification('Switch supprimé.', 'succes');
        }
    } catch (e) { afficherErreur(e.message); }
}

// =============================================================================
// MODULE : RÉSEAUX
// =============================================================================

async function validerReseau() {
    const label   = document.getElementById('reseau-label').value.trim();
    const adresse = document.getElementById('reseau-adresse').value.trim();
    const masque  = parseInt(document.getElementById('reseau-masque').value);

    if (!label || !adresse || isNaN(masque) || masque < 0 || masque > 32) {
        afficherErreur('Tous les champs sont requis (masque : 0-32).');
        return;
    }
    try {
        const data = await appelAPI('ajouter_reseau', {
            id_scenario: SCENARIO_ID, label, adresse_reseau: adresse, masque
        });
        if (data.id) {
            window.topologieCourante.reseaux = window.topologieCourante.reseaux || [];
            window.topologieCourante.reseaux.push({ id_reseau: data.id, adresse_reseau: adresse, masque, label });
            fermerModal('modal-reseau');
            afficherNotification(`Réseau "${label}" ajouté.`, 'succes');
            rafraichirPanneauReseaux();
            peuplerSelectsSimulation(); // Un réseau = potentiels hôtes attachés
        } else {
            afficherErreur(data.message || 'Erreur création réseau.');
        }
    } catch (e) { afficherErreur(e.message); }
}

async function supprimerReseau(idReseau) {
    if (!confirm('Supprimer ce réseau ? Les hôtes rattachés seront désactivés.')) return;
    try {
        const data = await appelAPI('supprimer_reseau', { id_reseau: idReseau });
        if (data.succes) {
            window.topologieCourante.reseaux = (window.topologieCourante.reseaux || []).filter(r => r.id_reseau !== idReseau);
            /* Désactivation des hôtes rattachés dans la topologie locale */
            (window.topologieCourante.hotes || []).forEach(h => {
                if (h.id_reseau == idReseau) {
                    h.id_reseau = null;
                    moteurVisuel?.mettreAJourHote(h.id_hote, h.nom, h.adresse_ip, false);
                }
            });
            fermerPanneau();
            afficherNotification('Réseau supprimé.', 'succes');
            rafraichirPanneauReseaux();
            peuplerSelectsSimulation();
        }
    } catch (e) { afficherErreur(e.message); }
}

// =============================================================================
// MODULE : HÔTES
// =============================================================================

/**
 * Remplit automatiquement le champ CIDR quand un réseau est sélectionné.
 * @param {string|number} idReseau Identifiant du réseau sélectionné
 */
function remplirCidrDepuisReseau(idReseau) {
    const champCidr = document.getElementById('hote-cidr');
    if (!champCidr) return;
    if (!idReseau) {
        champCidr.value = '';
        return;
    }
    const reseau = (window.topologieCourante?.reseaux || []).find(r => r.id_reseau == idReseau);
    if (reseau) {
        champCidr.value = reseau.masque;
    }
}

function peuplerSelectReseaux(valeur = '') {
    const sel = document.getElementById('hote-reseau');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Aucun réseau (hôte désactivé) --</option>';
    /* Ajouter l'attribut onchange si pas déjà présent */
    if (!sel.getAttribute('onchange')) {
        sel.setAttribute('onchange', 'remplirCidrDepuisReseau(this.value)');
    }
    (window.topologieCourante?.reseaux || []).forEach(r => {
        const opt = document.createElement('option');
        opt.value       = r.id_reseau;
        opt.textContent = `${r.label || ''} — ${r.adresse_reseau}/${r.masque}`;
        if (String(valeur) === String(r.id_reseau)) opt.selected = true;
        sel.appendChild(opt);
    });
}

function ouvrirModalHote(idHote = null) {
    document.getElementById('hote-id').value = idHote || '';
    if (!idHote) {
        /* Mode création : on vide tout */
        ['hote-nom','hote-ip','hote-cidr','hote-gw'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        peuplerSelectReseaux();
    }
    document.getElementById('modal-hote-titre').textContent = idHote ? 'Modifier l\'hôte' : 'Ajouter un hôte';
    ouvrirModal('modal-hote');
}

/**
 * Ouvre la modal hôte en mode modification, en pré-remplissant tous les champs
 * y compris le CIDR du réseau associé.
 * @param {number} idHote Identifiant de l'hôte à modifier
 */
function ouvrirModalHoteModif(idHote) {
    const h      = (window.topologieCourante?.hotes || []).find(h => h.id_hote === idHote);
    const reseau = h?.id_reseau
        ? (window.topologieCourante?.reseaux || []).find(r => r.id_reseau == h.id_reseau)
        : null;

    peuplerSelectReseaux(h?.id_reseau || '');
    document.getElementById('hote-id').value  = idHote;
    document.getElementById('hote-nom').value = h?.nom || '';
    document.getElementById('hote-ip').value  = h?.adresse_ip || '';
    document.getElementById('hote-gw').value  = h?.passerelle_ip || '';

    const champCidr = document.getElementById('hote-cidr');
    if (champCidr) champCidr.value = reseau ? reseau.masque : '';

    document.getElementById('modal-hote-titre').textContent = "Modifier l'hôte";
    ouvrirModal('modal-hote');
}

async function validerHote() {
    const id       = document.getElementById('hote-id').value;
    const nom      = document.getElementById('hote-nom').value.trim();
    const ip       = document.getElementById('hote-ip').value.trim();
    const cidr     = document.getElementById('hote-cidr')?.value.trim() || null;
    const gw       = document.getElementById('hote-gw').value.trim();
    const idReseau = document.getElementById('hote-reseau').value || null;

    let ok = true;
    ['hote-nom','hote-ip','hote-gw'].forEach(champId => {
        const el = document.getElementById(champId);
        if (!el.value.trim()) { el.classList.add('champ-erreur'); ok = false; }
    });
    if (!ok) return;

    try {
        if (id) {
            /* Modification */
            const data = await appelAPI('modifier_hote', {
                id_hote: parseInt(id), nom, adresse_ip: ip,
                passerelle_ip: gw, id_reseau: idReseau ? parseInt(idReseau) : null
            });
            if (data.succes) {
                const actif    = idReseau !== null;
                const idHoteNb = parseInt(id);
                /* Récupérer le masque du réseau pour afficher IP/CIDR */
                const reseauModif = (window.topologieCourante.reseaux || []).find(r => r.id_reseau == idReseau);
                const ipAvecCidr  = (actif && reseauModif) ? `${ip}/${reseauModif.masque}` : ip;
                moteurVisuel?.mettreAJourHote(idHoteNb, nom, ipAvecCidr, actif);
                /* Mise à jour topologie locale */
                const h = (window.topologieCourante.hotes || []).find(h => h.id_hote == id);
                if (h) { h.nom = nom; h.adresse_ip = ip; h.passerelle_ip = gw; h.id_reseau = idReseau; }
                fermerModal('modal-hote');
                afficherNotification('Hôte mis à jour.', 'succes');
                /* Rafraîchir le panneau et les selects de simulation */
                afficherPanneauHote(idHoteNb, nom);
                peuplerSelectsSimulation();
            } else {
                afficherErreur(data.message || 'Erreur modification hôte.');
            }
        } else {
            /* Création */
            if (!moteurPret()) return;
            const x = 200 + Math.random() * 400;
            const y = 180 + Math.random() * 280;
            const data = await appelAPI('ajouter_hote', {
                id_scenario: SCENARIO_ID, nom, adresse_ip: ip,
                passerelle_ip: gw, id_reseau: idReseau ? parseInt(idReseau) : null, x, y
            });
            if (data.id) {
                const actif = idReseau !== null;
                const nouv  = { id_hote: data.id, nom, adresse_ip: ip, passerelle_ip: gw, id_reseau: idReseau, pos_x: x, pos_y: y };
                window.topologieCourante.hotes = window.topologieCourante.hotes || [];
                window.topologieCourante.hotes.push(nouv);
                /* Récupérer le masque du réseau pour afficher IP/CIDR */
                const reseauHote = (window.topologieCourante.reseaux || []).find(r => r.id_reseau == idReseau);
                const labelHote  = reseauHote ? `${nom}\n${ip}/${reseauHote.masque}` : `${nom}\n${ip}`;
                moteurVisuel.ajouterNoeudHote({
                    id: `H${data.id}`, id_db: data.id,
                    label: labelHote, x, y, actif,
                });
                fermerModal('modal-hote');
                afficherNotification(`Hôte "${nom}" ajouté.`, 'succes');
                peuplerSelectsSimulation(); // Mettre à jour la liste déroulante
            } else {
                afficherErreur(data.message || 'Erreur création hôte.');
            }
        }
    } catch (e) { afficherErreur(e.message); }
}

async function supprimerHote(idHote) {
    if (!confirm('Supprimer cet hôte ?')) return;
    try {
        const data = await appelAPI('supprimer_hote', { id_hote: idHote });
        if (data.succes) {
            moteurVisuel?.supprimerNoeud(`H${idHote}`);
            window.topologieCourante.hotes = (window.topologieCourante.hotes || []).filter(h => h.id_hote !== idHote);
            fermerPanneau();
            afficherNotification('Hôte supprimé.', 'succes');
            peuplerSelectsSimulation();
        }
    } catch (e) { afficherErreur(e.message); }
}

// =============================================================================
// MODULE : INTERFACES
// =============================================================================

function ouvrirModalInterface(idRouteur, idInterface = null, valeurs = {}) {
    document.getElementById('interface-routeur-id').value = idRouteur;
    document.getElementById('interface-id').value         = idInterface || '';
    document.getElementById('interface-nom').value        = valeurs.nom        || '';
    document.getElementById('interface-ip').value         = valeurs.adresse_ip || '';
    document.getElementById('interface-masque').value     = valeurs.masque     || '';
    document.getElementById('modal-interface-titre').textContent =
        idInterface ? 'Modifier l\'interface' : 'Ajouter une interface';
    ouvrirModal('modal-interface');
}

async function validerInterface() {
    const idRouteur   = parseInt(document.getElementById('interface-routeur-id').value);
    const idInterface = document.getElementById('interface-id').value;
    const nom         = document.getElementById('interface-nom').value.trim();
    const ip          = document.getElementById('interface-ip').value.trim();
    const masque      = parseInt(document.getElementById('interface-masque').value);

    if (!nom || !ip || isNaN(masque)) { afficherErreur('Tous les champs sont requis.'); return; }

    try {
        if (idInterface) {
            const data = await appelAPI('modifier_interface', {
                id_interface: parseInt(idInterface), nom, adresse_ip: ip, masque
            });
            if (data.succes) {
                const i = (window.topologieCourante.interfaces || []).find(i => i.id_interface == idInterface);
                if (i) { i.nom = nom; i.adresse_ip = ip; i.masque = masque; }
                fermerModal('modal-interface');
                afficherNotification('Interface mise à jour.', 'succes');
                rafraichirPanneauRouteur(idRouteur);
            } else { afficherErreur(data.message); }
        } else {
            const data = await appelAPI('ajouter_interface', { id_routeur: idRouteur, nom, adresse_ip: ip, masque });
            if (data.succes) {
                const nouv = { id_interface: data.id, nom, adresse_ip: ip, masque, id_routeur: idRouteur };
                window.topologieCourante.interfaces = window.topologieCourante.interfaces || [];
                window.topologieCourante.interfaces.push(nouv);
                fermerModal('modal-interface');
                afficherNotification('Interface ajoutée.', 'succes');
                rafraichirPanneauRouteur(idRouteur);
            } else { afficherErreur(data.message); }
        }
    } catch (e) { afficherErreur(e.message); }
}

async function supprimerInterface(idInterface, idRouteur) {
    if (!confirm('Supprimer cette interface ? Les câblages associés seront supprimés.')) return;
    try {
        const data = await appelAPI('supprimer_interface', { id_interface: idInterface });
        if (data.succes) {
            window.topologieCourante.interfaces = (window.topologieCourante.interfaces || []).filter(i => i.id_interface !== idInterface);
            moteurVisuel?.supprimerLiensInterface(idInterface);
            afficherNotification('Interface supprimée.', 'succes');
            rafraichirPanneauRouteur(idRouteur);
        }
    } catch (e) { afficherErreur(e.message); }
}

// =============================================================================
// MODULE : ROUTES STATIQUES
// =============================================================================

function ouvrirModalRoute(idRouteur) {
    document.getElementById('route-routeur-id').value = idRouteur;
    ['route-dest','route-masque','route-nexthop'].forEach(id => document.getElementById(id).value = '');
    ouvrirModal('modal-route');
}

async function validerRoute() {
    const idRouteur  = parseInt(document.getElementById('route-routeur-id').value);
    const dest       = document.getElementById('route-dest').value.trim();
    const masqueDest = parseInt(document.getElementById('route-masque').value);
    const nextHop    = document.getElementById('route-nexthop').value.trim();

    if (!dest || isNaN(masqueDest) || !nextHop) { afficherErreur('Tous les champs sont requis.'); return; }

    try {
        const data = await appelAPI('ajouter_route', {
            id_routeur: idRouteur, reseau_dest: dest, masque_dest: masqueDest, next_hop: nextHop
        });
        if (data.succes) {
            window.topologieCourante.routes = window.topologieCourante.routes || [];
            window.topologieCourante.routes.push({ id_route: data.id, reseau_dest: dest, masque_dest: masqueDest, next_hop: nextHop, id_routeur: idRouteur });
            fermerModal('modal-route');
            afficherNotification('Route statique ajoutée.', 'succes');
            rafraichirPanneauRouteur(idRouteur);
        } else { afficherErreur(data.message); }
    } catch (e) { afficherErreur(e.message); }
}

async function supprimerRoute(idRoute, idRouteur) {
    if (!confirm('Supprimer cette route statique ?')) return;
    try {
        const data = await appelAPI('supprimer_route', { id_route: idRoute });
        if (data.succes) {
            window.topologieCourante.routes = (window.topologieCourante.routes || []).filter(r => r.id_route !== idRoute);
            afficherNotification('Route supprimée.', 'succes');
            rafraichirPanneauRouteur(idRouteur);
        }
    } catch (e) { afficherErreur(e.message); }
}

// =============================================================================
// MODULE : CÂBLAGES
// =============================================================================

function peuplerCableChamps() {
    const type    = document.getElementById('cable-type').value;
    const topo    = window.topologieCourante;
    let html = '';

    if (type === 'hote-switch') {
        html  = '<div class="grille-2">';
        html += '<div class="champ-groupe"><label class="champ-label">Hôte *</label><select id="cable-hote" class="champ-input">';
        (topo.hotes || []).forEach(h => { html += `<option value="${h.id_hote}">${escHtml(h.nom)} (${h.adresse_ip || '?'})</option>`; });
        html += '</select></div>';
        html += '<div class="champ-groupe"><label class="champ-label">Switch *</label><select id="cable-switch" class="champ-input">';
        (topo.switchs || []).forEach(s => { html += `<option value="${s.id_switch}">${escHtml(s.nom)}</option>`; });
        html += '</select></div></div>';

    } else if (type === 'interface-switch') {
        html  = '<div class="grille-2">';
        html += '<div class="champ-groupe"><label class="champ-label">Interface *</label><select id="cable-interface" class="champ-input">';
        (topo.interfaces || []).forEach(i => {
            const routeur = (topo.routeurs || []).find(r => r.id_routeur === i.id_routeur);
            html += `<option value="${i.id_interface}">[${escHtml(routeur?.nom || '?')}] ${escHtml(i.nom)} ${i.adresse_ip}/${i.masque}</option>`;
        });
        html += '</select></div>';
        html += '<div class="champ-groupe"><label class="champ-label">Switch *</label><select id="cable-switch" class="champ-input">';
        (topo.switchs || []).forEach(s => { html += `<option value="${s.id_switch}">${escHtml(s.nom)}</option>`; });
        html += '</select></div></div>';

    } else if (type === 'interface-interface') {
        html  = '<div class="grille-2">';
        html += '<div class="champ-groupe"><label class="champ-label">Interface A *</label><select id="cable-i1" class="champ-input">';
        (topo.interfaces || []).forEach(i => {
            const r = (topo.routeurs || []).find(r => r.id_routeur === i.id_routeur);
            html += `<option value="${i.id_interface}">[${escHtml(r?.nom || '?')}] ${escHtml(i.nom)}</option>`;
        });
        html += '</select></div>';
        html += '<div class="champ-groupe"><label class="champ-label">Interface B *</label><select id="cable-i2" class="champ-input">';
        (topo.interfaces || []).forEach(i => {
            const r = (topo.routeurs || []).find(r => r.id_routeur === i.id_routeur);
            html += `<option value="${i.id_interface}">[${escHtml(r?.nom || '?')}] ${escHtml(i.nom)}</option>`;
        });
        html += '</select></div></div>';
    }

    document.getElementById('cable-champs').innerHTML = html;
}

async function validerCable() {
    const type = document.getElementById('cable-type').value;
    try {
        if (type === 'hote-switch') {
            const idHote   = parseInt(document.getElementById('cable-hote').value);
            const idSwitch = parseInt(document.getElementById('cable-switch').value);
            const data = await appelAPI('cabler_hote_switch', { id_hote: idHote, id_switch: idSwitch });
            if (data.succes) {
                moteurVisuel?.ajouterLienHoteSwitch(idHote, idSwitch);
                window.topologieCourante.cables = window.topologieCourante.cables || {};
                window.topologieCourante.cables.hote_switch = window.topologieCourante.cables.hote_switch || [];
                window.topologieCourante.cables.hote_switch.push({ id_hote: idHote, id_switch: idSwitch });
                fermerModal('modal-cable');
                afficherNotification('Câblage Hôte↔Switch créé.', 'succes');
            } else { afficherErreur(data.message || 'Erreur câblage.'); }

        } else if (type === 'interface-switch') {
            const idIface  = parseInt(document.getElementById('cable-interface').value);
            const idSwitch = parseInt(document.getElementById('cable-switch').value);
            const data = await appelAPI('cabler_interface_switch', { id_interface: idIface, id_switch: idSwitch });
            if (data.succes) {
                const iface   = (window.topologieCourante.interfaces || []).find(i => i.id_interface === idIface);
                if (iface) moteurVisuel?.ajouterLienInterfaceSwitch(idIface, idSwitch, iface.id_routeur, iface.nom, iface.adresse_ip, iface.masque);
                fermerModal('modal-cable');
                afficherNotification('Câblage Interface↔Switch créé.', 'succes');
            } else { afficherErreur(data.message || 'Erreur câblage.'); }

        } else if (type === 'interface-interface') {
            const idI1 = parseInt(document.getElementById('cable-i1').value);
            const idI2 = parseInt(document.getElementById('cable-i2').value);
            if (idI1 === idI2) { afficherErreur('Une interface ne peut pas se connecter à elle-même.'); return; }
            const data = await appelAPI('cabler_interface_interface', { id_interface: idI1, id_interface_1: idI2 });
            if (data.succes) {
                const i1 = (window.topologieCourante.interfaces || []).find(i => i.id_interface === idI1);
                const i2 = (window.topologieCourante.interfaces || []).find(i => i.id_interface === idI2);
                if (i1 && i2) moteurVisuel?.ajouterLienInterfaceInterface(idI1, idI2, i1.id_routeur, i2.id_routeur, i1.nom, i2.nom);
                fermerModal('modal-cable');
                afficherNotification('Câblage P2P créé.', 'succes');
            } else { afficherErreur(data.message || 'Erreur câblage.'); }
        }
    } catch (e) { afficherErreur(e.message); }
}

// =============================================================================
// MODULE : PANNEAU DE PROPRIÉTÉS
// =============================================================================

function afficherPanneauRouteur(idRouteur, nomRouteur) {
    const interfaces = (window.topologieCourante.interfaces || []).filter(i => i.id_routeur === idRouteur);
    const routes     = (window.topologieCourante.routes     || []).filter(r => r.id_routeur === idRouteur);

    let html = `
        <div class="prop-section">
            <div class="prop-section-titre">Informations</div>
            <table class="prop-tableau">
                <tr><td>Nom</td><td>${escHtml(nomRouteur)}</td></tr>
                <tr><td>Interfaces</td><td>${interfaces.length}</td></tr>
                <tr><td>Routes</td><td>${routes.length}</td></tr>
            </table>
        </div>
        <div class="prop-section">
            <div class="prop-section-titre">Interfaces</div>
            <ul class="liste-interfaces">`;

    if (!interfaces.length) {
        html += '<li class="panneau-vide" style="padding:0.5rem 0;font-style:italic">Aucune interface</li>';
    } else {
        interfaces.forEach(iface => {
            html += `
            <li class="interface-item">
                <div class="interface-item-nom">${escHtml(iface.nom)}</div>
                <div class="interface-item-ip">${iface.adresse_ip}/${iface.masque}</div>
                <div class="interface-actions">
                    <button class="bouton bouton-sm bouton-secondaire"
                        onclick="ouvrirModalInterface(${idRouteur}, ${iface.id_interface}, {nom:'${escHtml(iface.nom)}',adresse_ip:'${iface.adresse_ip}',masque:${iface.masque}})">Modifier</button>
                    <button class="bouton bouton-sm bouton-danger"
                        onclick="supprimerInterface(${iface.id_interface}, ${idRouteur})">×</button>
                </div>
            </li>`;
        });
    }

    html += `</ul>
            <button class="bouton bouton-secondaire bouton-sm bouton-plein" style="margin-top:0.25rem"
                onclick="ouvrirModalInterface(${idRouteur})">+ Interface</button>
        </div>
        <div class="prop-section">
            <div class="prop-section-titre">Routes statiques</div>
            <ul class="liste-routes">`;

    if (!routes.length) {
        html += '<li class="panneau-vide" style="padding:0.5rem 0;font-style:italic">Aucune route</li>';
    } else {
        routes.forEach(route => {
            html += `
            <li class="route-item">
                <div>
                    <div class="route-dest">${route.reseau_dest}/${route.masque_dest}</div>
                    <div class="route-hop">via ${route.next_hop}</div>
                </div>
                <button class="bouton bouton-sm bouton-danger"
                    onclick="supprimerRoute(${route.id_route}, ${idRouteur})">×</button>
            </li>`;
        });
    }

    html += `</ul>
            <button class="bouton bouton-secondaire bouton-sm bouton-plein" style="margin-top:0.25rem"
                onclick="ouvrirModalRoute(${idRouteur})">+ Route</button>
        </div>
        <div class="prop-section">
            <div class="prop-section-titre">Actions</div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <button class="bouton bouton-sm bouton-secondaire"
                    onclick="ouvrirModalRouteur(${idRouteur},'${escHtml(nomRouteur)}')">Renommer</button>
                <button class="bouton bouton-sm bouton-danger"
                    onclick="supprimerRouteur(${idRouteur})">Supprimer</button>
            </div>
        </div>`;

    _afficherPanneau(`🔲 ${nomRouteur}`, html);
}

function rafraichirPanneauRouteur(idRouteur) {
    const r = (window.topologieCourante.routeurs || []).find(r => r.id_routeur === idRouteur);
    if (r) afficherPanneauRouteur(idRouteur, r.nom);
}

function afficherPanneauSwitch(idSwitch, nomSwitch) {
    // Récupérer tous les câbles liés à ce switch depuis la topologie locale
    const cablesHS  = (window.topologieCourante.cables?.hote_switch || [])
        .filter(c => c.id_switch === idSwitch);
    const cablesIS  = (window.topologieCourante.cables?.interface_switch || [])
        .filter(c => c.id_switch === idSwitch);

    // --- Section câbles Hôte↔Switch ---
    let htmlCablesHS = '';
    cablesHS.forEach(c => {
        const hote = (window.topologieCourante.hotes || []).find(h => h.id_hote === c.id_hote);
        if (!hote) return;
        htmlCablesHS += `
        <li class="cable-item">
            <div class="cable-info">
                <span class="cable-icone">💻</span>
                <span class="cable-nom">${escHtml(hote.nom)}</span>
                <span class="cable-ip">${hote.adresse_ip || '?'}</span>
            </div>
            <button class="bouton bouton-sm bouton-danger"
                onclick="supprimerCableHoteSwitch(${c.id_hote}, ${idSwitch})"
                title="Supprimer ce câblage">×</button>
        </li>`;
    });

    // --- Section câbles Interface↔Switch ---
    let htmlCablesIS = '';
    cablesIS.forEach(c => {
        const iface   = (window.topologieCourante.interfaces || []).find(i => i.id_interface === c.id_interface);
        const routeur = iface ? (window.topologieCourante.routeurs || []).find(r => r.id_routeur === iface.id_routeur) : null;
        if (!iface) return;
        htmlCablesIS += `
        <li class="cable-item">
            <div class="cable-info">
                <span class="cable-icone">🔲</span>
                <span class="cable-nom">${escHtml(routeur?.nom || '?')} / ${escHtml(iface.nom)}</span>
                <span class="cable-ip">${iface.adresse_ip}/${iface.masque}</span>
            </div>
            <button class="bouton bouton-sm bouton-danger"
                onclick="supprimerCableInterfaceSwitch(${c.id_interface}, ${idSwitch})"
                title="Supprimer ce câblage">×</button>
        </li>`;
    });

    const totalCables = cablesHS.length + cablesIS.length;

    const html = `
        <div class="prop-section">
            <div class="prop-section-titre">Informations</div>
            <table class="prop-tableau">
                <tr><td>Nom</td><td>${escHtml(nomSwitch)}</td></tr>
                <tr><td>Type</td><td>Switch L2</td></tr>
                <tr><td>Câbles</td><td>${totalCables} connexion(s)</td></tr>
            </table>
        </div>
        <div class="prop-section">
            <div class="prop-section-titre">Câbles connectés (${totalCables})</div>
            ${totalCables === 0
                ? '<p class="panneau-vide" style="padding:0.5rem 0;font-style:italic">Aucun câble</p>'
                : `<ul class="liste-cables">
                    ${htmlCablesHS}
                    ${htmlCablesIS}
                   </ul>`
            }
        </div>
        <div class="prop-section">
            <div class="prop-section-titre">Actions</div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <button class="bouton bouton-sm bouton-secondaire"
                    onclick="document.getElementById('switch-id').value='${idSwitch}';
                             document.getElementById('switch-nom').value='${escHtml(nomSwitch)}';
                             document.getElementById('modal-switch-titre').textContent='Modifier le switch';
                             ouvrirModal('modal-switch')">
                    Renommer</button>
                <button class="bouton bouton-sm bouton-danger"
                    onclick="supprimerSwitch(${idSwitch})">Supprimer</button>
            </div>
        </div>`;
    _afficherPanneau(`◆ ${nomSwitch}`, html);
}

/**
 * Supprime un câble Hôte↔Switch.
 */
async function supprimerCableHoteSwitch(idHote, idSwitch) {
    if (!confirm('Supprimer ce câblage ?')) return;
    try {
        const data = await appelAPI('decabler_hote_switch', { id_hote: idHote, id_switch: idSwitch });
        if (data.succes) {
            // Mise à jour topologie locale
            const cables = window.topologieCourante.cables;
            if (cables?.hote_switch) {
                cables.hote_switch = cables.hote_switch.filter(c =>
                    !(c.id_hote === idHote && c.id_switch === idSwitch)
                );
            }
            // Suppression du lien dans vis.js
            moteurVisuel?.supprimerLien(`CHS_${idSwitch}_${idHote}`);
            afficherNotification('Câble supprimé.', 'succes');
            // Rafraîchir le panneau
            const sw = (window.topologieCourante.switchs || []).find(s => s.id_switch === idSwitch);
            if (sw) afficherPanneauSwitch(idSwitch, sw.nom);
        }
    } catch (e) { afficherErreur(e.message); }
}

/**
 * Supprime un câble Interface↔Switch.
 */
async function supprimerCableInterfaceSwitch(idInterface, idSwitch) {
    if (!confirm('Supprimer ce câblage ?')) return;
    try {
        const data = await appelAPI('decabler_interface_switch', { id_interface: idInterface, id_switch: idSwitch });
        if (data.succes) {
            const cables = window.topologieCourante.cables;
            if (cables?.interface_switch) {
                cables.interface_switch = cables.interface_switch.filter(c =>
                    !(c.id_interface === idInterface && c.id_switch === idSwitch)
                );
            }
            moteurVisuel?.supprimerLien(`CIS_${idInterface}_${idSwitch}`);
            afficherNotification('Câble supprimé.', 'succes');
            const sw = (window.topologieCourante.switchs || []).find(s => s.id_switch === idSwitch);
            if (sw) afficherPanneauSwitch(idSwitch, sw.nom);
        }
    } catch (e) { afficherErreur(e.message); }
}

function afficherPanneauHote(idHote, nomHote) {
    const h      = (window.topologieCourante.hotes   || []).find(h => h.id_hote === idHote);
    const reseau = (window.topologieCourante.reseaux || []).find(r => r.id_reseau == h?.id_reseau);
    const actif  = h?.id_reseau != null;

    const html = `
        <div class="prop-section">
            <div class="prop-section-titre">Informations</div>
            <table class="prop-tableau">
                <tr><td>Nom</td><td>${escHtml(h?.nom || nomHote)}</td></tr>
                <tr><td>IP</td><td>${h?.adresse_ip || '—'}</td></tr>
                <tr><td>Passerelle</td><td>${h?.passerelle_ip || '—'}</td></tr>
                <tr><td>Réseau</td><td>${reseau ? `${reseau.label} (${reseau.adresse_reseau}/${reseau.masque})` : 'Aucun'}</td></tr>
                <tr><td>Statut</td><td class="${actif ? 'badge-actif' : 'badge-desactive'}">${actif ? '✓ Actif' : '✗ Désactivé'}</td></tr>
            </table>
        </div>
        <div class="prop-section">
            <div class="prop-section-titre">Actions</div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <button class="bouton bouton-sm bouton-secondaire"
                    onclick="ouvrirModalHoteModif(${idHote})">
                    Modifier</button>
                <button class="bouton bouton-sm bouton-danger"
                    onclick="supprimerHote(${idHote})">Supprimer</button>
            </div>
        </div>`;
    _afficherPanneau(`💻 ${nomHote}`, html);
}

function afficherPanneauReseau(idReseau) {
    const r = (window.topologieCourante.reseaux || []).find(r => r.id_reseau === idReseau);
    if (!r) return;
    const hotesRattaches = (window.topologieCourante.hotes || []).filter(h => h.id_reseau == idReseau);
    const html = `
        <div class="prop-section">
            <div class="prop-section-titre">Informations</div>
            <table class="prop-tableau">
                <tr><td>Label</td><td>${escHtml(r.label)}</td></tr>
                <tr><td>Adresse</td><td>${r.adresse_reseau}/${r.masque}</td></tr>
                <tr><td>Hôtes</td><td>${hotesRattaches.length}</td></tr>
            </table>
        </div>
        <div class="prop-section">
            <div class="prop-section-titre">Actions</div>
            <button class="bouton bouton-sm bouton-danger bouton-plein"
                onclick="supprimerReseau(${idReseau})">Supprimer le réseau</button>
        </div>`;
    _afficherPanneau(`🌐 ${r.label}`, html);
}

function _afficherPanneau(titre, html) {
    document.getElementById('panneau-titre').textContent = titre;
    document.getElementById('panneau-corps').innerHTML   = html;
    document.getElementById('panneau-proprietes').classList.add('ouvert');
}

function fermerPanneau() {
    document.getElementById('panneau-proprietes')?.classList.remove('ouvert');
}

// =============================================================================
// MODULE : SIMULATION
// =============================================================================

async function lancerSimulation() {
    const ipSource = document.getElementById('sim-ip-source').value.trim();
    const ipDest   = document.getElementById('sim-ip-dest').value.trim();
    if (!ipSource || !ipDest) {
        afficherErreur('Renseignez les adresses IP source et destination.');
        return;
    }

    const btn      = document.getElementById('btn-simuler');
    btn.disabled   = true;
    btn.textContent = '⏳ En cours...';

    try {
        const data = await appelAPI('simuler', {
            id_scenario: SCENARIO_ID, ip_source: ipSource, ip_destination: ipDest
        });
        afficherResultatsSimulation(data);
        if (data.sauts?.length) moteurVisuel?.animer(data.sauts);
    } catch (e) {
        afficherErreur('Erreur simulation : ' + e.message);
    } finally {
        btn.disabled    = false;
        btn.textContent = '▶ Lancer la simulation';
        document.getElementById('btn-reinit-sim').style.display = '';
    }
}

function afficherResultatsSimulation(data) {
    const panneau = document.getElementById('panneau-simulation');
    const corps   = document.getElementById('simulation-corps');
    panneau.style.display = 'flex';

    let html = '';
    (data.sauts || []).forEach((saut, idx) => {
        const cls       = saut.statut === 'erreur_ttl' ? 'saut-erreur' : saut.type === 'routeur' ? 'saut-routeur' : 'saut-hote';
        const ttlChange = (saut.type === 'routeur' || saut.statut === 'erreur_ttl');
        html += `
        <div class="saut-carte ${cls}" style="animation-delay:${idx * 0.07}s">
            <div class="saut-type">${saut.type.toUpperCase()} #${idx + 1}</div>
            <div class="saut-nom">${escHtml(saut.nom)}</div>
            <div class="saut-ip">${saut.ip}</div>
            <div class="saut-entete-ip">
                <div class="ip-champ"><span class="ip-champ-nom">Ver/IHL</span><span class="ip-champ-val">${saut.paquet.version}/${saut.paquet.ihl}</span></div>
                <div class="ip-champ"><span class="ip-champ-nom">ID</span><span class="ip-champ-val">${saut.paquet.identification}</span></div>
                <div class="ip-champ"><span class="ip-champ-nom">Flags</span><span class="ip-champ-val">DF=${saut.paquet.flags_df?'1':'0'}</span></div>
                <div class="ip-champ ${ttlChange?'ip-champ-modifie':''}"><span class="ip-champ-nom">TTL</span><span class="ip-champ-val">${saut.paquet.ttl}</span></div>
                <div class="ip-champ ${ttlChange?'ip-champ-modifie':''}"><span class="ip-champ-nom">Checksum</span><span class="ip-champ-val">${saut.paquet.checksum}</span></div>
                <div class="ip-champ"><span class="ip-champ-nom">Src</span><span class="ip-champ-val">${saut.paquet.source}</span></div>
                <div class="ip-champ"><span class="ip-champ-nom">Dst</span><span class="ip-champ-val">${saut.paquet.destination}</span></div>
            </div>
            <div class="saut-message">${escHtml(saut.message)}</div>
        </div>`;
    });

    html += `<div class="simulation-resultat ${data.succes?'succes':'echec'}">${data.succes?'✓':'✗'} ${escHtml(data.message)}</div>`;
    corps.innerHTML = html;
}

function reinitialiserSimulation() {
    document.getElementById('panneau-simulation').style.display = 'none';
    document.getElementById('simulation-corps').innerHTML       = '';
    document.getElementById('btn-reinit-sim').style.display     = 'none';
    moteurVisuel?.reinitialiserAnimation();
}

// =============================================================================
// MODULE : SÉLECTEURS HÔTES POUR LA SIMULATION
// =============================================================================

/**
 * Synchronise le champ texte IP avec la valeur du select hôte.
 * Si l'utilisateur choisit un hôte, le champ texte se met à jour.
 * Si l'utilisateur saisit manuellement, le select repasse à "vide".
 *
 * @param {'source'|'dest'} sens  Source ou destination
 * @param {string}          ip    IP sélectionnée depuis le select
 */
function syncSimIP(sens, ip) {
    const champTexte = document.getElementById(sens === 'source' ? 'sim-ip-source' : 'sim-ip-dest');
    if (champTexte) champTexte.value = ip || '';
}

/**
 * Peuple les deux selects hôtes de la section simulation.
 * Appelé à l'init et après chaque ajout/suppression d'hôte.
 */
function peuplerSelectsSimulation() {
    const hotes = (window.topologieCourante?.hotes || []).filter(h => h.adresse_ip);

    ['sim-select-source', 'sim-select-dest'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;

        /* Sauvegarder la valeur courante */
        const valActuelle = sel.value;
        sel.innerHTML = '<option value="">-- Choisir un hôte --</option>';

        hotes.forEach(h => {
            const opt = document.createElement('option');
            opt.value       = h.adresse_ip;
            opt.textContent = `${h.nom}  ${h.adresse_ip}`;
            if (h.adresse_ip === valActuelle) opt.selected = true;
            sel.appendChild(opt);
        });
    });
}

/**
 * Met à jour le panneau latéral gauche "Réseaux".
 * Affiche chaque réseau avec son adresse, masque et label.
 * Appelé à l'init et après chaque ajout/suppression de réseau.
 */
function rafraichirPanneauReseaux() {
    const conteneur = document.getElementById('liste-reseaux-barre');
    if (!conteneur) return;

    const reseaux = window.topologieCourante?.reseaux || [];

    if (!reseaux.length) {
        conteneur.innerHTML = '<p class="barre-reseaux-vide">Aucun réseau</p>';
        return;
    }

    conteneur.innerHTML = reseaux.map(r => `
        <div class="reseau-badge" title="${r.adresse_reseau}/${r.masque}">
            <div class="reseau-badge-header">
                <span class="reseau-badge-label">${escHtml(r.label || 'Sans nom')}</span>
                <button class="reseau-badge-suppr bouton bouton-sm bouton-danger"
                        onclick="supprimerReseau(${r.id_reseau})"
                        title="Supprimer ce réseau">×</button>
            </div>
            <span class="reseau-badge-cidr">${r.adresse_reseau}/${r.masque}</span>
        </div>
    `).join('');
}

// =============================================================================
// MODULE : SÉLECTEURS HÔTES ET PANNEAU RÉSEAUX
// =============================================================================

/**
 * Synchronise le champ texte IP avec la valeur du select hôte.
 * @param {'source'|'dest'} sens
 * @param {string} ip
 */
function syncSimIP(sens, ip) {
    const id = sens === 'source' ? 'sim-ip-source' : 'sim-ip-dest';
    const el = document.getElementById(id);
    if (el) el.value = ip || '';
}

/**
 * Peuple les selects hôtes source/destination dans la section simulation.
 */
function peuplerSelectsSimulation() {
    const hotes = (window.topologieCourante?.hotes || []).filter(h => h.adresse_ip);
    ['sim-select-source', 'sim-select-dest'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const valActuelle = sel.value;
        sel.innerHTML = '<option value="">-- Choisir un hôte --</option>';
        hotes.forEach(h => {
            const opt = document.createElement('option');
            opt.value       = h.adresse_ip;
            opt.textContent = `${h.nom}  ${h.adresse_ip}`;
            if (h.adresse_ip === valActuelle) opt.selected = true;
            sel.appendChild(opt);
        });
    });
}

/**
 * Rafraîchit le panneau réseaux dans la barre gauche.
 */
function rafraichirPanneauReseaux() {
    const conteneur = document.getElementById('liste-reseaux-barre');
    if (!conteneur) return;
    const reseaux = window.topologieCourante?.reseaux || [];
    if (!reseaux.length) {
        conteneur.innerHTML = '<p class="barre-reseaux-vide">Aucun réseau</p>';
        return;
    }
    conteneur.innerHTML = reseaux.map(r => `
        <div class="reseau-badge">
            <div class="reseau-badge-header">
                <span class="reseau-badge-label">${escHtml(r.label || 'Sans nom')}</span>
                <button class="reseau-badge-suppr" onclick="supprimerReseau(${r.id_reseau})"
                        title="Supprimer">×</button>
            </div>
            <span class="reseau-badge-cidr">${r.adresse_reseau}/${r.masque}</span>
        </div>
    `).join('');
}

// =============================================================================
// INITIALISATION — Point d'entrée unique de toute l'application éditeur.
// Appelé au window.load APRÈS le chargement de vis.js et moteur-visuel.js.
// =============================================================================

window.addEventListener('load', function () {
    /* 1. Initialiser le moteur visuel EN PREMIER */
    if (typeof _initMoteurVisuel === 'function') {
        _initMoteurVisuel();
    }

    /* 2. Synchroniser la topologie en mémoire depuis les données PHP */
    if (typeof TOPOLOGIE !== 'undefined') {
        window.topologieCourante = JSON.parse(JSON.stringify(TOPOLOGIE)); // copie profonde
    }

    /* 3. Peupler les selects de simulation et le panneau réseaux */
    peuplerSelectsSimulation();
    rafraichirPanneauReseaux();

    /* --- Suite : liaison des événements UI --- */

    /* ---- Boutons d'outils barre gauche ---- */
    document.getElementById('outil-routeur')?.addEventListener('click', () => ouvrirModalRouteur());
    document.getElementById('outil-switch')?.addEventListener('click',  () => {
        document.getElementById('switch-id').value = '';
        document.getElementById('modal-switch-titre').textContent = 'Ajouter un switch';
        ouvrirModal('modal-switch');
    });
    document.getElementById('outil-hote')?.addEventListener('click', () => {
        peuplerSelectReseaux();
        ouvrirModalHote();
    });
    document.getElementById('outil-reseau')?.addEventListener('click', () => ouvrirModal('modal-reseau'));
    document.getElementById('outil-cable')?.addEventListener('click',  () => {
        peuplerCableChamps();
        ouvrirModal('modal-cable');
    });

    /* ---- Boutons valider des modals ---- */
    document.getElementById('btn-valider-routeur')?.addEventListener('click',   validerRouteur);
    document.getElementById('btn-valider-switch')?.addEventListener('click',    validerSwitch);
    document.getElementById('btn-valider-hote')?.addEventListener('click',      validerHote);
    document.getElementById('btn-valider-reseau')?.addEventListener('click',    validerReseau);
    document.getElementById('btn-valider-interface')?.addEventListener('click', validerInterface);
    document.getElementById('btn-valider-route')?.addEventListener('click',     validerRoute);
    document.getElementById('btn-valider-cable')?.addEventListener('click',     validerCable);

    /* ---- Mise à jour des champs câble selon le type ---- */
    document.getElementById('cable-type')?.addEventListener('change', peuplerCableChamps);

    /* ---- Fermeture panneau propriétés ---- */
    document.getElementById('btn-fermer-panneau')?.addEventListener('click', fermerPanneau);

    /* ---- Simulation ---- */
    document.getElementById('btn-simuler')?.addEventListener('click',    lancerSimulation);
    document.getElementById('btn-reinit-sim')?.addEventListener('click', reinitialiserSimulation);
    document.getElementById('btn-fermer-simulation')?.addEventListener('click', () => {
        document.getElementById('panneau-simulation').style.display = 'none';
        moteurVisuel?.reinitialiserAnimation();
    });

    /* ---- Boutons fermer/annuler génériques des modals ---- */
    document.querySelectorAll('[data-modal],[data-fermer]').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.modal || this.dataset.fermer;
            fermerModal(id);
        });
    });

    /* ---- Touche Entrée dans les champs de modal ---- */
    document.querySelectorAll('.modal .champ-input').forEach(champ => {
        champ.addEventListener('keydown', e => {
            champ.classList.remove('champ-erreur');
            if (e.key === 'Enter') {
                e.preventDefault();
                champ.closest('.modal')?.querySelector('.bouton-principal')?.click();
            }
        });
    });
});
