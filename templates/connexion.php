<div class="auth-container">
    <div class="auth-card">
        <h1>Bienvenue</h1>
        <p class="auth-subtitle">Connectez-vous à votre compte ou créez-en un.</p>

        <!-- Système d'onglets pour basculer entre Login et Register -->
        <div class="auth-tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Connexion</button>
            <button class="tab-btn" onclick="switchTab('register')">Inscription</button>
        </div>

        <!-- Gestion dynamique des messages d'alerte -->
        <?php if (isset($_GET['erreur'])): ?>
            <div class="alerte-erreur">
                <?php 
                    switch($_GET['erreur']) {
                        case 'auth': echo "Identifiants invalides. Veuillez réessayer."; break;
                        case 'existe': echo "Ce nom d'utilisateur est déjà utilisé."; break;
                        case 'reg': echo "Erreur lors de l'inscription. Réessayez plus tard."; break;
                        default: echo "Une erreur est survenue.";
                    }
                ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de Connexion -->
        <form id="login-form" action="api.php?action=login" method="POST" class="auth-form">
            <div class="form-group">
                <label for="login-username">Nom d'utilisateur</label>
                <input type="text" id="login-username" name="username" placeholder="votre_pseudo" required autofocus>
            </div>
            <div class="form-group">
                <label for="login-password">Mot de passe</label>
                <input type="password" id="login-password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-principal full-width">Se connecter</button>
        </form>

        <!-- Formulaire d'Inscription (masqué par défaut via la classe .hidden) -->
        <form id="register-form" action="api.php?action=register" method="POST" class="auth-form hidden">
            <div class="form-group">
                <label for="reg-username">Choisir un pseudo</label>
                <input type="text" id="reg-username" name="username" placeholder="votre_pseudo" required>
            </div>
            <div class="form-group">
                <label for="reg-password">Créer un mot de passe</label>
                <input type="password" id="reg-password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-principal full-width">Créer mon compte</button>
        </form>
    </div>
</div>

<script>
/**
 * Alterne l'affichage entre le formulaire de connexion et d'inscription.
 * @param {string} type - 'login' ou 'register'
 */
function switchTab(type) {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const tabs = document.querySelectorAll('.tab-btn');

    if (type === 'login') {
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
        tabs[0].classList.add('active');
        tabs[1].classList.remove('active');
    } else {
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        tabs[0].classList.remove('active');
        tabs[1].classList.add('active');
    }
}
</script>