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
    async creerScenario() {
        const nom = prompt("Entrez le nom du nouveau scénario :");
        
        // Annulation si le champ est vide ou si l'utilisateur clique sur "Annuler"
        if (!nom || nom.trim() === "") return;

        try {
            const reponse = await fetch('backend/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'creer_scenario', 
                    nom: nom.trim() 
                })
            });

            const resultat = await reponse.json();

            if (reponse.ok && resultat.id) {
                // Succès : on rafraîchit pour voir la nouvelle carte
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
     * Supprime un scénario après confirmation.
     * @param {number} id - L'identifiant du scénario à supprimer.
     */
    async supprimerScenario(id) {
        if (!id || !confirm("Voulez-vous vraiment supprimer ce scénario et tout son contenu ?")) {
            return;
        }

        try {
            const reponse = await fetch('backend/api.php', {
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
    }
};

/**
 * Initialisation des écouteurs d'événements au chargement du DOM.
 */
document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Gestion du bouton "Nouveau scénario" (ID spécifique pour éviter les conflits)
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

    // 3. Gestion des boutons "Supprimer" (Utilisation de data-id pour l'isolation)
    const boutonsSupprimer = document.querySelectorAll('.btn-danger');
    boutonsSupprimer.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            // On récupère l'ID stocké dans l'attribut data-id du bouton
            const id = e.target.getAttribute('data-id');
            AppClient.supprimerScenario(id);
        });
    });
});