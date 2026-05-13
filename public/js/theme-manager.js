/**
 * theme-manager.js
 * WBS 3.0 : Logique de bascule d'environnement visuel (Dark/Light mode).
 */

document.addEventListener('DOMContentLoaded', () => {
    const btnToggle = document.getElementById('btn-theme-toggle');
    const iconSpan = btnToggle ? btnToggle.querySelector('.theme-icon') : null;

    if (!btnToggle) return;

    // Fonction de mise à jour de l'icône selon le thème actuel
    const updateIcon = (theme) => {
        if (iconSpan) {
            iconSpan.textContent = theme === 'dark' ? '☀️' : '🌙';
        }
    };

    // Initialisation
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    updateIcon(currentTheme);

    btnToggle.addEventListener('click', () => {
        let theme = document.documentElement.getAttribute('data-theme');
        let newTheme = 'light';

        if (theme !== 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            newTheme = 'dark';
        } else {
            document.documentElement.removeAttribute('data-theme');
        }

        // Sauvegarde de l'état
        localStorage.setItem('theme', newTheme);
        updateIcon(newTheme);

        // Émission d'un événement global pour notifier les autres composants (ex: moteur-visuel)
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: newTheme } }));
    });
});
