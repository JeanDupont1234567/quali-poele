<?php
// Définir une constante pour autoriser l'accès aux fichiers de configuration
define('SECURE_ACCESS', true);

// Charger l'initialisation
require_once 'init.php';

// Rediriger si déjà connecté
if ($auth->verify_session()) {
    header('Location: mon-compte.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Protection CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité, veuillez réessayer";
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        // Vérifier si l'IP ou l'email est bloqué
        if ($auth->is_blocked($email)) {
            $error = "Trop de tentatives échouées. Veuillez réessayer plus tard.";
        } else if (!$email || !$password) {
            $error = "Tous les champs sont obligatoires";
        } else {
            // Vérifier les identifiants
            $user = $auth->verify_credentials($email, $password);
            
            if ($user) {
                // Connexion réussie
                $auth->create_user_session($user);
                
                // Journaliser la connexion réussie
                log_access_attempt($pdo, 'login', true);
                
                // Vérifier si l'utilisateur a des projets
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM projets WHERE client_id = ?");
                $stmt->execute([$user['id']]);
                $has_projects = $stmt->fetchColumn() > 0;
                
                // Rediriger vers la qualification si pas de projets, sinon vers le compte
                if (!$has_projects) {
                    header('Location: ../qualification.html?welcome=1');
                } else {
                    header('Location: mon-compte.php');
                }
                exit;
            } else {
                $error = "Email ou mot de passe incorrect";
                
                // Journaliser la tentative échouée
                log_access_attempt($pdo, 'login', false, "Email: $email");
            }
        }
    }
}

// Générer un token CSRF
$_SESSION['csrf_token'] = generate_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'">
    <title>Connexion à votre espace client - Quali'Poêle</title>
    <link rel="stylesheet" href="../css/landing.css">
    <link rel="stylesheet" href="../css/qualification.css">
</head>
<body class="dark-theme">
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <a href="../index.html" class="header-logo">
                    <img src="../img/logo.png" alt="Quali'Poêle">
                </a>
            </div>
        </div>
    </header>
    
    <main>
        <div class="container">
            <div class="qualification-container">
                <h1 class="main-title">Connexion à votre espace client</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="post" class="qualification-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-input" required autocomplete="email">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password">
                        </div>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="submit" class="btn btn-submit">Se connecter</button>
                    </div>
                    
                    <div class="form-links" style="text-align: center; margin-top: 20px;">
                        <p>Pas encore de compte ? <a href="inscription.php" style="color: var(--secondary-color);">Créer un compte</a></p>
                        <p><a href="../qualification.html" style="color: var(--secondary-color);">Demander un devis</a></p>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="../img/logo.png" alt="Quali'Poêle" width="150">
                </div>
                <div class="footer-columns">
                    <div class="footer-column">
                        <h3 class="footer-heading">À propos</h3>
                        <ul class="footer-links">
                            <li><a href="../index.html">Accueil</a></li>
                            <li><a href="../qualification.html">Demander un devis</a></li>
                            <li><a href="connexion.php">Espace client</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3 class="footer-heading">Légal</h3>
                        <ul class="footer-links">
                            <li><a href="../mentions-legales.html">Mentions légales</a></li>
                            <li><a href="../politique-confidentialite.html">Politique de confidentialité</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Quali'Poêle - Tous droits réservés</p>
            </div>
        </div>
    </footer>
    
    <!-- Script pour expiration automatique du formulaire après 10 minutes -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Expire le formulaire après 10 minutes
        setTimeout(function() {
            alert('Votre session de connexion a expiré pour des raisons de sécurité. Veuillez rafraîchir la page.');
            document.querySelector('form').reset();
            document.querySelector('input[name="csrf_token"]').value = '';
        }, 600000); // 10 minutes
    });
    </script>
</body>
</html> 