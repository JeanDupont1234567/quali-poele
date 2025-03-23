<?php
// Définir une constante pour autoriser l'accès aux fichiers de configuration
define('SECURE_ACCESS', true);

// Charger l'initialisation
require_once 'init.php';

// Vérifier si l'utilisateur est connecté
if (!$auth->verify_session()) {
    header('Location: connexion.php');
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Initialiser les variables
$success = false;
$error = null;

// Traitement du formulaire de modification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Protection CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité, veuillez réessayer";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Vérifier que tous les champs sont remplis
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Tous les champs sont obligatoires";
        } else if (strlen($new_password) < 8) {
            $error = "Le nouveau mot de passe doit contenir au moins 8 caractères";
        } else if ($new_password !== $confirm_password) {
            $error = "Les nouveaux mots de passe ne correspondent pas";
        } else {
            try {
                // Récupérer le mot de passe actuel
                $stmt = $pdo->prepare("SELECT password FROM clients WHERE id = ?");
                $stmt->execute([$user_id]);
                $current_hash = $stmt->fetchColumn();
                
                // Vérifier que le mot de passe actuel est correct
                if (!verify_password($current_password, $current_hash)) {
                    $error = "Le mot de passe actuel est incorrect";
                } else {
                    // Hasher le nouveau mot de passe
                    $new_hash = hash_password($new_password);
                    
                    // Mettre à jour le mot de passe
                    $stmt = $pdo->prepare("UPDATE clients SET password = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $user_id]);
                    
                    // Journaliser le changement de mot de passe
                    log_access_attempt($pdo, 'password_change', true);
                    
                    $success = "Votre mot de passe a été modifié avec succès";
                    
                    // Réinitialiser les données du formulaire
                    $current_password = $new_password = $confirm_password = '';
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la modification du mot de passe: " . $e->getMessage());
                $error = "Une erreur est survenue lors de la modification du mot de passe";
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
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'">
    <title>Modifier mon mot de passe - Quali'Poêle</title>
    <link rel="stylesheet" href="../css/landing.css">
    <link rel="stylesheet" href="../css/qualification.css">
    <style>
        .dashboard-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .password-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background-color: rgba(0,0,0,0.2);
            color: var(--text-color);
            font-size: 16px;
        }
        .password-requirements {
            margin-top: 5px;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
        }
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: var(--secondary-color);
            color: var(--dark-color);
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: var(--text-color);
            text-decoration: none;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .back-link:hover {
            opacity: 0.8;
        }
        .back-link svg {
            margin-right: 5px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .alert-error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        .required-mark {
            color: #F44336;
            margin-left: 3px;
        }
        .security-tips {
            margin-top: 30px;
            padding: 15px;
            border-radius: 8px;
            background-color: rgba(255,255,255,0.05);
        }
        .security-tips h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .security-tips ul {
            margin: 0;
            padding-left: 20px;
        }
        .security-tips li {
            margin-bottom: 5px;
        }
    </style>
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
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="main-title">Modifier mon mot de passe</h1>
                <a href="mon-compte.php" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                    </svg>
                    Retour au tableau de bord
                </a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="password-card">
                <form method="post" action="" id="password-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">Mot de passe actuel <span class="required-mark">*</span></label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required autocomplete="current-password">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">Nouveau mot de passe <span class="required-mark">*</span></label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required minlength="8" autocomplete="new-password">
                        <div class="password-requirements">Le mot de passe doit contenir au moins 8 caractères.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe <span class="required-mark">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="8" autocomplete="new-password">
                    </div>
                    
                    <div class="form-actions">
                        <a href="mon-compte.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">Modifier mon mot de passe</button>
                    </div>
                </form>
                
                <div class="security-tips">
                    <h3>Conseils de sécurité</h3>
                    <ul>
                        <li>Utilisez un mot de passe unique que vous n'utilisez pas sur d'autres sites.</li>
                        <li>Incluez des lettres majuscules, minuscules, des chiffres et des caractères spéciaux.</li>
                        <li>Évitez d'utiliser des informations personnelles facilement devinables.</li>
                        <li>Ne partagez jamais votre mot de passe avec d'autres personnes.</li>
                        <li>Changez régulièrement votre mot de passe pour une meilleure sécurité.</li>
                    </ul>
                </div>
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
                            <li><a href="mon-compte.php">Espace client</a></li>
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validation de formulaire côté client
        const form = document.getElementById('password-form');
        
        form.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Vérifier la longueur minimum
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Le nouveau mot de passe doit contenir au moins 8 caractères.');
                return;
            }
            
            // Vérifier la correspondance
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Les nouveaux mots de passe ne correspondent pas.');
                return;
            }
        });
    });
    </script>
</body>
</html> 