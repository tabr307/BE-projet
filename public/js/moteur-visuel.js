/**
 * frontend/js/moteur-visuel.js
 * Moteur Vis.js adapté à la maquette UI avec séparation stricte Switchs / Réseaux et Fenêtre Modale.
 */
let network = null;
let nodes = new vis.DataSet([]);
let edges = new vis.DataSet([]);

// State global
let donneesScenario = { routeurs: [], switchs: [], reseaux: [], hotes: [], liaisons_hs: [], liaisons_is: [] };
let ongletActif = 'routeurs'; 
let elementEnEdition = null;

// --- UTILITAIRE STRICT POUR REQUÊTES AJAX ---
async function apiFetch(payload) {
    const targetUrl = payload.action ? `api.php?action=${payload.action}` : 'api.php';
    const req = await fetch(targetUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    return await req.json();
}

document.addEventListener('DOMContentLoaded', () => { 
    if (typeof CURRENT_SCENARIO_ID !== 'undefined') initialiserEditeur(); 
});

async function initialiserEditeur() {
    try {
        donneesScenario = await apiFetch({ action: 'charger_scenario', id: CURRENT_SCENARIO_ID });
        
        if (donneesScenario.erreur) {
            await afficherAlerte("Erreur : " + donneesScenario.erreur);
            window.location.href = 'index.php?page=tableau-de-bord';
            return;
        }
        
        document.getElementById('nom-scenario').textContent = donneesScenario.nom || "Scénario";
        switchEquipementTab(ongletActif);
        dessinerReseau();
    } catch (e) { 
        console.error("Erreur réseau critique :", e); 
        await afficherAlerte("Erreur critique de communication avec le serveur.");
    }
}

function switchEquipementTab(type) {
    ongletActif = type;
    const mapNoms = { 'routeurs': 'routeurs', 'switchs': 'switchs', 'reseaux': 'réseaux', 'hotes': 'hôtes', 'routes': 'routes' };
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.trim().toLowerCase() === mapNoms[type]);
    });

    const labelsSingulier = { 'routeurs': 'routeur', 'switchs': 'switch', 'reseaux': 'réseau', 'hotes': 'hôte', 'routes': 'route' };
    const btnAjoutInfo = document.getElementById('label-type-ajout');
    if (btnAjoutInfo) btnAjoutInfo.textContent = labelsSingulier[type] || 'élément';

    mettreAJourInventaire(donneesScenario[type] || [], type);
}

function mettreAJourInventaire(items, type) {
    const cont = document.getElementById('inventaire-objets');
    if (!cont) return;
    
    if (items.length === 0) {
        cont.innerHTML = `<p class="p-3 text-sm text-gray-500 text-center">Aucun élément configuré.</p>`;
        return;
    }

    const sousTitres = {
        'routeurs': 'Interface(s) configurée(s)',
        'switchs': 'Commutateur L2',
        'reseaux': 'Réseau IP (LAN)',
        'hotes': 'Machine cible'
    };

    cont.innerHTML = items.map(i => `
        <div class="carte-objet">
            <div class="info-objet">
                <strong>${i.nom}</strong>
                <small>${sousTitres[type]}</small>
            </div>
            <div class="actions-objet">
                <button class="btn-action-mini text-gray-600" onclick="editerElt('${type}', ${i.id}, '${i.nom}')">Éditer</button>
                <button class="btn-action-mini text-red-500 font-bold" onclick="supprimerElt('${type}', ${i.id})">✕</button>
            </div>
        </div>
    `).join('');
}

// --- ACTIONS DEPUIS LA BARRE LATÉRALE ---

async function supprimerElt(type, id) {
    if (await demanderConfirmation("Supprimer cet élément ?")) {
        const res = await apiFetch({ action: 'supprimer_equipement', id: `${type}_${id}` });
        if (res.statut === 'succes' || res.succes) initialiserEditeur();
        else await afficherAlerte("Erreur : " + (res.message || res.erreur || "Inconnue"));
    }
}

// --- GESTION DE LA FENÊTRE MODALE D'ÉDITION ---

function editerElt(type, id, ancienNom) {
    elementEnEdition = { type, id, ancienNom };
    
    const libelles = { 'routeurs': 'Routeur', 'switchs': 'Switch', 'reseaux': 'Réseau', 'hotes': 'Hôte' };
    document.getElementById('modal-titre').textContent = `${libelles[type]} : ${ancienNom}`;
    document.getElementById('modal-input-nom').value = ancienNom;
    
    const sectionInterfaces = document.getElementById('section-interfaces');
    if (type === 'routeurs') {
        sectionInterfaces.style.display = 'block';
        chargerInterfacesRouteur(id);
    } else {
        sectionInterfaces.style.display = 'none';
    }

    document.getElementById('form-ajout-interface').classList.add('hidden');
    document.getElementById('icon-toggle-interface').textContent = '▶';
    
    document.getElementById('modal-edition').classList.remove('hidden');
}

function fermerModal() {
    document.getElementById('modal-edition').classList.add('hidden');
    elementEnEdition = null;
}

function toggleInterfaceForm() {
    const form = document.getElementById('form-ajout-interface');
    const icon = document.getElementById('icon-toggle-interface');
    
    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        icon.textContent = '▼';
    } else {
        form.classList.add('hidden');
        icon.textContent = '▶';
    }
}

async function sauvegarderEdition() {
    if (!elementEnEdition) return;
    
    const nouveauNom = document.getElementById('modal-input-nom').value.trim();
    
    if (nouveauNom && nouveauNom !== elementEnEdition.ancienNom.trim()) {
        const res = await apiFetch({ 
            action: 'renommer_equipement', 
            type: elementEnEdition.type, 
            id: elementEnEdition.id, 
            nom: nouveauNom 
        });

        if (res.statut === 'succes' || res.succes) {
            fermerModal();
            initialiserEditeur();
        } else {
            await afficherAlerte("Erreur lors du renommage : " + (res.message || res.erreur || "Action refusée par le serveur."));
        }
    } else {
        fermerModal();
    }
}

// ----------------------------------------------

async function ajouterElement() {
    if (ongletActif === 'routes') {
        await afficherAlerte("La gestion des routes statiques sera implémentée prochainement.");
        return;
    }

    const mapConfigurations = { 
        'routeurs': { prefixe: 'R', actionEndpoint: 'ajouter_routeur' },
        'switchs': { prefixe: 'SW', actionEndpoint: 'ajouter_commutateur' },
        'hotes': { prefixe: 'PC', actionEndpoint: 'ajouter_hote' },
        'reseaux': { prefixe: 'LAN', actionEndpoint: 'ajouter_sous_reseau' }
    };

    const configTarget = mapConfigurations[ongletActif];
    if (!configTarget || !configTarget.actionEndpoint) {
        await afficherAlerte("Erreur de typage : Élément non qualifié pour l'insertion.");
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const scenarioId = typeof CURRENT_SCENARIO_ID !== 'undefined' ? CURRENT_SCENARIO_ID : urlParams.get('id');

    if (!scenarioId) {
        await afficherAlerte("Erreur critique : Contexte de scénario introuvable.");
        return;
    }

    const nom = await demanderSaisie(`Nom de l'élément :`, `${configTarget.prefixe}-${nodes.length + 1}`);

    if (nom) {
        const res = await apiFetch({ 
            action: configTarget.actionEndpoint, 
            scenario_id: parseInt(scenarioId, 10), 
            nom: nom.trim() 
        });
        
        if (res.statut === 'succes') {
            initialiserEditeur();
        } else {
            await afficherAlerte("Erreur système : " + (res.message || "Exception de sérialisation."));
        }
    }
}

// --- DESSIN DU CANEVAS VIS.JS ---

function dessinerReseau() {
    const container = document.getElementById('network-canvas');
    nodes.clear(); edges.clear();
    const allNodes = [];
    
    const styleBase = { 
        color: { background: '#ffffff', border: '#d1d5db', highlight: { background: '#f9fafb', border: '#9ca3af' } },
        font: { color: '#1f2937', face: 'system-ui', size: 14 },
        borderWidth: 1,
        shadow: { enabled: true, color: 'rgba(0,0,0,0.04)', size: 4, x: 2, y: 2 }
    };

    const conf = { 
        routeurs: { shape: 'box', ...styleBase, shapeProperties: { borderRadius: 0 } },
        switchs:  { shape: 'box', ...styleBase, color: { background: '#f0f9ff', border: '#bae6fd' }, shapeProperties: { borderRadius: 4 } },
        reseaux:  { shape: 'ellipse', ...styleBase }, 
        hotes:    { shape: 'box', ...styleBase, shapeProperties: { borderRadius: 8 } }
    };

    ['routeurs', 'switchs', 'reseaux', 'hotes'].forEach(type => {
        (donneesScenario[type] || []).forEach(item => {
            allNodes.push({
                id: `${type}_${item.id}`, 
                label: item.nom,
                x: item.pos_x ? parseInt(item.pos_x) : undefined,
                y: item.pos_y ? parseInt(item.pos_y) : undefined,
                ...conf[type]
            });
        });
    });
    nodes.add(allNodes);

    const edgeStyle = { color: '#9ca3af', width: 1, smooth: false };

    (donneesScenario.liaisons_hs || []).forEach(l => {
        edges.add({ id: `lhs_${l.hote_id}_${l.switch_id}`, from: `hotes_${l.hote_id}`, to: `switchs_${l.switch_id}`, ...edgeStyle });
    });

    (donneesScenario.liaisons_is || []).forEach(l => {
        edges.add({ id: `lis_${l.interface_id}_${l.switch_id}`, from: `routeurs_${l.routeur_id}`, to: `switchs_${l.switch_id}`, ...edgeStyle });
    });

    if (!network) {
        network = new vis.Network(container, { nodes, edges }, {
            locale: 'fr',
            physics: false,
            interaction: { hover: true },
            manipulation: {
                enabled: true,
                initiallyActive: true,
                addNode: false,
                addEdge: async (data, callback) => {
                    const fromParts = data.from.split('_');
                    const toParts = data.to.split('_');
                    const fromType = fromParts[0], fromId = parseInt(fromParts[1]);
                    const toType = toParts[0], toId = parseInt(toParts[1]);

                    if ((fromType === 'hotes' && toType === 'switchs') || (fromType === 'switchs' && toType === 'hotes')) {
                        const hoteId = fromType === 'hotes' ? fromId : toId;
                        const switchId = fromType === 'switchs' ? fromId : toId;
                        const res = await apiFetch({ action: 'creer_liaison_hote_switch', hote_id: hoteId, switch_id: switchId });
                        
                        if (res.statut === 'succes') { callback(data); initialiserEditeur(); }
                        else { await afficherAlerte("Exception d'insertion : " + (res.message || "Rejet de la base de données.")); }
                        return;
                    }

                    if ((fromType === 'routeurs' && toType === 'switchs') || (fromType === 'switchs' && toType === 'routeurs')) {
                        const routeurId = fromType === 'routeurs' ? fromId : toId;
                        const switchId = fromType === 'switchs' ? fromId : toId;

                        const reqIntf = await apiFetch({ action: 'lire_interfaces_routeur', id_routeur: routeurId });
                        if (reqIntf.statut !== 'succes' || !reqIntf.interfaces || reqIntf.interfaces.length === 0) {
                            await afficherAlerte("Interruption : Le routeur ne possède aucune interface physique. Initialisation de l'environnement de configuration.");
                            const noeudRouteur = donneesScenario.routeurs.find(r => r.id === routeurId);
                            const nomRouteur = noeudRouteur ? noeudRouteur.nom : `Routeur ${routeurId}`;
                            editerElt('routeurs', routeurId, nomRouteur);
                            return;
                        }

                        let idInterfaceCible;

                        if (reqIntf.interfaces.length === 1) {
                            idInterfaceCible = reqIntf.interfaces[0].id;
                        } else {
                            const selection = await demanderSelectionInterface(reqIntf.interfaces);
                            if (!selection) return; 
                            idInterfaceCible = selection;
                        }

                        const res = await apiFetch({ action: 'creer_liaison_interface_switch', interface_id: idInterfaceCible, switch_id: switchId });
                        if (res.statut === 'succes') { callback(data); initialiserEditeur(); }
                        else { await afficherAlerte("Exception d'insertion : " + (res.message || "Rejet transactionnel.")); }
                        return;
                    }

                    await afficherAlerte("Violation de contrainte : Topologie de liaison non autorisée par le modèle WBS 1.1.");
                },
                deleteNode: async (data, callback) => {
                    if (await demanderConfirmation("Confirmer la destruction matérielle ?")) {
                        const res = await apiFetch({ action: 'supprimer_equipement', id: data.nodes[0] });
                        if (res.statut === 'succes' || res.succes) { callback(data); initialiserEditeur(); }
                        else { await afficherAlerte("Exception de suppression : " + (res.message || res.erreur || "Requête rejetée.")); }
                    }
                },
                deleteEdge: async (data, callback) => {
                    if (await demanderConfirmation("Confirmer le retrait du câblage ?")) {
                        const res = await apiFetch({ action: 'supprimer_liaison', id: data.edges[0] });
                        if (res.statut === 'succes' || res.succes) { callback(data); initialiserEditeur(); }
                        else { await afficherAlerte("Exception de suppression : " + (res.message || res.erreur || "Requête rejetée.")); }
                    }
                }
            }
        });

        network.on("dragEnd", p => {
            if (p.nodes.length) {
                const id = p.nodes[0]; 
                const pos = network.getPositions([id])[id];
                apiFetch({ action: 'mettre_a_jour_positions', id, x: Math.round(pos.x), y: Math.round(pos.y) });
            }
        });
    }
}

// --- LOGIQUE DES INTERFACES ---

async function chargerInterfacesRouteur(id_routeur) {
    const container = document.getElementById('liste-interfaces-actives');
    const msgVide = document.getElementById('liste-interfaces-vides');
    container.innerHTML = '<p class="texte-muet text-sm">Chargement en cours...</p>';
    
    const res = await apiFetch({ action: 'lire_interfaces_routeur', id_routeur: id_routeur });
    
    if (res.statut === 'succes') {
        if (!res.interfaces || res.interfaces.length === 0) {
            container.innerHTML = '';
            msgVide.style.display = 'block';
        } else {
            msgVide.style.display = 'none';
            container.innerHTML = res.interfaces.map(intf => `
                <div style="display:flex; justify-content:space-between; align-items:center; background:#f9fafb; border:1px solid #e5e7eb; padding:8px 12px; border-radius:6px; margin-bottom:8px;">
                    <div>
                        <strong style="font-size:13px; color:#1f2937;">${intf.nom}</strong><br>
                        <span style="font-size:12px; color:#6b7280;">${intf.adresse_ip} / ${intf.masque}</span>
                    </div>
                    <button style="background:none; border:none; color:#ef4444; font-weight:bold; cursor:pointer;" onclick="supprimerInterfaceRouteur(${intf.id}, ${id_routeur})">✕</button>
                </div>
            `).join('');
        }
    } else {
        container.innerHTML = '<p class="texte-muet text-sm" style="color:red;">Erreur de lecture API.</p>';
    }
}

async function ajouterInterfaceRouteur() {
    if (!elementEnEdition || elementEnEdition.type !== 'routeurs') return;
    
    const nom = document.getElementById('nouvelle-int-nom').value.trim();
    const ip = document.getElementById('nouvelle-int-ip').value.trim();
    const masque = document.getElementById('nouvelle-int-masque').value.trim();
    
    if(!nom || !ip || !masque) {
        await afficherAlerte("Validation échouée : Vecteur de paramètres incomplet.");
        return;
    }

    const res = await apiFetch({
        action: 'creer_interface_routeur',
        id_routeur: elementEnEdition.id,
        nom: nom,
        ip: ip,
        masque: parseInt(masque, 10)
    });

    if (res.statut === 'succes') {
        document.getElementById('nouvelle-int-nom').value = '';
        document.getElementById('nouvelle-int-ip').value = '';
        document.getElementById('nouvelle-int-masque').value = '';
        
        toggleInterfaceForm();
        chargerInterfacesRouteur(elementEnEdition.id);
    } else {
        await afficherAlerte("Exception d'insertion : " + (res.message || "Rejet de la transaction."));
    }
}

async function supprimerInterfaceRouteur(id_interface, id_routeur) {
    if(await demanderConfirmation("Confirmer la destruction de l'interface réseau ?")) {
        const res = await apiFetch({ action: 'supprimer_interface', id_interface: id_interface });
        if (res.statut === 'succes') {
            chargerInterfacesRouteur(id_routeur);
        } else {
            await afficherAlerte("Exception de suppression : " + (res.message || "Rejet de la transaction."));
        }
    }
}

// --- UTILITAIRES ASYNCHRONES DOM (MODALES) ---

function demanderSelectionInterface(interfaces) {
    return new Promise((resolve) => {
        const modal = document.getElementById('modal-choix-interface');
        const select = document.getElementById('select-interface-cible');
        const btnConfirm = document.getElementById('btn-confirmer-interface');
        const btnCancel = document.getElementById('btn-annuler-interface');

        select.innerHTML = interfaces.map(i => 
            `<option value="${i.id}">[ID: ${i.id}] ${i.nom} (${i.adresse_ip}/${i.masque})</option>`
        ).join('');

        modal.classList.remove('hidden');

        const cleanUp = () => {
            modal.classList.add('hidden');
            btnConfirm.removeEventListener('click', onConfirm);
            btnCancel.removeEventListener('click', onCancel);
        };

        const onConfirm = () => { cleanUp(); resolve(parseInt(select.value, 10)); };
        const onCancel = () => { cleanUp(); resolve(null); };

        btnConfirm.addEventListener('click', onConfirm);
        btnCancel.addEventListener('click', onCancel);
    });
}

function afficherAlerte(message) {
    return new Promise((resolve) => {
        const modal = document.getElementById('modal-alerte');
        document.getElementById('texte-alerte').textContent = message;
        modal.classList.remove('hidden');

        const btnOk = document.getElementById('btn-fermer-alerte');
        
        const onOk = () => {
            modal.classList.add('hidden');
            btnOk.removeEventListener('click', onOk);
            resolve();
        };
        btnOk.addEventListener('click', onOk);
    });
}

function demanderConfirmation(message) {
    return new Promise((resolve) => {
        const modal = document.getElementById('modal-confirmation');
        document.getElementById('texte-confirmation').textContent = message;
        modal.classList.remove('hidden');

        const btnConfirm = document.getElementById('btn-valider-confirmation');
        const btnCancel = document.getElementById('btn-annuler-confirmation');

        const cleanUp = () => {
            modal.classList.add('hidden');
            btnConfirm.removeEventListener('click', onConfirm);
            btnCancel.removeEventListener('click', onCancel);
        };

        const onConfirm = () => { cleanUp(); resolve(true); };
        const onCancel = () => { cleanUp(); resolve(false); };

        btnConfirm.addEventListener('click', onConfirm);
        btnCancel.addEventListener('click', onCancel);
    });
}

function demanderSaisie(message, valeurDefaut = '') {
    return new Promise((resolve) => {
        const modal = document.getElementById('modal-saisie');
        document.getElementById('texte-saisie').textContent = message;
        
        const input = document.getElementById('input-saisie');
        input.value = valeurDefaut;
        modal.classList.remove('hidden');
        input.focus(); // Place le curseur directement dans le champ de saisie

        const btnConfirm = document.getElementById('btn-valider-saisie');
        const btnCancel = document.getElementById('btn-annuler-saisie');

        const cleanUp = () => {
            modal.classList.add('hidden');
            btnConfirm.removeEventListener('click', onConfirm);
            btnCancel.removeEventListener('click', onCancel);
        };

        const onConfirm = () => { cleanUp(); resolve(input.value); };
        const onCancel = () => { cleanUp(); resolve(null); }; // Retourne null pour simuler l'annulation

        btnConfirm.addEventListener('click', onConfirm);
        btnCancel.addEventListener('click', onCancel);
        
        // Validation rapide avec la touche "Entrée"
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                onConfirm();
            }
        }, { once: true });
    });
}