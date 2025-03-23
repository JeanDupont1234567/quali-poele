<?php
// Définir une constante pour autoriser l'accès aux fichiers de configuration
define('SECURE_ACCESS', true);

// Charger la configuration
require_once '../db/config.php';
require_once '../client/functions.php';

// Démarrer la session
session_start();

// Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Initialiser les variables
$error = '';
$email = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le jeton CSRF
    $csrf = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
    
    if (!$csrf || $csrf !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        // Récupérer et nettoyer les données du formulaire
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
        
        // Validation simple
        if (empty($email) || empty($password)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            try {
                // Requête pour vérifier si l'administrateur existe
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();
                
                if ($admin && password_verify($password, $admin['password'])) {
                    // Authentification réussie
                    
                    // Régénérer l'ID de session pour éviter les attaques par fixation de session
                    session_regenerate_id(true);
                    
                    // Stocker les informations de l'administrateur en session
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_nom'] = $admin['nom'];
                    
                    // Rediriger vers le tableau de bord
                    header('Location: index.php');
                    exit;
                } else {
                    // Authentification échouée
                    $error = "Email ou mot de passe incorrect.";
                    
                    // Journal des tentatives de connexion échouées
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $stmt = $pdo->prepare("INSERT INTO access_logs (type, email, ip_address, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute(['admin_login', $email, $ip, 'failed']);
                }
            } catch (PDOException $e) {
                error_log("Erreur de connexion admin: " . $e->getMessage());
                $error = "Une erreur est survenue. Veuillez réessayer plus tard.";
            }
        }
    }
}

// Générer un jeton CSRF
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'">
    <title>Connexion - Administration Quali'Poêle</title>
    <link rel="stylesheet" href="../css/landing.css">
    <link rel="stylesheet" href="../css/qualification.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: var(--dark-card);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-title {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary-color);
        }
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-label {
            font-weight: 500;
            color: var(--text-color);
        }
        .form-input {
            padding: 12px;
            border-radius: 5px;
            background-color: var(--dark-color);
            color: var(--text-color);
            border: 1px solid rgba(255,255,255,0.2);
            width: 100%;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--secondary-color);
        }
        .login-btn {
            padding: 12px;
            border-radius: 5px;
            background-color: var(--secondary-color);
            color: var(--dark-color);
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .login-btn:hover {
            opacity: 0.9;
        }
        .login-error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.5);
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-logo img {
            height: 60px;
        }
    </style>
</head>
<body class="dark-theme">
    <div class="login-container">
        <div class="login-logo">
            <h1><strong>Quali'Poêle</strong> | Admin</h1>
        </div>
        
        <h2 class="login-title">Connexion à l'administration</h2>
        
        <?php if (!empty($error)): ?>
            <div class="login-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form class="login-form" method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>
            
            <button type="submit" class="login-btn">Se connecter</button>
        </form>
    </div>
</body>
</html> 