/**
 * =============================================================================
 * MOTEUR-VISUEL.JS
 * Auteur : Étudiant
 * Description : Moteur de rendu graphique de la topologie réseau via vis.js.
 *               Gère les nœuds, les liens, l'interactivité (clic, déplacement)
 *               et l'animation des paquets lors de la simulation.
 *
 * IMPORTANT : Ce fichier doit être chargé AVANT application-client.js.
 *             Il expose la variable globale `moteurVisuel` utilisée par l'autre JS.
 * =============================================================================
 */

'use strict';

// =============================================================================
// CONFIGURATION VISUELLE DES NŒUDS
// =============================================================================

const STYLE_NOEUD = {
    // Routeur : bleu clair professionnel, lisible sur fond blanc
    ROUTEUR: {
        shape: 'box',
        color: {
            background: '#1E40AF',
            border:     '#2563EB',
            highlight:  { background: '#1D4ED8', border: '#1E40AF' },
            hover:      { background: '#2563EB', border: '#1D4ED8' },
        },
        font:        { color: '#FFFFFF', face: 'Arial', size: 13, bold: true },
        borderWidth: 2,
        borderWidthSelected: 3,
        shadow:      { enabled: true, color: 'rgba(37,99,235,0.25)', size: 8 },
        widthConstraint: { minimum: 80 },
    },

    // Switch : carré à bords arrondis pour mieux afficher le label
    SWITCH: {
        shape: 'box',
        color: {
            background: '#5B21B6',
            border:     '#7C3AED',
            highlight:  { background: '#6D28D9', border: '#5B21B6' },
            hover:      { background: '#6D28D9', border: '#7C3AED' },
        },
        font:        { color: '#FFFFFF', face: 'Arial', size: 12, bold: true },
        borderWidth: 2,
        borderWidthSelected: 3,
        shadow:      { enabled: true, color: 'rgba(109,40,217,0.3)', size: 8 },
        shapeProperties: { borderRadius: 4 },
        /* Icône ◆ devant le nom pour distinguer visuellement du routeur */
        icon: undefined,
    },

    // Hôte actif : vert foncé professionnel
    HOTE_ACTIF: {
        shape: 'ellipse',
        color: {
            background: '#14532D',
            border:     '#16A34A',
            highlight:  { background: '#166534', border: '#15803D' },
            hover:      { background: '#166534', border: '#16A34A' },
        },
        font:        { color: '#FFFFFF', face: 'Arial', size: 12 },
        borderWidth: 2,
        borderWidthSelected: 3,
        widthConstraint: { minimum: 100 },
    },

    // Hôte désactivé : gris neutre
    HOTE_DESACTIVE: {
        shape: 'ellipse',
        color: {
            background: '#9CA3AF',
            border:     '#6B7280',
            highlight:  { background: '#6B7280', border: '#4B5563' },
            hover:      { background: '#6B7280', border: '#9CA3AF' },
        },
        font:        { color: '#FFFFFF', face: 'Arial', size: 12 },
        borderWidth: 1,
        borderWidthSelected: 2,
        widthConstraint: { minimum: 100 },
    },
};

const STYLE_LIEN = {
    FILAIRE: {
        color: { color: '#94A3B8', highlight: '#2563EB', hover: '#3B82F6' },
        width: 2,
        smooth: { type: 'continuous' },
        arrows: {},
        font: { color: '#64748B', size: 10, face: 'Arial', align: 'middle' },
    },
    P2P: {
        color: { color: '#CBD5E1', highlight: '#2563EB', hover: '#3B82F6' },
        width: 2,
        dashes: [6, 3],
        smooth: { type: 'continuous' },
        arrows: {},
        font: { color: '#64748B', size: 10, face: 'Arial', align: 'middle' },
    },
};

// =============================================================================
// CLASSE : MoteurVisuel
// =============================================================================

class MoteurVisuel {

    constructor(idConteneur, topologie) {
        this._noeuds          = new vis.DataSet();
        this._liens           = new vis.DataSet();
        this._reseau          = null;
        this._timersAnimation = [];

        this._initialiserDepuisTopologie(topologie);
        this._creerReseau(idConteneur);
    }

    // =========================================================================
    // INITIALISATION
    // =========================================================================

    _initialiserDepuisTopologie(topologie) {
        /* --- Routeurs --- */
        (topologie.routeurs || []).forEach(r => {
            this._noeuds.add({
                id:    `R${r.id_routeur}`,
                id_db: r.id_routeur,
                type:  'routeur',
                label: r.nom,
                x:     parseFloat(r.pos_x) || 0,
                y:     parseFloat(r.pos_y) || 0,
                ...STYLE_NOEUD.ROUTEUR,
            });
        });

        /* --- Switchs --- */
        (topologie.switchs || []).forEach(s => {
            this._noeuds.add({
                id:    `SW${s.id_switch}`,
                id_db: s.id_switch,
                type:  'switch',
                label: `◆ ${s.nom}`,
                x:     parseFloat(s.pos_x) || 0,
                y:     parseFloat(s.pos_y) || 0,
                ...STYLE_NOEUD.SWITCH,
            });
        });

        /* --- Hôtes (avec CIDR du réseau dans le label) --- */
        (topologie.hotes || []).forEach(h => {
            const actif  = h.id_reseau !== null && h.id_reseau !== undefined;
            /* Recherche du réseau pour afficher IP/masque */
            const reseau = (topologie.reseaux || []).find(r => r.id_reseau == h.id_reseau);
            const ipLabel = (actif && reseau)
                ? `${h.adresse_ip || '?'}/${reseau.masque}`
                : (h.adresse_ip || '?');
            this._noeuds.add({
                id:    `H${h.id_hote}`,
                id_db: h.id_hote,
                type:  'hote',
                label: `${h.nom}\n${ipLabel}`,
                x:     parseFloat(h.pos_x) || 0,
                y:     parseFloat(h.pos_y) || 0,
                ...(actif ? STYLE_NOEUD.HOTE_ACTIF : STYLE_NOEUD.HOTE_DESACTIVE),
            });
        });

        /* --- Câbles Hôte ↔ Switch --- */
        (topologie.cables?.hote_switch || []).forEach(c => {
            this._liens.add({
                id:   `CHS_${c.id_switch}_${c.id_hote}`,
                from: `H${c.id_hote}`,
                to:   `SW${c.id_switch}`,
                ...STYLE_LIEN.FILAIRE,
            });
        });

        /* --- Câbles Interface ↔ Switch --- */
        (topologie.cables?.interface_switch || []).forEach(c => {
            const iface = (topologie.interfaces || []).find(i => i.id_interface === c.id_interface);
            if (iface) {
                this._liens.add({
                    id:    `CIS_${c.id_interface}_${c.id_switch}`,
                    from:  `R${iface.id_routeur}`,
                    to:    `SW${c.id_switch}`,
                    title: `${iface.nom} (${iface.adresse_ip}/${iface.masque})`,
                    ...STYLE_LIEN.FILAIRE,
                });
            }
        });

        /* --- Câbles Interface ↔ Interface (P2P) --- */
        (topologie.cables?.interface_interface || []).forEach(c => {
            const i1 = (topologie.interfaces || []).find(i => i.id_interface === c.id_interface);
            const i2 = (topologie.interfaces || []).find(i => i.id_interface === c.id_interface_1);
            if (i1 && i2) {
                this._liens.add({
                    id:    `CII_${c.id_interface}_${c.id_interface_1}`,
                    from:  `R${i1.id_routeur}`,
                    to:    `R${i2.id_routeur}`,
                    title: `${i1.nom} ↔ ${i2.nom}`,
                    ...STYLE_LIEN.P2P,
                });
            }
        });
    }

    _creerReseau(idConteneur) {
        const conteneur = document.getElementById(idConteneur);
        if (!conteneur) {
            console.error('[MoteurVisuel] Conteneur DOM introuvable :', idConteneur);
            return;
        }

        /* S'il n'y a aucun nœud, on centre quand même le canvas */
        const options = {
            physics: { enabled: false },
            interaction: {
                hover:             true,
                tooltipDelay:      150,
                multiselect:       false,
                dragView:          true,
                zoomView:          true,
                navigationButtons: false,
                keyboard:          false,
            },
            manipulation: { enabled: false },
            layout:       { improvedLayout: false },
            /* Fond blanc pour le canvas vis.js */
            nodes: {
                shapeProperties: { interpolation: false },
            },
        };

        this._reseau = new vis.Network(
            conteneur,
            { nodes: this._noeuds, edges: this._liens },
            options
        );

        this._enregistrerEvenements();
    }

    // =========================================================================
    // ÉVÉNEMENTS
    // =========================================================================

    _enregistrerEvenements() {

        /* Clic sur un nœud → panneau de propriétés */
        this._reseau.on('click', (params) => {
            if (params.nodes.length > 0) {
                const idNoeud = params.nodes[0];
                const noeud   = this._noeuds.get(idNoeud);
                if (!noeud) return;

                switch (noeud.type) {
                    case 'routeur': afficherPanneauRouteur(noeud.id_db, noeud.label); break;
                    case 'switch':  afficherPanneauSwitch(noeud.id_db, noeud.label); break;
                    case 'hote': {
                        const nom = noeud.label.split('\n')[0];
                        afficherPanneauHote(noeud.id_db, nom);
                        break;
                    }
                }
            } else if (params.nodes.length === 0 && params.edges.length === 0) {
                fermerPanneau();
            }
        });

        /* Survol → tooltip enrichi */
        this._reseau.on('hoverNode', (params) => {
            const noeud = this._noeuds.get(params.node);
            if (!noeud) return;
            let titre = '';
            if (noeud.type === 'routeur') {
                const ifaces = (window.topologieCourante?.interfaces || [])
                    .filter(i => i.id_routeur === noeud.id_db)
                    .map(i => `  ${i.nom}: ${i.adresse_ip}/${i.masque}`)
                    .join('\n');
                titre = `🔲 Routeur: ${noeud.label}\n${ifaces || '  (aucune interface)'}`;
            } else if (noeud.type === 'switch') {
                titre = `⬛ Switch: ${noeud.label}`;
            } else if (noeud.type === 'hote') {
                const h = (window.topologieCourante?.hotes || []).find(h => h.id_hote === noeud.id_db);
                titre = `💻 ${h?.nom || noeud.label}\nIP: ${h?.adresse_ip || '?'}\nGW: ${h?.passerelle_ip || '?'}\nStatut: ${h?.id_reseau ? 'Actif' : 'Désactivé'}`;
            }
            if (titre) this._noeuds.update({ id: noeud.id, title: titre });
        });

        /* Fin de drag → sauvegarde position en BDD */
        this._reseau.on('dragEnd', async (params) => {
            if (!params.nodes?.length) return;
            for (const idNoeud of params.nodes) {
                const pos   = this._reseau.getPosition(idNoeud);
                const noeud = this._noeuds.get(idNoeud);
                if (!noeud || noeud.type === 'paquet') continue;

                try {
                    let action = '', corps = { x: pos.x, y: pos.y };
                    if      (noeud.type === 'routeur') { action = 'maj_position_routeur'; corps.id_routeur = noeud.id_db; }
                    else if (noeud.type === 'switch')  { action = 'maj_position_switch';  corps.id_switch  = noeud.id_db; }
                    else if (noeud.type === 'hote')    { action = 'maj_position_hote';    corps.id_hote    = noeud.id_db; }
                    if (action) await appelAPI(action, corps);
                } catch (e) {
                    console.warn('[MoteurVisuel] Échec sauvegarde position :', e);
                }
            }
        });
    }

    // =========================================================================
    // OPÉRATIONS SUR LES NŒUDS
    // =========================================================================

    ajouterNoeudRouteur(config) {
        this._noeuds.add({
            id:    config.id,
            id_db: config.id_db,
            type:  'routeur',
            label: config.label,
            x:     config.x,
            y:     config.y,
            ...STYLE_NOEUD.ROUTEUR,
        });
    }

    ajouterNoeudSwitch(config) {
        this._noeuds.add({
            id:    config.id,
            id_db: config.id_db,
            type:  'switch',
            label: `◆ ${config.label}`,
            x:     config.x,
            y:     config.y,
            ...STYLE_NOEUD.SWITCH,
        });
    }

    ajouterNoeudHote(config) {
        const style = config.actif ? STYLE_NOEUD.HOTE_ACTIF : STYLE_NOEUD.HOTE_DESACTIVE;
        this._noeuds.add({
            id:    config.id,
            id_db: config.id_db,
            type:  'hote',
            label: config.label,
            x:     config.x,
            y:     config.y,
            ...style,
        });
    }

    renommerNoeud(idNoeud, nouveauNom) {
        const noeud = this._noeuds.get(idNoeud);
        if (!noeud) return;
        if (noeud.type === 'hote') {
            const parties = noeud.label.split('\n');
            parties[0] = nouveauNom;
            this._noeuds.update({ id: idNoeud, label: parties.join('\n') });
        } else {
            this._noeuds.update({ id: idNoeud, label: nouveauNom });
        }
    }

    mettreAJourHote(idHote, nom, ip, actif) {
        const style = actif ? STYLE_NOEUD.HOTE_ACTIF : STYLE_NOEUD.HOTE_DESACTIVE;
        this._noeuds.update({ id: `H${idHote}`, label: `${nom}\n${ip}`, ...style });
    }

    supprimerNoeud(idNoeud) {
        const liens = this._reseau.getConnectedEdges(idNoeud);
        this._liens.remove(liens);
        this._noeuds.remove(idNoeud);
    }

    supprimerLiensInterface(idInterface) {
        const aSupprimer = this._liens.getIds().filter(id =>
            id.includes(`_${idInterface}_`) || id.endsWith(`_${idInterface}`)
        );
        this._liens.remove(aSupprimer);
    }

    /**
     * Supprime un lien spécifique par son ID vis.js.
     * @param {string} idLien Identifiant du lien à supprimer
     */
    supprimerLien(idLien) {
        if (this._liens.get(idLien)) {
            this._liens.remove(idLien);
            return true;
        }
        return false;
    }

    /**
     * Retourne tous les liens connectés à un nœud donné avec leurs métadonnées.
     * @param {string} idNoeud Identifiant vis.js du nœud
     * @returns {Array} Liste des liens avec id, from, to, title
     */
    getLiensNoeud(idNoeud) {
        const idLiens = this._reseau.getConnectedEdges(idNoeud);
        return idLiens.map(idLien => this._liens.get(idLien)).filter(Boolean);
    }

    ajouterLienHoteSwitch(idHote, idSwitch) {
        const idLien = `CHS_${idSwitch}_${idHote}`;
        if (!this._liens.get(idLien)) {
            this._liens.add({ id: idLien, from: `H${idHote}`, to: `SW${idSwitch}`, ...STYLE_LIEN.FILAIRE });
        }
    }

    ajouterLienInterfaceSwitch(idInterface, idSwitch, idRouteur, nomInterface, ip, masque) {
        const idLien = `CIS_${idInterface}_${idSwitch}`;
        if (!this._liens.get(idLien)) {
            this._liens.add({
                id: idLien, from: `R${idRouteur}`, to: `SW${idSwitch}`,
                title: `${nomInterface} (${ip}/${masque})`,
                ...STYLE_LIEN.FILAIRE,
            });
        }
    }

    ajouterLienInterfaceInterface(idI1, idI2, idR1, idR2, nomI1, nomI2) {
        const idLien = `CII_${idI1}_${idI2}`;
        if (!this._liens.get(idLien)) {
            this._liens.add({
                id: idLien, from: `R${idR1}`, to: `R${idR2}`,
                title: `${nomI1} ↔ ${nomI2}`,
                ...STYLE_LIEN.P2P,
            });
        }
    }

    // =========================================================================
    // ANIMATION DE SIMULATION
    // =========================================================================

    animer(sauts) {
        this.reinitialiserAnimation();
        const carteIP       = this._construireCartographieIP();
        const DELAI_PAR_SAUT = 900;

        sauts.forEach((saut, idx) => {
            const t = setTimeout(() => {
                const idNoeud = carteIP[saut.ip];
                if (!idNoeud) return;

                const estErreur = saut.statut === 'erreur_ttl';
                this._mettreEnSurbrillance(idNoeud, estErreur);

                if (idx < sauts.length - 1) {
                    const suivant = carteIP[sauts[idx + 1].ip];
                    if (suivant) this._animerLien(idNoeud, suivant, estErreur);
                }

                this._reseau.focus(idNoeud, {
                    scale:     1.2,
                    animation: { duration: 400, easingFunction: 'easeInOutQuad' },
                });
            }, idx * DELAI_PAR_SAUT);
            this._timersAnimation.push(t);
        });

        const tFinal = setTimeout(
            () => this._reinitialiserSurbrillances(),
            sauts.length * DELAI_PAR_SAUT + 500
        );
        this._timersAnimation.push(tFinal);
    }

    _construireCartographieIP() {
        const carte = {};
        const topo  = window.topologieCourante;
        (topo.hotes      || []).forEach(h => { if (h.adresse_ip)  carte[h.adresse_ip]  = `H${h.id_hote}`; });
        (topo.interfaces || []).forEach(i => { if (i.adresse_ip)  carte[i.adresse_ip]  = `R${i.id_routeur}`; });
        return carte;
    }

    _mettreEnSurbrillance(idNoeud, erreur = false) {
        const couleur = erreur
            ? { background: '#FEE2E2', border: '#DC2626' }  /* rouge clair sur fond blanc */
            : { background: '#FEF3C7', border: '#D97706' }; /* jaune/orange pour le paquet */
        this._noeuds.update({ id: idNoeud, color: couleur });
    }

    _animerLien(idDepart, idArrivee, erreur = false) {
        const lien = this._trouverLienEntre(idDepart, idArrivee);
        if (!lien) return;
        const couleur = erreur ? '#DC2626' : '#D97706';
        this._liens.update({ id: lien.id, color: { color: couleur }, width: 4 });
        setTimeout(() => {
            const original = lien.dashes ? STYLE_LIEN.P2P : STYLE_LIEN.FILAIRE;
            this._liens.update({ id: lien.id, ...original });
        }, 750);
    }

    _trouverLienEntre(id1, id2) {
        return this._liens.get().find(l =>
            (l.from === id1 && l.to === id2) || (l.from === id2 && l.to === id1)
        ) || null;
    }

    _reinitialiserSurbrillances() {
        this._noeuds.get().forEach(n => {
            let s;
            if      (n.type === 'routeur') s = STYLE_NOEUD.ROUTEUR;
            else if (n.type === 'switch')  s = STYLE_NOEUD.SWITCH;
            else if (n.type === 'hote') {
                const h = (window.topologieCourante?.hotes || []).find(h => h.id_hote === n.id_db);
                s = (h?.id_reseau != null && h?.id_reseau !== undefined)
                    ? STYLE_NOEUD.HOTE_ACTIF
                    : STYLE_NOEUD.HOTE_DESACTIVE;
            } else return;
            this._noeuds.update({ id: n.id, color: s.color, font: s.font });
        });
    }

    reinitialiserAnimation() {
        this._timersAnimation.forEach(t => clearTimeout(t));
        this._timersAnimation = [];
        this._reinitialiserSurbrillances();
        this._liens.get().forEach(l => {
            const original = l.dashes ? STYLE_LIEN.P2P : STYLE_LIEN.FILAIRE;
            this._liens.update({ id: l.id, ...original, width: 2 });
        });
    }
}

// =============================================================================
// POINT D'ENTRÉE UNIQUE
// moteur-visuel.js s'initialise ici et expose `moteurVisuel` globalement.
// application-client.js sera exécuté APRÈS, il pourra donc l'utiliser.
// =============================================================================

/** @type {MoteurVisuel} Instance globale du moteur visuel */
var moteurVisuel = null;

/**
 * Initialise le moteur visuel.
 * Appelé par initEditeur() depuis application-client.js,
 * qui coordonne l'ordre d'init des deux modules.
 */
function _initMoteurVisuel() {
    if (typeof vis === 'undefined') {
        console.error('[MoteurVisuel] vis.js introuvable — vérifiez le CDN.');
        return false;
    }
    if (typeof TOPOLOGIE === 'undefined' || typeof SCENARIO_ID === 'undefined') {
        console.error('[MoteurVisuel] Variables PHP TOPOLOGIE/SCENARIO_ID non disponibles.');
        return false;
    }
    moteurVisuel = new MoteurVisuel('canevas-topologie', TOPOLOGIE);
    console.info('[MoteurVisuel] ✓ Initialisé — Scénario #' + SCENARIO_ID);
    return true;
}
