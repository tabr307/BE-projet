/**
 * public/js/animation.js
 * Moteur d'Animation de Simulation IP (WBS 5.0)
 * Gère l'interaction UI, l'appel à l'API de simulation, et l'animation vis.js.
 */

// 1. GESTION DE LA MODALE ET DU LANCEMENT

async function lancerSimulation() {
    // Remplir les listes déroulantes avec les hôtes disponibles
    const hotes = donneesScenario.hotes || [];
    if (hotes.length < 2) {
        afficherAlerte("Vous devez configurer au moins deux hôtes pour simuler un trajet.");
        return;
    }

    const selectSource = document.getElementById('sim-source');
    const selectDest = document.getElementById('sim-dest');
    
    selectSource.innerHTML = '';
    selectDest.innerHTML = '';

    hotes.forEach(h => {
        const hasIP = h.adresse_ip ? `(${h.adresse_ip})` : '(Pas d\'IP)';
        const optionHTML = `<option value="${h.id}" data-ip="${h.adresse_ip || ''}">${h.nom} ${hasIP}</option>`;
        selectSource.innerHTML += optionHTML;
        selectDest.innerHTML += optionHTML;
    });

    // Sélection par défaut (éviter source == dest si possible)
    if (selectDest.options.length > 1) {
        selectDest.selectedIndex = 1;
    }

    document.getElementById('modal-simulation').classList.remove('hidden');
}

function fermerModalSimulation() {
    document.getElementById('modal-simulation').classList.add('hidden');
}

let animationEnCours = false;

async function executerSimulation() {
    if (animationEnCours) return;
    
    const selectSource = document.getElementById('sim-source');
    const selectDest = document.getElementById('sim-dest');

    const idSource = parseInt(selectSource.value, 10);
    const selectedDestOption = selectDest.options[selectDest.selectedIndex];
    const ipDest = selectedDestOption.getAttribute('data-ip');

    if (!ipDest) {
        afficherAlerte("L'hôte de destination n'a pas d'adresse IP valide.");
        return;
    }

    fermerModalSimulation();
    
    animationEnCours = true;
    const btnSimulerHeader = document.querySelector('.btn-simuler');
    if (btnSimulerHeader) {
        btnSimulerHeader.disabled = true;
        btnSimulerHeader.style.opacity = '0.5';
    }

    // Lancer la requête de simulation
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'simuler_routage',
                scenario_id: CURRENT_SCENARIO_ID,
                id_source: idSource,
                ip_dest: ipDest
            })
        });

        const data = await response.json();
        
        if (data.statut === 'succes' || data.statut === 'erreur') {
            await demarrerAnimation(data.trace, data.statut === 'erreur' ? data.message : "Arrivée à destination !");
        } else {
            afficherAlerte("Erreur inattendue : " + JSON.stringify(data));
        }

    } catch (error) {
        console.error(error);
        afficherAlerte("Impossible de contacter le moteur de routage.");
    } finally {
        animationEnCours = false;
        if (btnSimulerHeader) {
            btnSimulerHeader.disabled = false;
            btnSimulerHeader.style.opacity = '1';
        }
    }
}

// 2. MOTEUR D'ANIMATION (VIS.JS DOM)

let enveloppeDOM = null;

async function demarrerAnimation(traceInitiale, messageFinal) {
    if (!traceInitiale || traceInitiale.length === 0) return;

    // --- INJECTION LAYER 2 (VISUELLE) ---
    // Le backend ne retourne que les sauts L3 (Hôtes et Routeurs).
    // Pour l'animation visuelle, on doit insérer les switchs intermédiaires.
    let trace = [];
    for (let i = 0; i < traceInitiale.length; i++) {
        trace.push(traceInitiale[i]);
        if (i < traceInitiale.length - 1) {
            let nA = traceInitiale[i];
            let nB = traceInitiale[i+1];
            
            // Cherchons si un switch relie A et B
            let switchId = null;
            let liaisons_is = donneesScenario.liaisons_is || [];
            let liaisons_hs = donneesScenario.liaisons_hs || [];
            
            let switchsA = [];
            if (nA.type_noeud === 'routeur') switchsA = liaisons_is.filter(l => parseInt(l.id_routeur || l.routeur_id || l.interface_id) !== -1).map(l => parseInt(l.switch_id)); // simplifé: on prend ts les switch_id
            if (nA.type_noeud === 'hote') switchsA = liaisons_hs.filter(l => parseInt(l.hote_id) === parseInt(nA.id_noeud)).map(l => parseInt(l.switch_id));
            
            let switchsB = [];
            if (nB.type_noeud === 'routeur') switchsB = liaisons_is.filter(l => parseInt(l.id_routeur || l.routeur_id || l.interface_id) !== -1).map(l => parseInt(l.switch_id));
            if (nB.type_noeud === 'hote') switchsB = liaisons_hs.filter(l => parseInt(l.hote_id) === parseInt(nB.id_noeud)).map(l => parseInt(l.switch_id));

            // Si nA et nB partagent un switch (basique: intersection)
            // Note: En base, interface_id lie le routeur au switch. On triche visuellement en regardant directement le graphe vis.js (edges).
            let edgeTrouve = network.body.data.edges.get({
                filter: function (item) {
                    return (item.from === `${nA.type_noeud}s_${nA.id_noeud}` && item.to.startsWith('switchs_')) ||
                           (item.to === `${nA.type_noeud}s_${nA.id_noeud}` && item.from.startsWith('switchs_'));
                }
            });
            let edgeB = network.body.data.edges.get({
                filter: function (item) {
                    return (item.from === `${nB.type_noeud}s_${nB.id_noeud}` && item.to.startsWith('switchs_')) ||
                           (item.to === `${nB.type_noeud}s_${nB.id_noeud}` && item.from.startsWith('switchs_'));
                }
            });

            // Intersection des switchs
            let swA = edgeTrouve.map(e => e.from.startsWith('switchs_') ? e.from : e.to);
            let swB = edgeB.map(e => e.from.startsWith('switchs_') ? e.from : e.to);
            let swCommun = swA.find(s => swB.includes(s));
            
            if (swCommun) {
                let sId = parseInt(swCommun.split('_')[1], 10);
                trace.push({
                    type_noeud: 'switch',
                    id_noeud: sId,
                    etat_datagramme: nA.etat_datagramme // Garde l'état
                });
            }
        }
    }

    // Afficher le HUD
    const hud = document.getElementById('hud-simulation');
    hud.classList.remove('hidden');
    
    // Nettoyer ancienne animation
    if (enveloppeDOM) enveloppeDOM.remove();
    document.getElementById('hud-message').textContent = "";
    document.getElementById('hud-message').style.color = "var(--primaire)";

    // Créer le DOM Envelope
    enveloppeDOM = document.createElement('div');
    enveloppeDOM.className = 'datagram-envelope';
    enveloppeDOM.textContent = '✉️';
    document.getElementById('network-canvas').appendChild(enveloppeDOM);

    // Vitesse de transition
    const DELAI_SAUT_MS = 1500;
    
    for (let i = 0; i < trace.length; i++) {
        const hop = trace[i];
        
        // Mettre à jour HUD (En-tête IP)
        if (hop.etat_datagramme) {
            mettreAJourHUD(hop.etat_datagramme, hop.hop_index);
        }

        // Trouver les coordonnées du nœud vis.js (ex: hotes_1, routeurs_2)
        const visNodeId = `${hop.type_noeud}s_${hop.id_noeud}`;
        
        try {
            // Convertir coordonnées Canvas (vis.js) -> DOM (Pixels)
            const canvasCoords = network.getPositions([visNodeId])[visNodeId];
            if (canvasCoords) {
                const domCoords = network.canvasToDOM(canvasCoords);
                
                // Déplacer l'enveloppe
                enveloppeDOM.style.left = `${domCoords.x}px`;
                enveloppeDOM.style.top = `${domCoords.y}px`;
                
                // Déplacer le HUD pour qu'il suive l'enveloppe
                hud.style.left = `${domCoords.x + 30}px`;
                hud.style.top = `${domCoords.y - 60}px`;
            }
        } catch (e) {
            console.warn(`Impossible de positionner sur ${visNodeId}`);
        }

        // Si c'est le dernier saut et qu'il contient une erreur, on met en rouge
        if (i === trace.length - 1 && messageFinal && messageFinal.includes("Exception") === false && hop.message && hop.message.includes("Erreur")) {
            enveloppeDOM.classList.add('datagram-error');
            document.getElementById('hud-message').style.color = "var(--rouge, #ef4444)";
        }

        // Attendre avant le prochain saut
        if (i < trace.length - 1) {
            await new Promise(r => setTimeout(r, DELAI_SAUT_MS));
        }
    }

    // Afficher le statut final
    document.getElementById('hud-message').textContent = messageFinal;
    if (messageFinal.includes("Network Unreachable") || messageFinal.includes("Time Exceeded") || messageFinal.includes("Boucle")) {
        enveloppeDOM.classList.add('datagram-error');
        document.getElementById('hud-message').style.color = "var(--rouge, #ef4444)";
        document.getElementById('hud-message').textContent = "Erreur : " + messageFinal;
    }

    // L'enveloppe disparaît au bout de 5 secondes
    setTimeout(() => {
        hud.classList.add('hidden');
        if (enveloppeDOM) enveloppeDOM.remove();
        enveloppeDOM = null;
    }, 5000);
}

// 3. MISE À JOUR DU HUD (En-tête IP dynamique)

let dernierTTL = -1;
let dernierChecksum = "";

function mettreAJourHUD(etat, hopIndex) {
    const elId = document.getElementById('hud-id');
    const elDf = document.getElementById('hud-df');
    const elTtl = document.getElementById('hud-ttl');
    const elChk = document.getElementById('hud-checksum');
    const elSrc = document.getElementById('hud-src');
    const elDst = document.getElementById('hud-dest');

    elId.textContent = etat.id;
    elDf.textContent = etat.df;
    elSrc.textContent = etat.ip_source;
    elDst.textContent = etat.ip_dest;

    // Surlignage TTL si modifié
    if (hopIndex > 0 && dernierTTL !== -1 && etat.ttl !== dernierTTL) {
        elTtl.classList.add('hud-highlight');
        setTimeout(() => elTtl.classList.remove('hud-highlight'), 800);
    }
    elTtl.textContent = etat.ttl;
    dernierTTL = etat.ttl;

    // Surlignage Checksum si modifié
    if (hopIndex > 0 && dernierChecksum !== "" && etat.checksum !== dernierChecksum) {
        elChk.classList.add('hud-highlight');
        setTimeout(() => elChk.classList.remove('hud-highlight'), 800);
    }
    elChk.textContent = etat.checksum;
    dernierChecksum = etat.checksum;
}
