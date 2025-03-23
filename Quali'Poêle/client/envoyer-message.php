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
$projet_id = null;
$projet_info = null;
$sujet_prefixe = '';

// Vérifier si un projet_id est fourni
if (isset($_GET['projet_id']) && !empty($_GET['projet_id'])) {
    $projet_id = (int)$_GET['projet_id'];
    
    // Vérifier que le projet appartient bien au client connecté
    try {
        $stmt = $pdo->prepare("SELECT id, type_chauffage FROM projets WHERE id = ? AND client_id = ?");
        $stmt->execute([$projet_id, $user_id]);
        $projet_info = $stmt->fetch();
        
        if ($projet_info) {
            // Préfixer le sujet avec le numéro et le type du projet
            $sujet_prefixe = "Projet #" . $projet_info['id'] . " - " . $projet_info['type_chauffage'] . " : ";
        } else {
            // Si le projet n'appartient pas au client ou n'existe pas
            $error = "Le projet spécifié n'existe pas ou ne vous appartient pas.";
            $projet_id = null;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du projet: " . $e->getMessage());
        $error = "Une erreur est survenue lors de la vérification du projet.";
        $projet_id = null;
    }
}

// Traitement du formulaire d'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Protection CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité, veuillez réessayer";
    } else {
        $message_text = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        $sujet = filter_input(INPUT_POST, 'sujet', FILTER_SANITIZE_STRING);
        $projet_reference = filter_input(INPUT_POST, 'projet_id', FILTER_VALIDATE_INT);
        
        if (empty($message_text)) {
            $error = "Le message ne peut pas être vide";
        } else if (empty($sujet)) {
            $error = "Le sujet ne peut pas être vide";
        } else {
            try {
                // Insertion du message dans la base de données
                $stmt = $pdo->prepare("INSERT INTO messages (client_id, sujet, message, expediteur, date_envoi, projet_reference) 
                                      VALUES (?, ?, ?, 'client', NOW(), ?)");
                $stmt->execute([$user_id, $sujet, $message_text, $projet_reference ?: null]);
                
                $success = "Votre message a bien été envoyé";
                
                // Rediriger vers la page des messages après 2 secondes
                header("refresh:2;url=messages.php");
            } catch (PDOException $e) {
                error_log("Erreur lors de l'envoi du message: " . $e->getMessage());
                $error = "Une erreur est survenue lors de l'envoi du message";
            }
        }
    }
}

// Récupérer les projets du client pour le menu déroulant
try {
    $stmt = $pdo->prepare("SELECT id, type_chauffage FROM projets WHERE client_id = ? ORDER BY date_creation DESC");
    $stmt->execute([$user_id]);
    $projets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des projets: " . $e->getMessage());
    $projets = [];
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
    <title>Envoyer un message - Quali'Poêle</title>
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
        .message-card {
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
        textarea.form-input {
            min-height: 200px;
            resize: vertical;
        }
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background-color: rgba(0,0,0,0.2);
            color: var(--text-color);
            font-size: 16px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ffffff' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px 12px;
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
        .project-reference {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: rgba(255,204,51,0.1);
            border: 1px solid rgba(255,204,51,0.3);
        }
        .reference-title {
            font-weight: 500;
            margin-bottom: 5px;
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
                <h1 class="main-title">Envoyer un message</h1>
                <a href="<?= isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : 'mon-compte.php' ?>" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                    </svg>
                    Retour
                </a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="message-card">
                <?php if ($projet_info): ?>
                    <div class="project-reference">
                        <div class="reference-title">Référence au projet :</div>
                        <div>Projet #<?= $projet_info['id'] ?> - <?= htmlspecialchars($projet_info['type_chauffage']) ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <?php if ($projet_id): ?>
                        <input type="hidden" name="projet_id" value="<?= $projet_id ?>">
                    <?php else: ?>
                        <div class="form-group">
                            <label for="projet_id" class="form-label">Concerne un projet (optionnel)</label>
                            <select id="projet_id" name="projet_id" class="form-select">
                                <option value="">Aucun projet spécifique</option>
                                <?php foreach ($projets as $projet): ?>
                                    <option value="<?= $projet['id'] ?>">
                                        Projet #<?= $projet['id'] ?> - <?= htmlspecialchars($projet['type_chauffage']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="sujet" class="form-label">Sujet <span class="required-mark">*</span></label>
                        <input type="text" id="sujet" name="sujet" class="form-input" value="<?= htmlspecialchars($sujet_prefixe) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message" class="form-label">Message <span class="required-mark">*</span></label>
                        <textarea id="message" name="message" class="form-input" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="<?= isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : 'messages.php' ?>" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">Envoyer</button>
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
</body>
</html> 