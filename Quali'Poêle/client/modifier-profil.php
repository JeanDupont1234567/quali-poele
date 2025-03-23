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

try {
    // Récupérer les informations actuelles de l'utilisateur
    $stmt = $pdo->prepare("SELECT prenom, nom, email, telephone, code_postal FROM clients WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }

    // Traitement du formulaire de modification du profil
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Protection CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Erreur de sécurité, veuillez réessayer";
        } else {
            // Récupérer et nettoyer les données du formulaire
            $prenom = trim($_POST['prenom'] ?? '');
            $nom = trim($_POST['nom'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $code_postal = trim($_POST['code_postal'] ?? '');
            
            // Vérifier que tous les champs sont remplis
            if (empty($prenom) || empty($nom) || empty($email) || empty($telephone) || empty($code_postal)) {
                $error = "Tous les champs sont obligatoires";
            } 
            // Vérifier le format de l'email
            else if (!is_valid_email($email)) {
                $error = "Format d'email invalide";
            } 
            // Vérifier le format du numéro de téléphone français
            else if (!is_valid_phone($telephone)) {
                $error = "Format de numéro de téléphone invalide (format français requis)";
            }
            // Vérifier le format du code postal français
            else if (!preg_match('/^[0-9]{5}$/', $code_postal)) {
                $error = "Format de code postal invalide (5 chiffres requis)";
            }
            // Vérifier si l'email existe déjà pour un autre utilisateur
            else if ($email !== $user['email']) {
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error = "Cet email est déjà utilisé par un autre compte";
                }
            }
            
            // Si aucune erreur, mettre à jour le profil
            if (!$error) {
                try {
                    $stmt = $pdo->prepare("UPDATE clients SET prenom = ?, nom = ?, email = ?, telephone = ?, code_postal = ? WHERE id = ?");
                    $stmt->execute([$prenom, $nom, $email, $telephone, $code_postal, $user_id]);
                    
                    // Mettre à jour les informations de session si l'email a changé
                    if ($email !== $user['email']) {
                        $_SESSION['email'] = $email;
                    }
                    
                    // Mettre à jour les variables pour l'affichage du formulaire
                    $user['prenom'] = $prenom;
                    $user['nom'] = $nom;
                    $user['email'] = $email;
                    $user['telephone'] = $telephone;
                    $user['code_postal'] = $code_postal;
                    
                    $success = "Vos informations ont été mises à jour avec succès";
                    
                    // Journaliser la modification du profil
                    log_access_attempt($pdo, 'profile_update', true);
                } catch (PDOException $e) {
                    error_log("Erreur lors de la mise à jour du profil: " . $e->getMessage());
                    $error = "Une erreur est survenue lors de la mise à jour de votre profil";
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors du chargement du profil: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement de votre profil";
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
    <title>Modifier mon profil - Quali'Poêle</title>
    <link rel="stylesheet" href="../css/landing.css">
    <link rel="stylesheet" href="../css/qualification.css">
    <style>
        .dashboard-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .profile-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
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
        .form-hint {
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
                <h1 class="main-title">Modifier mon profil</h1>
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
            
            <div class="profile-card">
                <form method="post" action="" id="profile-form">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prenom" class="form-label">Prénom <span class="required-mark">*</span></label>
                            <input type="text" id="prenom" name="prenom" class="form-input" required 
                                value="<?= htmlspecialchars($user['prenom'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="nom" class="form-label">Nom <span class="required-mark">*</span></label>
                            <input type="text" id="nom" name="nom" class="form-input" required 
                                value="<?= htmlspecialchars($user['nom'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email <span class="required-mark">*</span></label>
                        <input type="email" id="email" name="email" class="form-input" required 
                            value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        <div class="form-hint">Cet email sera utilisé pour vous connecter à votre compte.</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telephone" class="form-label">Téléphone <span class="required-mark">*</span></label>
                            <input type="tel" id="telephone" name="telephone" class="form-input" required 
                                pattern="^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$"
                                value="<?= htmlspecialchars($user['telephone'] ?? '') ?>">
                            <div class="form-hint">Format: 06 12 34 56 78 ou +33 6 12 34 56 78</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="code_postal" class="form-label">Code postal <span class="required-mark">*</span></label>
                            <input type="text" id="code_postal" name="code_postal" class="form-input" required 
                                pattern="[0-9]{5}"
                                value="<?= htmlspecialchars($user['code_postal'] ?? '') ?>">
                            <div class="form-hint">Format: 5 chiffres (ex: 75001)</div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="mon-compte.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
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
        const form = document.getElementById('profile-form');
        
        form.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const telephone = document.getElementById('telephone').value;
            const codePostal = document.getElementById('code_postal').value;
            
            // Validation email
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Format d\'email invalide');
                return;
            }
            
            // Validation téléphone français
            const telRegex = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
            if (!telRegex.test(telephone)) {
                e.preventDefault();
                alert('Format de numéro de téléphone invalide (format français requis)');
                return;
            }
            
            // Validation code postal
            const cpRegex = /^[0-9]{5}$/;
            if (!cpRegex.test(codePostal)) {
                e.preventDefault();
                alert('Format de code postal invalide (5 chiffres requis)');
                return;
            }
        });
    });
    </script>
</body>
</html> 