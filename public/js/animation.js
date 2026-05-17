/**
 * public/js/animation.js
 * Moteur d'Animation de Simulation IP (WBS 5.0)
 * Gère l'interaction UI, l'appel à l'API de simulation, et l'animation vis.js.
 */

// ============================================================
// 1. GESTION DE LA MODALE ET DU LANCEMENT
// ============================================================

/**
 * Ouvre la modale de simulation et peuple les listes déroulantes
 * avec les hôtes disponibles dans le scénario courant.
 */
async function lancerSimulation() {
    const hotes = donneesScenario.hotes || [];

    // Minimum 2 hôtes requis pour simuler un trajet source → destination
    if (hotes.length < 2) {
        afficherAlerte("Vous devez configurer au moins deux hôtes pour simuler un trajet.");
        return;
    }

    const selectSource = document.getElementById('sim-source');
    const selectDest   = document.getElementById('sim-dest');
    
    // Réinitialise les listes avant de les repeupler
    selectSource.innerHTML = '';
    selectDest.innerHTML   = '';

    // Génère une option par hôte, avec son IP en attribut data pour récupération ultérieure
    hotes.forEach(h => {
        const hasIP     = h.adresse_ip ? `(${h.adresse_ip})` : '(Pas d\'IP)';
        const optionHTML = `<option value="${h.id}" data-ip="${h.adresse_ip || ''}">${h.nom} ${hasIP}</option>`;
        selectSource.innerHTML += optionHTML;
        selectDest.innerHTML   += optionHTML;
    });

    // Par défaut, source = premier hôte, dest = deuxième hôte 
    if (selectDest.options.length > 1) {
        selectDest.selectedIndex = 1;
    }

    // Affiche la modale
    document.getElementById('modal-simulation').classList.remove('hidden');
}

/** Ferme la modale de simulation sans lancer de simulation. */
function fermerModalSimulation() {
    document.getElementById('modal-simulation').classList.add('hidden');
}

// États globaux de l'animation 
let animationEnCours    = false; // Vrai si une animation tourne actuellement
let simulationStoppee   = false; // Vrai si l'utilisateur a cliqué sur Stop
let timeoutFinSimulation = null; // Référence au setTimeout de nettoyage final

// Gestion de la vitesse de lecture
const VITESSES          = [1, 2, 4];  // Multiplicateurs disponibles (x1, x2, x4)
let indexVitesse        = 0;
let vitesseMultiplicateur = 1;

/**
 * Cycle entre les vitesses disponibles (x1 -> x2 -> x4 -> x1…)
 * et met à jour le texte du bouton en conséquence.
 */
function cyclerVitesse() {
    indexVitesse         = (indexVitesse + 1) % VITESSES.length;
    vitesseMultiplicateur = VITESSES[indexVitesse];
    const btn = document.getElementById('btn-vitesse');
    if (btn) btn.textContent = 'x' + vitesseMultiplicateur;
}

/**
 * Active ou désactive les boutons Simuler / Stop selon l'état de la simulation.
 * @param {boolean} simulationActive  true = simulation en cours
 */
function mettreAJourBoutons(simulationActive) {
    const btnSimuler = document.getElementById('btn-lancer-simulation');
    const btnStop    = document.getElementById('btn-stop-simulation');

    if (btnSimuler) {
        btnSimuler.disabled    = simulationActive;
        btnSimuler.style.opacity = simulationActive ? '0.5' : '1';
        btnSimuler.style.cursor  = simulationActive ? 'not-allowed' : 'pointer';
    }
    if (btnStop) {
        btnStop.disabled    = !simulationActive;
        btnStop.style.opacity = simulationActive ? '1' : '0.5';
        btnStop.style.cursor  = simulationActive ? 'pointer' : 'not-allowed';
    }
}

/**
 * Réinitialise proprement l'état après une simulation :
 * annule le timeout, masque le HUD, supprime l'enveloppe animée, réactive les boutons.
 */
function nettoyerSimulation() {
    if (timeoutFinSimulation) {
        clearTimeout(timeoutFinSimulation); // Annule la disparition automatique si déjà planifiée
        timeoutFinSimulation = null;
    }

    const hud = document.getElementById('hud-simulation');
    if (hud) hud.classList.add('hidden'); // Masque le HUD d'en-tête IP

    if (enveloppeDOM) {
        enveloppeDOM.remove(); // Supprime l'élément DOM de l'enveloppe animée
        enveloppeDOM = null;
    }

    animationEnCours = false;
    mettreAJourBoutons(false); // Réactive le bouton Simuler
}

/** Arrête immédiatement la simulation en cours si elle existe. */
function arreterSimulation() {
    if (!animationEnCours) return;
    simulationStoppee = true; // Signal d'arrêt lu par la boucle d'animation
    nettoyerSimulation();
}

/**
 * Point d'entrée principal : récupère les paramètres de la modale,
 * appelle l'API PHP de routage, puis lance l'animation avec la trace reçue.
 */
async function executerSimulation() {
    if (animationEnCours) return; // Empêche le double-clic

    const selectSource      = document.getElementById('sim-source');
    const selectDest        = document.getElementById('sim-dest');
    const idSource          = parseInt(selectSource.value, 10);
    const selectedDestOption = selectDest.options[selectDest.selectedIndex];
    const ipDest            = selectedDestOption.getAttribute('data-ip'); // IP stockée en data-attribute

    if (!ipDest) {
        afficherAlerte("L'hôte de destination n'a pas d'adresse IP valide.");
        return;
    }

    fermerModalSimulation();

    // Passe en état "simulation active"
    animationEnCours  = true;
    simulationStoppee = false;
    mettreAJourBoutons(true);

    try {
        // Appel POST vers api.php — le backend PHP exécute l'algorithme de routage
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action:      'simuler_routage',
                scenario_id: CURRENT_SCENARIO_ID,
                id_source:   idSource,
                ip_dest:     ipDest
            })
        });

        if (simulationStoppee) return; // L'utilisateur a stoppé pendant la requête réseau

        const data = await response.json();

        // Le backend retourne toujours 'succes' ou 'erreur' avec une trace
        if (data.statut === 'succes' || data.statut === 'erreur') {
            // En cas d'erreur réseau, le message est affiché à la fin de l'animation
            await demarrerAnimation(
                data.trace,
                data.statut === 'erreur' ? data.message : "Arrivée à destination !"
            );
        } else {
            afficherAlerte("Erreur inattendue : " + JSON.stringify(data));
            nettoyerSimulation();
        }

    } catch (error) {
        console.error(error);
        if (!simulationStoppee) {
            afficherAlerte("Impossible de contacter le moteur de routage.");
        }
        nettoyerSimulation();
    }
}

// ============================================================
// 2. MOTEUR D'ANIMATION (VIS.JS DOM)
// ============================================================

let enveloppeDOM = null; // Élément DOM représentant le datagramme animé sur le graphe

/**
 * Enrichit la trace L3 (couche réseau) avec les switchs intermédiaires (couche 2)
 * pour une animation visuellement fidèle à la topologie, puis anime le déplacement
 * de l'enveloppe nœud par nœud sur le graphe vis.js.
 *
 * @param {Array}  traceInitiale  Tableau de sauts retourné par le backend PHP
 * @param {string} messageFinal   Message à afficher à la fin de l'animation
 */
async function demarrerAnimation(traceInitiale, messageFinal) {
    if (!traceInitiale || traceInitiale.length === 0) return;

    // INJECTION LAYER 2 (VISUELLE) 
    // Le backend ne retourne que les sauts L3 (Hôtes et Routeurs).
    // On insère ici les switchs intermédiaires visibles dans le graphe vis.js,
    // en cherchant si un switch est partagé entre deux nœuds consécutifs.
    let trace = [];
    for (let i = 0; i < traceInitiale.length; i++) {
        trace.push(traceInitiale[i]); // Ajoute le saut L3 courant

        if (i < traceInitiale.length - 1) {
            let nA = traceInitiale[i];
            let nB = traceInitiale[i + 1];

            // Récupère les liaisons switch-interface et switch-hôte du scénario
            let liaisons_is = donneesScenario.liaisons_is || []; // Interface routeur <-> Switch
            let liaisons_hs = donneesScenario.liaisons_hs || []; // Hôte <-> Switch

            // Recherche des switchs connectés à nA via le graphe vis.js 
            // On interroge directement les arêtes vis.js plutôt que la BDD
            // pour rester cohérent avec le rendu visuel affiché
            let edgeTrouve = network.body.data.edges.get({
                filter: function (item) {
                    return (item.from === `${nA.type_noeud}s_${nA.id_noeud}` && item.to.startsWith('switchs_')) ||
                           (item.to   === `${nA.type_noeud}s_${nA.id_noeud}` && item.from.startsWith('switchs_'));
                }
            });

            // Recherche des switchs connectés à nB 
            let edgeB = network.body.data.edges.get({
                filter: function (item) {
                    return (item.from === `${nB.type_noeud}s_${nB.id_noeud}` && item.to.startsWith('switchs_')) ||
                           (item.to   === `${nB.type_noeud}s_${nB.id_noeud}` && item.from.startsWith('switchs_'));
                }
            });

            // Extrait les IDs de switchs de chaque côté
            let swA = edgeTrouve.map(e => e.from.startsWith('switchs_') ? e.from : e.to);
            let swB = edgeB.map(e => e.from.startsWith('switchs_') ? e.from : e.to);

            // Cherche un switch commun entre nA et nB (switch partagé = switch intermédiaire)
            let swCommun = swA.find(s => swB.includes(s));

            if (swCommun) {
                // Insère le switch comme saut intermédiaire dans la trace visuelle
                let sId = parseInt(swCommun.split('_')[1], 10);
                trace.push({
                    type_noeud:      'switch',
                    id_noeud:        sId,
                    etat_datagramme: nA.etat_datagramme // Conserve l'état du datagramme courant
                });
            }
        }
    }

    // AFFICHAGE DU HUD 
    const hud = document.getElementById('hud-simulation');
    hud.classList.remove('hidden');

    // Réinitialise le message du HUD
    if (enveloppeDOM) enveloppeDOM.remove();
    document.getElementById('hud-message').textContent = "";
    document.getElementById('hud-message').style.color = "var(--primaire)";

    // Crée l'élément DOM de l'enveloppe et l'injecte dans le canvas réseau
    enveloppeDOM           = document.createElement('div');
    enveloppeDOM.className = 'datagram-envelope';
    enveloppeDOM.textContent = '✉️';
    document.getElementById('network-canvas').appendChild(enveloppeDOM);

    // Durée d'un saut en ms, inversement proportionnelle au multiplicateur de vitesse
    const DELAI_SAUT_MS = 1500 / vitesseMultiplicateur;
    enveloppeDOM.style.transition = `left ${DELAI_SAUT_MS / 1000}s linear, top ${DELAI_SAUT_MS / 1000}s linear`;

    // BOUCLE PRINCIPALE D'ANIMATION 
    for (let i = 0; i < trace.length; i++) {
        if (simulationStoppee) return; // Arrêt immédiat demandé par l'utilisateur

        const hop = trace[i];

        // Met à jour le HUD avec l'état du datagramme au saut courant
        if (hop.etat_datagramme) {
            mettreAJourHUD(hop.etat_datagramme, hop.hop_index);
        }

        // Bascule l'enveloppe en mode erreur (rouge) dès qu'un paquet ICMP Time Exceeded apparaît
        if (hop.type_paquet === 'icmp_time_exceeded' && !enveloppeDOM.classList.contains('datagram-error')) {
            enveloppeDOM.classList.add('datagram-error');
            enveloppeDOM.textContent = '❌';
            document.getElementById('hud-message').textContent = 'ICMP Time Exceeded — Retour vers la source';
            document.getElementById('hud-message').style.color = "var(--rouge, #ef4444)";
        }

        // Construit l'ID du nœud vis.js correspondant au saut (ex: "routeurs_3", "hotes_1")
        const visNodeId = `${hop.type_noeud}s_${hop.id_noeud}`;

        try {
            // Convertit les coordonnées internes vis.js (espace canvas) en coordonnées DOM (pixels)
            const canvasCoords = network.getPositions([visNodeId])[visNodeId];
            if (canvasCoords) {
                const domCoords = network.canvasToDOM(canvasCoords);

                // Déplace l'enveloppe — la transition CSS crée l'effet de glissement fluide
                enveloppeDOM.style.left = `${domCoords.x}px`;
                enveloppeDOM.style.top  = `${domCoords.y}px`;

                // Déplace également le HUD pour qu'il reste ancré à l'enveloppe
                hud.style.left = `${domCoords.x + 30}px`;
                hud.style.top  = `${domCoords.y - 60}px`;
            }
        } catch (e) {
            console.warn(`Impossible de positionner sur ${visNodeId}`);
        }

        // Attend le délai avant de passer au saut suivant (pause entre deux nœuds)
        if (i < trace.length - 1) {
            await new Promise(r => setTimeout(r, DELAI_SAUT_MS));
        }
    }

    // AFFICHAGE DU STATUT FINAL 

    document.getElementById('hud-message').textContent = messageFinal;

    // Si le message final indique une erreur réseau connue, bascule l'affichage en rouge
    if (messageFinal.includes("Network Unreachable") ||
        messageFinal.includes("Time Exceeded")       ||
        messageFinal.includes("Boucle")) {
        enveloppeDOM.classList.add('datagram-error');
        document.getElementById('hud-message').style.color   = "var(--rouge, #ef4444)";
        document.getElementById('hud-message').textContent   = "Erreur : " + messageFinal;
    }

    // Après 5 secondes, nettoie automatiquement l'animation et réactive le bouton Simuler
    timeoutFinSimulation = setTimeout(() => {
        nettoyerSimulation();
    }, 5000);
}

// ============================================================
// 3. MISE À JOUR DU HUD (En-tête IP dynamique)
// ============================================================

// Valeurs précédentes pour détecter les changements et déclencher le surlignage
let dernierTTL      = -1;
let dernierChecksum = "";

/**
 * Met à jour les champs du HUD avec l'état courant du datagramme.
 * Surligne visuellement les champs TTL et Checksum quand ils changent entre deux sauts.
 *
 * @param {Object} etat      État du datagramme IP (ttl, checksum, src, dest, df, id…)
 * @param {number} hopIndex  Index du saut courant (0 = hôte source, pas de surlignage)
 */
function mettreAJourHUD(etat, hopIndex) {
    const elId  = document.getElementById('hud-id');
    const elDf  = document.getElementById('hud-df');
    const elTtl = document.getElementById('hud-ttl');
    const elChk = document.getElementById('hud-checksum');
    const elSrc = document.getElementById('hud-src');
    const elDst = document.getElementById('hud-dest');

    // Champs statiques pour ce saut
    elId.textContent  = etat.id;
    elDf.textContent  = etat.df ? 'DF=1' : 'DF=0';
    elSrc.textContent = etat.src;
    elDst.textContent = etat.dest;

    // Indique visuellement si le paquet est un ICMP de retour
    if (etat.type_paquet === 'icmp_time_exceeded') {
        elSrc.textContent = etat.src + ' (ICMP)';
    }

    // Surlignage TTL 
    // Déclenché uniquement si la valeur a changé par rapport au saut précédent
    if (hopIndex > 0 && dernierTTL !== -1 && etat.ttl !== dernierTTL) {
        elTtl.classList.add('hud-highlight');
        setTimeout(() => elTtl.classList.remove('hud-highlight'), 800); // Retire la classe après 800ms
    }
    elTtl.textContent = etat.ttl;
    dernierTTL        = etat.ttl; // Mémorise pour comparaison au prochain saut

    // Surlignage Checksum 
    if (hopIndex > 0 && dernierChecksum !== "" && etat.checksum !== dernierChecksum) {
        elChk.classList.add('hud-highlight');
        setTimeout(() => elChk.classList.remove('hud-highlight'), 800);
    }
    elChk.textContent = etat.checksum;
    dernierChecksum   = etat.checksum;
}