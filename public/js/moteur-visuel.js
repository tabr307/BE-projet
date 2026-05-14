/**
 * frontend/js/moteur-visuel.js
 * Moteur Vis.js adapté à la maquette UI avec séparation stricte Switchs / Réseaux et Fenêtre Modale.
 */
let network = null;
let nodes = new vis.DataSet([]);
let edges = new vis.DataSet([]);

// State global
let donneesScenario = { routeurs: [], switchs: [], reseaux: [], hotes: [], liaisons_hs: [], liaisons_is: [], liaisons_hi: [], liaisons_hh: [] };
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

        if (donneesScenario.statut === 'erreur' || donneesScenario.erreur) {
            await afficherAlerte("Erreur : " + (donneesScenario.message || donneesScenario.erreur));
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
    const sectionRoutes = document.getElementById('section-routes');
    const sectionHote = document.getElementById('section-hote');

    sectionInterfaces.style.display = 'none';
    sectionRoutes.style.display = 'none';
    sectionHote.style.display = 'none';

    if (type === 'routeurs') {
        sectionInterfaces.style.display = 'block';
        sectionRoutes.style.display = 'block';
        chargerInterfacesRouteur(id);
        chargerRoutesRouteur(id);
    } else if (type === 'hotes') {
        sectionHote.style.display = 'block';

        const formConfig = document.getElementById('form-config-hote');
        if (formConfig) formConfig.style.display = 'block';

        // Pré-remplir les données actuelles si disponibles
        const hote = donneesScenario.hotes.find(h => parseInt(h.id) === id);
        document.getElementById('hote-nom-interface').value = hote && hote.nom_interface ? hote.nom_interface : 'eth0';
        document.getElementById('hote-ip').value = hote && hote.adresse_ip ? hote.adresse_ip.split('/')[0] : '';
        document.getElementById('hote-cidr').value = '24';
        document.getElementById('hote-passerelle').value = hote && hote.passerelle_ip ? hote.passerelle_ip : '';
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

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    const styleBase = isDark ? {
        color: { background: '#1f2937', border: '#4b5563', highlight: { background: '#374151', border: '#6b7280' } },
        font: { color: '#f9fafb', face: 'system-ui', size: 14 },
        borderWidth: 1,
        shadow: { enabled: true, color: 'rgba(0,0,0,0.5)', size: 4, x: 2, y: 2 }
    } : {
        color: { background: '#ffffff', border: '#d1d5db', highlight: { background: '#f9fafb', border: '#9ca3af' } },
        font: { color: '#1f2937', face: 'system-ui', size: 14 },
        borderWidth: 1,
        shadow: { enabled: true, color: 'rgba(0,0,0,0.04)', size: 4, x: 2, y: 2 }
    };

    const conf = {
        routeurs: { shape: 'box', ...styleBase, shapeProperties: { borderRadius: 0 } },
        switchs: {
            shape: 'box',
            ...styleBase,
            color: isDark ? { background: '#1e3a8a', border: '#3b82f6', highlight: { background: '#2563eb', border: '#60a5fa' } } : { background: '#f0f9ff', border: '#bae6fd' },
            shapeProperties: { borderRadius: 4 }
        },
        reseaux: { shape: 'ellipse', ...styleBase },
        hotes: { shape: 'box', ...styleBase, shapeProperties: { borderRadius: 8 } }
    };

    ['routeurs', 'switchs', 'reseaux', 'hotes'].forEach(type => {
        (donneesScenario[type] || []).forEach(item => {
            let itemStyle = { ...conf[type] };

            // Verrouillage visuel de l'hôte
            if (type === 'hotes') {
                const estConnecte = donneesScenario.liaisons_hs.some(l => parseInt(l.hote_id) === parseInt(item.id)) ||
                    (donneesScenario.liaisons_hi && donneesScenario.liaisons_hi.some(l => parseInt(l.hote_id) === parseInt(item.id))) ||
                    (donneesScenario.liaisons_hh && donneesScenario.liaisons_hh.some(l => parseInt(l.hote_1_id) === parseInt(item.id) || parseInt(l.hote_2_id) === parseInt(item.id)));
                if (!estConnecte) {
                    if (isDark) {
                        itemStyle.color = { background: '#111827', border: '#374151' };
                        itemStyle.font = { color: '#4b5563' };
                    } else {
                        itemStyle.color = { background: '#f3f4f6', border: '#d1d5db' };
                        itemStyle.font = { color: '#9ca3af' };
                    }
                }
            }

            let px = parseFloat(item.pos_x);
            let py = parseFloat(item.pos_y);
            if (!px && !py) {
                window.spawnOffsetX = (window.spawnOffsetX || 0) + 30;
                window.spawnOffsetY = (window.spawnOffsetY || 0) + 30;
                px = -150 + (window.spawnOffsetX % 300);
                py = -150 + (window.spawnOffsetY % 300);
                item.pos_x = px;
                item.pos_y = py;
            }

            allNodes.push({
                id: `${type}_${item.id}`,
                label: item.nom,
                x: px,
                y: py,
                ...itemStyle
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

    (donneesScenario.liaisons_hi || []).forEach(l => {
        edges.add({ id: `lhi_${l.hote_id}_${l.interface_id}`, from: `hotes_${l.hote_id}`, to: `routeurs_${l.routeur_id}`, ...edgeStyle });
    });

    (donneesScenario.liaisons_hh || []).forEach(l => {
        edges.add({ id: `lhh_${l.hote_1_id}_${l.hote_2_id}`, from: `hotes_${l.hote_1_id}`, to: `hotes_${l.hote_2_id}`, ...edgeStyle });
    });

    (donneesScenario.liaisons_ii || []).forEach(l => {
        edges.add({ id: `lii_${l.interface_id}_${l.interface_1_id}`, from: `routeurs_${l.routeur_id}`, to: `routeurs_${l.routeur_1_id}`, ...edgeStyle });
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
                            await afficherAlerte("Interruption : Le routeur ne possède aucune interface physique.");
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

                    if ((fromType === 'hotes' && toType === 'routeurs') || (fromType === 'routeurs' && toType === 'hotes')) {
                        const hoteId = fromType === 'hotes' ? fromId : toId;
                        const routeurId = fromType === 'routeurs' ? fromId : toId;

                        const reqIntf = await apiFetch({ action: 'lire_interfaces_routeur', id_routeur: routeurId });
                        if (reqIntf.statut !== 'succes' || !reqIntf.interfaces || reqIntf.interfaces.length === 0) {
                            await afficherAlerte("Interruption : Le routeur ne possède aucune interface physique.");
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

                        const res = await apiFetch({ action: 'creer_liaison_hote_interface', hote_id: hoteId, interface_id: idInterfaceCible });
                        if (res.statut === 'succes') { callback(data); initialiserEditeur(); }
                        else { await afficherAlerte("Exception d'insertion : " + (res.message || "Rejet transactionnel.")); }
                        return;
                    }

                    if (fromType === 'routeurs' && toType === 'routeurs') {
                        const reqIntf1 = await apiFetch({ action: 'lire_interfaces_routeur', id_routeur: fromId });
                        if (reqIntf1.statut !== 'succes' || !reqIntf1.interfaces || reqIntf1.interfaces.length === 0) {
                            await afficherAlerte(`Interruption : Le routeur source ne possède aucune interface physique.`);
                            return;
                        }

                        let idInterface1;
                        if (reqIntf1.interfaces.length === 1) {
                            idInterface1 = reqIntf1.interfaces[0].id;
                        } else {
                            const selection = await demanderSelectionInterface(reqIntf1.interfaces);
                            if (!selection) return;
                            idInterface1 = selection;
                        }

                        const reqIntf2 = await apiFetch({ action: 'lire_interfaces_routeur', id_routeur: toId });
                        if (reqIntf2.statut !== 'succes' || !reqIntf2.interfaces || reqIntf2.interfaces.length === 0) {
                            await afficherAlerte(`Interruption : Le routeur destination ne possède aucune interface physique.`);
                            return;
                        }

                        let idInterface2;
                        if (reqIntf2.interfaces.length === 1) {
                            idInterface2 = reqIntf2.interfaces[0].id;
                        } else {
                            const selection = await demanderSelectionInterface(reqIntf2.interfaces);
                            if (!selection) return;
                            idInterface2 = selection;
                        }

                        if (idInterface1 === idInterface2) {
                            await afficherAlerte(`Erreur : Les interfaces doivent être différentes.`);
                            return;
                        }

                        const res = await apiFetch({ action: 'creer_liaison_interface_interface', interface_1_id: idInterface1, interface_2_id: idInterface2 });
                        if (res.statut === 'succes') { callback(data); initialiserEditeur(); }
                        else { await afficherAlerte("Exception d'insertion : " + (res.message || "Rejet transactionnel.")); }
                        return;
                    }

                    if (fromType === 'hotes' && toType === 'hotes') {
                        const res = await apiFetch({ action: 'creer_liaison_hote_hote', hote_1_id: fromId, hote_2_id: toId });
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
                const x = Math.round(pos.x);
                const y = Math.round(pos.y);

                // Sauvegarde distante
                apiFetch({ action: 'mettre_a_jour_positions', id, x, y });

                // Sauvegarde locale pour éviter le reset lors du changement de thème
                const parts = id.split('_');
                const type = parts[0];
                const itemId = parseInt(parts[1], 10);
                if (donneesScenario[type]) {
                    const item = donneesScenario[type].find(i => parseInt(i.id, 10) === itemId);
                    if (item) {
                        item.pos_x = x;
                        item.pos_y = y;
                    }
                }
            }
        });

        network.on("doubleClick", function (params) {
            if (params.nodes.length > 0) {
                // 1. Purge immédiate de la sélection pour libérer le moteur physique
                network.unselectAll();

                const idVis = params.nodes[0];
                const parts = idVis.split('_');
                const type = parts[0];
                const itemId = parseInt(parts[1], 10);

                if (donneesScenario[type]) {
                    const item = donneesScenario[type].find(i => parseInt(i.id, 10) === itemId);
                    if (item) {
                        // 2. Délégation Asynchrone : Permet à l'Event Loop de terminer le 'mouseUp'
                        setTimeout(() => {
                            editerElt(type, itemId, item.nom);
                            const modalEdition = document.getElementById('modal-edition');
                            if (modalEdition) {
                                modalEdition.classList.remove('hidden');
                            }
                        }, 50);
                    }
                }
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
                <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-clair); border:1px solid var(--bordure); padding:8px 12px; border-radius:6px; margin-bottom:8px;">
                    <div>
                        <strong style="font-size:13px; color:var(--texte-principal);" class="nom-interface-liste">${intf.nom}</strong><br>
                        <span style="font-size:12px; color:var(--texte-muet);" class="ip-interface-liste">${intf.adresse_ip} / ${intf.masque}</span>
                    </div>
                    <div>
                        <button style="background:none; border:none; color:#3b82f6; font-weight:bold; cursor:pointer; margin-right:8px;" onclick="preparerEditionInterface(${intf.id}, this)">✎</button>
                        <button style="background:none; border:none; color:#ef4444; font-weight:bold; cursor:pointer;" onclick="supprimerInterfaceRouteur(${intf.id}, ${id_routeur})">✕</button>
                    </div>
                </div>
            `).join('');
        }
    } else {
        container.innerHTML = '<p class="texte-muet text-sm" style="color:red;">Erreur de lecture API.</p>';
    }
}

let interfaceEnEdition = null;

function preparerEditionInterface(id, btnElement) {
    const conteneur = btnElement.closest('div').previousElementSibling;
    const nom = conteneur.querySelector('.nom-interface-liste').textContent.trim();
    const [ip, masque] = conteneur.querySelector('.ip-interface-liste').textContent.split('/').map(s => s.trim());

    document.getElementById('nouvelle-int-nom').value = nom;
    document.getElementById('nouvelle-int-ip').value = ip;
    document.getElementById('nouvelle-int-masque').value = masque;

    interfaceEnEdition = id;

    const form = document.getElementById('form-ajout-interface');
    form.classList.remove('hidden');
    document.getElementById('icon-toggle-interface').textContent = '▼';

    // Changer le texte du bouton en "Modifier" si désiré, ou juste le laisser
}

async function ajouterInterfaceRouteur() {
    if (!elementEnEdition || elementEnEdition.type !== 'routeurs') return;

    const nom = document.getElementById('nouvelle-int-nom').value.trim();
    const ip = document.getElementById('nouvelle-int-ip').value.trim();
    const masque = document.getElementById('nouvelle-int-masque').value.trim();

    if (!nom || !ip || !masque) {
        await afficherAlerte("Validation échouée : Vecteur de paramètres incomplet.");
        return;
    }

    const payload = {
        action: interfaceEnEdition ? 'modifier_interface_routeur' : 'creer_interface_routeur',
        id_routeur: elementEnEdition.id,
        nom: nom,
        ip: ip,
        masque: parseInt(masque, 10)
    };
    if (interfaceEnEdition) payload.id_interface = interfaceEnEdition;

    const res = await apiFetch(payload);

    if (res.statut === 'succes') {
        document.getElementById('nouvelle-int-nom').value = '';
        document.getElementById('nouvelle-int-ip').value = '';
        document.getElementById('nouvelle-int-masque').value = '';
        interfaceEnEdition = null;

        toggleInterfaceForm();
        chargerInterfacesRouteur(elementEnEdition.id);
    } else {
        await afficherAlerte("Exception de modification : " + (res.message || "Rejet de la transaction."));
    }
}

async function supprimerInterfaceRouteur(id_interface, id_routeur) {
    if (await demanderConfirmation("Confirmer la destruction de l'interface réseau ?")) {
        const res = await apiFetch({ action: 'supprimer_interface', id_interface: id_interface });
        if (res.statut === 'succes') {
            chargerInterfacesRouteur(id_routeur);
        } else {
            await afficherAlerte("Exception de suppression : " + (res.message || "Rejet de la transaction."));
        }
    }
}

// --- LOGIQUE DES ROUTES STATIQUES ---

function toggleRouteForm() {
    const form = document.getElementById('form-ajout-route');
    const icon = document.getElementById('icon-toggle-route');

    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        icon.textContent = '▼';
    } else {
        form.classList.add('hidden');
        icon.textContent = '▶';
    }
}

async function chargerRoutesRouteur(id_routeur) {
    const container = document.getElementById('liste-routes');
    const msgVide = document.getElementById('liste-routes-vides');
    container.innerHTML = '<p class="texte-muet text-sm">Chargement en cours...</p>';

    const res = await apiFetch({ action: 'lire_routes', id_routeur: id_routeur });

    if (res.statut === 'succes') {
        if (!res.routes || res.routes.length === 0) {
            container.innerHTML = '';
            msgVide.style.display = 'block';
        } else {
            msgVide.style.display = 'none';
            container.innerHTML = res.routes.map(route => `
                <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-clair); border:1px solid var(--bordure); padding:8px 12px; border-radius:6px; margin-bottom:8px;">
                    <div>
                        <strong style="font-size:13px; color:var(--texte-principal);" class="dest-route-liste">${route.reseau_dest} / ${route.masque_dest}</strong><br>
                        <span style="font-size:12px; color:var(--texte-muet);" class="hop-route-liste">Via : ${route.next_hop}</span>
                    </div>
                    <div>
                        <button style="background:none; border:none; color:#3b82f6; font-weight:bold; cursor:pointer; margin-right:8px;" onclick="preparerEditionRoute(${route.id}, this)">✎</button>
                        <button style="background:none; border:none; color:#ef4444; font-weight:bold; cursor:pointer;" onclick="supprimerRouteStatique(${route.id}, ${id_routeur})">✕</button>
                    </div>
                </div>
            `).join('');
        }
    } else {
        container.innerHTML = '<p class="texte-muet text-sm" style="color:red;">Erreur de lecture API.</p>';
    }
}

let routeEnEdition = null;

function preparerEditionRoute(id, btnElement) {
    const conteneur = btnElement.closest('div').previousElementSibling;
    const [dest, masque] = conteneur.querySelector('.dest-route-liste').textContent.split('/').map(s => s.trim());
    const nextHop = conteneur.querySelector('.hop-route-liste').textContent.replace('Via :', '').trim();

    document.getElementById('nouvelle-route-dest').value = dest;
    document.getElementById('nouvelle-route-masque').value = masque;
    document.getElementById('nouvelle-route-nexthop').value = nextHop;

    routeEnEdition = id;

    const form = document.getElementById('form-ajout-route');
    form.classList.remove('hidden');
    document.getElementById('icon-toggle-route').textContent = '▼';
}

async function ajouterRouteStatique() {
    if (!elementEnEdition || elementEnEdition.type !== 'routeurs') return;

    const reseauDest = document.getElementById('nouvelle-route-dest').value.trim();
    const masqueDest = document.getElementById('nouvelle-route-masque').value.trim();
    const nextHop = document.getElementById('nouvelle-route-nexthop').value.trim();

    if (!reseauDest || !masqueDest || !nextHop) {
        await afficherAlerte("Validation échouée : Tous les champs de la route sont obligatoires.");
        return;
    }

    const payload = {
        action: routeEnEdition ? 'modifier_route' : 'ajouter_route',
        id_routeur: elementEnEdition.id,
        reseau_dest: reseauDest,
        masque_dest: parseInt(masqueDest, 10),
        next_hop: nextHop
    };
    if (routeEnEdition) payload.id_route = routeEnEdition;

    const res = await apiFetch(payload);

    if (res.statut === 'succes') {
        document.getElementById('nouvelle-route-dest').value = '';
        document.getElementById('nouvelle-route-masque').value = '';
        document.getElementById('nouvelle-route-nexthop').value = '';
        routeEnEdition = null;

        toggleRouteForm();
        chargerRoutesRouteur(elementEnEdition.id);
    } else {
        await afficherAlerte("Exception de modification : " + (res.message || "Rejet de la transaction."));
    }
}

async function supprimerRouteStatique(id_route, id_routeur) {
    if (await demanderConfirmation("Confirmer la destruction de la route statique ?")) {
        const res = await apiFetch({ action: 'supprimer_route', id_route: id_route });
        if (res.statut === 'succes') {
            chargerRoutesRouteur(id_routeur);
        } else {
            await afficherAlerte("Exception de suppression : " + (res.message || "Rejet de la transaction."));
        }
    }
}

// --- LOGIQUE CONFIGURATION HOTE ---

async function sauvegarderHote() {
    if (!elementEnEdition || elementEnEdition.type !== 'hotes') return;

    const nom_int = document.getElementById('hote-nom-interface').value.trim();
    const ip = document.getElementById('hote-ip').value.trim();
    const cidr = document.getElementById('hote-cidr').value.trim();
    const passerelle = document.getElementById('hote-passerelle').value.trim();

    if (!ip || !cidr || !passerelle || !nom_int) {
        await afficherAlerte("Validation échouée : Nom d'interface, IP, CIDR et Passerelle sont requis.");
        return;
    }

    const res = await apiFetch({
        action: 'configurer_hote',
        id_hote: elementEnEdition.id,
        nom_interface: nom_int,
        ip: ip,
        cidr: parseInt(cidr, 10),
        passerelle: passerelle
    });

    if (res.statut === 'succes') {
        fermerModal();
        initialiserEditeur();
        await afficherAlerte("Configuration réseau appliquée avec succès.");
    } else {
        await afficherAlerte("Erreur de configuration : " + (res.message || "Rejet de la transaction."));
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

// --- WBS 3.0 : Écouteur pour la bascule de thème ---
window.addEventListener('themeChanged', () => {
    if (network) {
        dessinerReseau();
    }
});