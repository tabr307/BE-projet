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
let elementEnEdition = null; // NOUVEAU : Retient l'élément en cours de modification

// --- UTILITAIRE STRICT POUR REQUÊTES AJAX ---
async function apiFetch(payload) {
    const req = await fetch('api.php', {
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
        
        // Redirection propre si le scénario n'existe plus
        if (donneesScenario.erreur) {
            alert("Erreur : " + donneesScenario.erreur);
            window.location.href = 'index.php?page=tableau-de-bord';
            return;
        }
        
        document.getElementById('nom-scenario').textContent = donneesScenario.nom || "Scénario";
        switchEquipementTab(ongletActif);
        dessinerReseau();
    } catch (e) { 
        console.error("Erreur réseau critique :", e); 
        alert("Erreur critique de communication avec le serveur.");
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
    if (confirm("Supprimer cet élément ?")) {
        const res = await apiFetch({ action: 'supprimer_equipement', id: `${type}_${id}` });
        if (res.succes) initialiserEditeur();
        else alert("Erreur : " + (res.erreur || "Inconnue"));
    }
}

// --- GESTION DE LA FENÊTRE MODALE D'ÉDITION ---

// Remplace ton ancienne fonction editerElt par celle-ci
function editerElt(type, id, ancienNom) {
    elementEnEdition = { type, id, ancienNom };
    
    const libelles = { 'routeurs': 'Routeur', 'switchs': 'Switch', 'reseaux': 'Réseau', 'hotes': 'Hôte' };
    document.getElementById('modal-titre').textContent = `${libelles[type]} : ${ancienNom}`;
    document.getElementById('modal-input-nom').value = ancienNom;
    
    const sectionInterfaces = document.getElementById('section-interfaces');
    if (type === 'routeurs') {
        sectionInterfaces.style.display = 'block';
        chargerInterfacesRouteur(id); // <-- NOUVEAU : On charge les données de la BDD !
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
    
    // On sauvegarde uniquement si le nom a été modifié
    if (nouveauNom && nouveauNom !== elementEnEdition.ancienNom.trim()) {
        const res = await apiFetch({ 
            action: 'renommer_equipement', 
            type: elementEnEdition.type, 
            id: elementEnEdition.id, 
            nom: nouveauNom 
        });

        if (res.succes) {
            fermerModal();
            initialiserEditeur();
        } else {
            alert("Erreur lors du renommage : " + (res.erreur || "Action refusée par le serveur."));
        }
    } else {
        fermerModal(); // Pas de changement
    }
}

// ----------------------------------------------

async function ajouterElement() {
    if (ongletActif === 'routes') {
        alert("La gestion des routes statiques sera implémentée prochainement.");
        return;
    }
    
    const noms = { 'routeurs': 'R', 'switchs': 'SW', 'reseaux': 'LAN', 'hotes': 'PC' };
    const prefixe = noms[ongletActif] || 'E';
    const nom = prompt(`Nom de l'élément :`, `${prefixe}-${nodes.length + 1}`);
    
    if (nom) {
        const res = await apiFetch({ action: 'creer_equipement', scenario_id: CURRENT_SCENARIO_ID, type: ongletActif, nom: nom });
        if (res.succes) initialiserEditeur();
        else alert("Erreur lors de la création : " + (res.erreur || "Inconnue"));
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
                    const res = await apiFetch({ action: 'creer_liaison', from: data.from, to: data.to });
                    if (res.succes) { callback(data); initialiserEditeur(); }
                    else { alert("Impossible de relier : " + (res.erreur || "Raison inconnue")); }
                },
                deleteNode: async (data, callback) => {
                    if (confirm("Supprimer cet équipement ?")) {
                        const res = await apiFetch({ action: 'supprimer_equipement', id: data.nodes[0] });
                        if (res.succes) { callback(data); initialiserEditeur(); }
                        else { alert("Erreur : " + (res.erreur || "Inconnue")); }
                    }
                },
                deleteEdge: async (data, callback) => {
                    if (confirm("Débrancher ce câble ?")) {
                        const res = await apiFetch({ action: 'supprimer_liaison', id: data.edges[0] });
                        if (res.succes) { callback(data); initialiserEditeur(); }
                        else { alert("Erreur : " + (res.erreur || "Inconnue")); }
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
// --- NOUVEAUTÉ : LOGIQUE DES INTERFACES ---

async function chargerInterfacesRouteur(id_routeur) {
    const container = document.getElementById('liste-interfaces-actives');
    const msgVide = document.getElementById('liste-interfaces-vides');
    container.innerHTML = '<p class="texte-muet text-sm">Chargement en cours...</p>';
    
    const res = await apiFetch({ action: 'lire_interfaces_routeur', id_routeur: id_routeur });
    
    if (res.succes) {
        if (res.interfaces.length === 0) {
            container.innerHTML = '';
            msgVide.style.display = 'block';
        } else {
            msgVide.style.display = 'none';
            // On génère du HTML pour chaque interface trouvée en BDD
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
    }
}

async function ajouterInterfaceRouteur() {
    if (!elementEnEdition || elementEnEdition.type !== 'routeurs') return;
    
    const nom = document.getElementById('nouvelle-int-nom').value.trim();
    const ip = document.getElementById('nouvelle-int-ip').value.trim();
    const masque = document.getElementById('nouvelle-int-masque').value.trim();
    
    if(!nom || !ip || !masque) {
        alert("Veuillez remplir tous les champs (Nom, IP, Masque).");
        return;
    }

    const res = await apiFetch({
        action: 'creer_interface_routeur',
        id_routeur: elementEnEdition.id,
        nom: nom,
        ip: ip,
        masque: parseInt(masque)
    });

    if(res.succes) {
        // Vider le formulaire après succès
        document.getElementById('nouvelle-int-nom').value = '';
        document.getElementById('nouvelle-int-ip').value = '';
        document.getElementById('nouvelle-int-masque').value = '';
        
        // Cacher le formulaire et recharger la liste dynamiquement
        toggleInterfaceForm();
        chargerInterfacesRouteur(elementEnEdition.id);
    } else {
        alert("Erreur : " + (res.erreur || "Impossible d'ajouter l'interface."));
    }
}

async function supprimerInterfaceRouteur(id_interface, id_routeur) {
    if(confirm("Voulez-vous vraiment supprimer cette interface ?")) {
        const res = await apiFetch({ action: 'supprimer_interface', id_interface: id_interface });
        if(res.succes) {
            chargerInterfacesRouteur(id_routeur); // On recharge l'affichage instantanément
        } else {
            alert("Erreur lors de la suppression.");
        }
    }
}

