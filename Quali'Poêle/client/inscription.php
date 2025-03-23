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
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Protection CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité, veuillez réessayer";
    } else {
        // Récupération des données du formulaire
        $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
        $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $telephone = filter_input(INPUT_POST, 'telephone', FILTER_SANITIZE_STRING);
        $code_postal = filter_input(INPUT_POST, 'code_postal', FILTER_SANITIZE_STRING);
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // Vérification des données
        if (!$prenom || !$nom || !$email || !$telephone || !$code_postal || !$password || !$password_confirm) {
            $error = "Tous les champs sont obligatoires";
        } else if (!is_valid_email($email)) {
            $error = "L'adresse email n'est pas valide";
        } else if (!is_valid_phone($telephone)) {
            $error = "Le numéro de téléphone n'est pas valide";
        } else if (strlen($password) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères";
        } else if ($password !== $password_confirm) {
            $error = "Les mots de passe ne correspondent pas";
        } else {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT 1 FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn()) {
                $error = "Cette adresse email est déjà utilisée";
            } else {
                // Créer l'utilisateur
                $user_data = [
                    'prenom' => $prenom,
                    'nom' => $nom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'code_postal' => $code_postal,
                    'password' => $password
                ];
                
                $user_id = $auth->register_user($user_data);
                
                if ($user_id) {
                    // Connexion automatique
                    $user = [
                        'id' => $user_id,
                        'prenom' => $prenom,
                        'nom' => $nom,
                        'email' => $email
                    ];
                    
                    $auth->create_user_session($user);
                    
                    // Journaliser l'inscription
                    log_access_attempt($pdo, 'login', true, "Nouvelle inscription");
                    
                    // Vérifier si l'utilisateur a des projets
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projets WHERE client_id = ?");
                    $stmt->execute([$user_id]);
                    $has_projects = $stmt->fetchColumn() > 0;
                    
                    // Rediriger vers la qualification si pas de projets, sinon vers le compte
                    if (!$has_projects) {
                        header('Location: ../qualification.html?welcome=1');
                    } else {
                        header('Location: mon-compte.php?welcome=1');
                    }
                    exit;
                } else {
                    $error = "Une erreur est survenue lors de l'inscription";
                }
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
    <title>Inscription - Quali'Poêle</title>
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
                <h1 class="main-title">Créer un compte</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="post" class="qualification-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" class="form-input" required value="<?= isset($prenom) ? htmlspecialchars($prenom) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" class="form-input" required value="<?= isset($nom) ? htmlspecialchars($nom) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-input" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" autocomplete="email">
                        </div>
                        
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" class="form-input" required value="<?= isset($telephone) ? htmlspecialchars($telephone) : '' ?>" autocomplete="tel">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="code_postal">Code postal</label>
                            <input type="text" id="code_postal" name="code_postal" class="form-input" required value="<?= isset($code_postal) ? htmlspecialchars($code_postal) : '' ?>" maxlength="5" pattern="[0-9]{5}">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Mot de passe (8 caractères minimum)</label>
                            <input type="password" id="password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm">Confirmer le mot de passe</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="display: flex; flex-direction: row; align-items: flex-start;">
                            <input type="checkbox" id="privacy_consent" name="privacy_consent" required style="margin-top: 5px; margin-right: 10px;">
                            <label for="privacy_consent" style="font-weight: normal; font-size: 0.95rem;">J'accepte que mes informations soient utilisées par Quali'Poêle conformément à la <a href="../politique-confidentialite.html" style="color: var(--secondary-color);" target="_blank">politique de confidentialité</a>.</label>
                        </div>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="submit" class="btn btn-submit">Créer mon compte</button>
                    </div>
                    
                    <div class="form-links" style="text-align: center; margin-top: 20px;">
                        <p>Déjà inscrit ? <a href="connexion.php" style="color: var(--secondary-color);">Se connecter</a></p>
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
    
    <!-- Script pour validation du formulaire -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return false;
            }
        });
        
        // Expire le formulaire après 10 minutes
        setTimeout(function() {
            alert('Votre session d\'inscription a expiré pour des raisons de sécurité. Veuillez rafraîchir la page.');
            form.reset();
            document.querySelector('input[name="csrf_token"]').value = '';
        }, 600000); // 10 minutes
    });
    </script>
</body>
</html> 