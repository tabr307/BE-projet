/**
 * application-client.js
 * Gestionnaire des interactions frontend du simulateur réseau.
 * Architecture modulaire pour éviter les conflits entre les vues.
 */

const AppClient = {
    /**
     * Crée un nouveau scénario en base de données.
     * Appelé par le bouton d'ajout sur le tableau de bord.
     */
    /**
     * Crée un nouveau scénario en base de données via une modale DOM Asynchrone.
     */
    async creerScenario() {
        // Encapsulation de la logique UI dans une Promesse
        const demanderNomScenario = () => {
            return new Promise((resolve) => {
                const modal = document.getElementById('modal-saisie-scenario');
                const input = document.getElementById('input-saisie-scenario');
                const btnValider = document.getElementById('btn-valider-saisie-scenario');
                const btnAnnuler = document.getElementById('btn-annuler-saisie-scenario');

                if (!modal) {
                    // Fallback de sécurité si le DOM n'est pas chargé
                    resolve(prompt("Entrez le nom du nouveau scénario :"));
                    return;
                }

                input.value = '';
                modal.classList.remove('hidden');
                input.focus();

                const cleanUp = () => {
                    modal.classList.add('hidden');
                    btnValider.removeEventListener('click', onValider);
                    btnAnnuler.removeEventListener('click', onAnnuler);
                    input.removeEventListener('keypress', onEnter);
                };

                const onValider = () => { cleanUp(); resolve(input.value); };
                const onAnnuler = () => { cleanUp(); resolve(null); };
                const onEnter = (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        onValider();
                    }
                };

                btnValider.addEventListener('click', onValider);
                btnAnnuler.addEventListener('click', onAnnuler);
                input.addEventListener('keypress', onEnter);
            });
        };

        const nom = await demanderNomScenario();

        if (!nom || nom.trim() === "") return;

        try {
            const reponse = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'creer_scenario',
                    nom: nom.trim()
                })
            });

            const resultat = await reponse.json();

            if (reponse.ok && resultat.id) {
                window.location.reload();
            } else {
                alert("Erreur lors de la création : " + (resultat.erreur || "Inconnue"));
            }
        } catch (erreur) {
            console.error("Erreur réseau :", erreur);
            alert("Impossible de contacter le serveur.");
        }
    },

    /**
     * Supprime un scénario après confirmation via modale asynchrone.
     * @param {number} id - L'identifiant du scénario à supprimer.
     */
    async supprimerScenario(id) {
        if (!id) return;

        // Encapsulation de l'attente de confirmation dans une Promesse
        const demanderConfirmation = () => {
            return new Promise((resolve) => {
                const modal = document.getElementById('modal-confirmation-scenario');
                const btnValider = document.getElementById('btn-valider-confirmation-scenario');
                const btnAnnuler = document.getElementById('btn-annuler-confirmation-scenario');

                if (!modal) {
                    // Fallback de sécurité
                    resolve(confirm("Voulez-vous vraiment supprimer ce scénario et tout son contenu ?"));
                    return;
                }

                modal.classList.remove('hidden');

                const cleanUp = () => {
                    modal.classList.add('hidden');
                    btnValider.removeEventListener('click', onValider);
                    btnAnnuler.removeEventListener('click', onAnnuler);
                };

                const onValider = () => { cleanUp(); resolve(true); };
                const onAnnuler = () => { cleanUp(); resolve(false); };

                btnValider.addEventListener('click', onValider);
                btnAnnuler.addEventListener('click', onAnnuler);
            });
        };

        const estConfirme = await demanderConfirmation();
        if (!estConfirme) return;

        try {
            const reponse = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'supprimer_scenario',
                    id: parseInt(id)
                })
            });

            const resultat = await reponse.json();

            if (reponse.ok && resultat.succes) {
                window.location.reload();
            } else {
                alert("Erreur lors de la suppression : " + (resultat.erreur || "Inconnue"));
            }
        } catch (erreur) {
            console.error("Erreur réseau :", erreur);
        }
    },

    /**
     * WBS 5.4.1 - Ouvre l'interface d'édition L3 pour un hôte spécifique.
     * Récupère l'état actuel de l'hôte via l'API REST.
     * @param {number} noeudId - Identifiant du nœud Hôte
     */
    async ouvrirEditeurL3Hote(noeudId) {
        try {
            // Interrogation de l'API REST pour lecture (GET)
            const reponse = await fetch(`api.php?action=get_hote&id=${noeudId}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!reponse.ok) throw new Error("Échec de récupération des données de l'hôte");
            const donneesHote = await reponse.json();

            // Injection des données dans le DOM
            document.getElementById('input-ip-hote').value = donneesHote.ip || '';
            document.getElementById('input-masque-hote').value = donneesHote.masque || '';
            document.getElementById('input-passerelle-hote').value = donneesHote.passerelle || '';

            // Affichage de la modale L3
            const modale = document.getElementById('editeur-hote');
            if (modale) modale.style.display = 'block';

            // Purge et attachement de l'écouteur de soumission pour éviter l'empilement d'événements
            const btnSave = document.getElementById('btn-sauvegarder-hote');
            if (btnSave) {
                const nouveauBtnSave = btnSave.cloneNode(true);
                btnSave.parentNode.replaceChild(nouveauBtnSave, btnSave);
                nouveauBtnSave.addEventListener('click', () => this.soumettreConfigurationHote(noeudId));
            }
        } catch (erreur) {
            console.error("Erreur I/O lors de la lecture des attributs L3 :", erreur);
            alert("Erreur de synchronisation avec la base de données.");
        }
    },

    /**
     * WBS 5.4.1 - Valide et transmet la mutation L3 de l'hôte à l'API REST.
     * @param {number} id - Identifiant de l'hôte
     */
    async soumettreConfigurationHote(id) {
        const ip = document.getElementById('input-ip-hote').value.trim();
        const masque = document.getElementById('input-masque-hote').value.trim();
        const passerelle = document.getElementById('input-passerelle-hote').value.trim();

        // Validation stricte du format IPv4 (Regex INET)
        const regexIPv4 = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;

        if (ip !== '' && !regexIPv4.test(ip)) {
            alert("Violation de contrainte : Format de l'Adresse IP invalide.");
            return;
        }
        if (passerelle !== '' && !regexIPv4.test(passerelle)) {
            alert("Violation de contrainte : Format de la Passerelle invalide.");
            return;
        }

        const payload = {
            action: 'update_hote',
            id: parseInt(id),
            ip: ip,
            masque: masque,
            passerelle: passerelle
        };

        try {
            // Transmission de la transaction au contrôleur frontal
            const reponse = await fetch('api.php', {
                method: 'POST', // Ajuster en PUT si votre contrôleur frontal gère ce verbe HTTP strict
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const resultat = await reponse.json();

            if (reponse.ok && (resultat.succes || resultat.id)) {
                // Fermeture de la modale
                const modale = document.getElementById('editeur-hote');
                if (modale) modale.style.display = 'none';

                // Indication dans la console pour l'intégration Vis.js (WBS 5.5.x)
                console.info(`Attributs L3 mis à jour pour l'entité ID: ${id}`);
            } else {
                alert("Erreur de mutation : " + (resultat.erreur || "Rejet de l'API"));
            }
        } catch (erreur) {
            console.error("Échec de la transaction PUT/POST sur l'API :", erreur);
            alert("Impossible de sauvegarder la configuration.");
        }
    }
};

/**
 * Initialisation des écouteurs d'événements au chargement du DOM.
 */
document.addEventListener('DOMContentLoaded', () => {

    // 1. Gestion du bouton "Nouveau scénario"
    const btnNouveau = document.getElementById('btn-creer-scenario');
    if (btnNouveau) {
        btnNouveau.addEventListener('click', (e) => {
            e.preventDefault();
            AppClient.creerScenario();
        });
    }

    // 2. Gestion de la carte vide "Nouveau scénario"
    const carteNouveau = document.querySelector('.carte-nouveau');
    if (carteNouveau) {
        carteNouveau.addEventListener('click', AppClient.creerScenario);
    }

    // 3. Gestion des boutons "Supprimer"
    const boutonsSupprimer = document.querySelectorAll('.btn-danger');
    boutonsSupprimer.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = e.target.getAttribute('data-id');
            AppClient.supprimerScenario(id);
        });
    });

    // WBS 5.4.1 - Écouteur pour fermer la modale d'édition L3 manuellement
    const btnFermerModaleHote = document.getElementById('btn-annuler-hote');
    if (btnFermerModaleHote) {
        btnFermerModaleHote.addEventListener('click', (e) => {
            e.preventDefault();
            const modale = document.getElementById('editeur-hote');
            if (modale) modale.style.display = 'none';
        });
    }

    /**
     * WBS 5.4.2 - Récupère et affiche la table de routage d'un routeur
     * @param {number} routeurId 
     */
    async function chargerTableRoutage(routeurId) {
        const reponse = await fetch(`api.php?action=get_routes&routeur_id=${routeurId}`);
        const routes = await reponse.json();

        const container = document.getElementById('table-body-routes');
        container.innerHTML = ''; // Reset synchrone du DOM

        routes.forEach(route => {
            container.innerHTML += `
                <tr>
                    <td>${route.destination}</td>
                    <td>${route.masque}</td>
                    <td>${route.passerelle}</td>
                    <td>${route.interface}</td>
                    <td>
                        <button class="btn-suppr-route" onclick="AppClient.supprimerRoute(${route.id}, ${routeurId})">
                            X
                        </button>
                    </td>
                </tr>`;
        });
    }

    /**
     * WBS 5.4.2 - Transmission d'une nouvelle route statique (POST)
     */
    async function ajouterRouteStatique(routeurId) {
        const payload = {
            action: 'add_route',
            routeur_id: routeurId,
            dest: document.getElementById('route-dest').value,
            mask: document.getElementById('route-mask').value,
            gw: document.getElementById('route-gw').value,
            iface: document.getElementById('route-iface').value
        };

        // Validation IPv4 obligatoire (réutiliser la Regex du WBS 5.4.1)
        if (!AppClient.validerIP(payload.dest)) return;

        await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        chargerTableRoutage(routeurId); // Rafraîchissement de l'UI
    }
});