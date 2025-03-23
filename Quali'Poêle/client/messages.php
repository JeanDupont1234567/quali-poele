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

// Traitement du formulaire d'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Protection CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité, veuillez réessayer";
    } else {
        $message_text = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        $sujet = filter_input(INPUT_POST, 'sujet', FILTER_SANITIZE_STRING);
        
        if (empty($message_text)) {
            $error = "Le message ne peut pas être vide";
        } else if (empty($sujet)) {
            $error = "Le sujet ne peut pas être vide";
        } else {
            try {
                // Insertion du message dans la base de données
                $stmt = $pdo->prepare("INSERT INTO messages (client_id, sujet, message, expediteur, date_envoi) 
                                      VALUES (?, ?, ?, 'client', NOW())");
                $stmt->execute([$user_id, $sujet, $message_text]);
                
                $success = "Votre message a bien été envoyé";
                
                // Réinitialiser les variables du formulaire
                $message_text = '';
                $sujet = '';
            } catch (PDOException $e) {
                error_log("Erreur lors de l'envoi du message: " . $e->getMessage());
                $error = "Une erreur est survenue lors de l'envoi du message";
            }
        }
    }
}

try {
    // Récupérer tous les messages du client
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE client_id = ? ORDER BY date_envoi DESC");
    $stmt->execute([$user_id]);
    $messages = $stmt->fetchAll();
    
    // Marquer les messages comme lus
    $stmt = $pdo->prepare("UPDATE messages SET est_lu = 1 WHERE client_id = ? AND expediteur = 'admin' AND est_lu = 0");
    $stmt->execute([$user_id]);
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des messages: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération de vos messages";
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
    <title>Mes messages - Quali'Poêle</title>
    <link rel="stylesheet" href="../css/landing.css">
    <link rel="stylesheet" href="../css/qualification.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .messages-container {
            display: grid;
            grid-template-columns: 1fr 3fr;
            gap: 20px;
        }
        .message-list {
            background: var(--dark-card);
            border-radius: 10px;
            overflow: hidden;
        }
        .message-list-header {
            padding: 15px 20px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-weight: 500;
        }
        .message-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
            transition: all 0.3s;
        }
        .message-item:hover {
            background: rgba(255,255,255,0.05);
        }
        .message-item.active {
            background: rgba(255,204,51,0.1);
            border-left: 3px solid var(--secondary-color);
        }
        .message-item.unread {
            background: rgba(255,204,51,0.05);
        }
        .message-sender {
            font-weight: 500;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }
        .message-preview {
            font-size: 14px;
            color: rgba(255,255,255,0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .message-date {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        .message-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .message-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .message-subject {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .message-info {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
        }
        .message-content {
            padding: 20px;
            flex-grow: 1;
            overflow-y: auto;
            max-height: 400px;
        }
        .message-body {
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .message-form {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .form-group {
            margin-bottom: 15px;
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
            min-height: 150px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-secondary {
            background: var(--secondary-color);
            color: var(--dark-color);
        }
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 50px 20px;
            text-align: center;
            color: rgba(255,255,255,0.6);
        }
        .conversation {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .conversation-bubble {
            max-width: 80%;
            padding: 15px;
            border-radius: 10px;
            position: relative;
        }
        .conversation-bubble.sent {
            align-self: flex-end;
            background-color: rgba(255,204,51,0.15);
            border-bottom-right-radius: 0;
        }
        .conversation-bubble.received {
            align-self: flex-start;
            background-color: rgba(255,255,255,0.05);
            border-bottom-left-radius: 0;
        }
        .bubble-time {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            margin-top: 5px;
            text-align: right;
        }
        .conversation-date-divider {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            text-align: center;
            margin: 15px 0;
            position: relative;
        }
        .conversation-date-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: calc(50% - 50px);
            height: 1px;
            background-color: rgba(255,255,255,0.1);
        }
        .conversation-date-divider::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            width: calc(50% - 50px);
            height: 1px;
            background-color: rgba(255,255,255,0.1);
        }
        
        @media (max-width: 768px) {
            .messages-container {
                grid-template-columns: 1fr;
            }
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
                <h1 class="main-title">Mes messages</h1>
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
            
            <div class="messages-container">
                <div class="message-list">
                    <div class="message-list-header">
                        Conversations
                    </div>
                    
                    <?php if (count($messages) > 0): ?>
                        <?php 
                        // Regrouper les messages par sujet
                        $conversations = [];
                        $subjects = [];
                        
                        foreach ($messages as $message) {
                            $sujet = $message['sujet'];
                            if (!isset($conversations[$sujet])) {
                                $conversations[$sujet] = [];
                                $subjects[$sujet] = [
                                    'date' => $message['date_envoi'],
                                    'est_lu' => $message['est_lu'],
                                    'expediteur' => $message['expediteur']
                                ];
                            }
                            $conversations[$sujet][] = $message;
                        }
                        
                        // Trier les sujets par date (le plus récent d'abord)
                        uasort($subjects, function($a, $b) {
                            return strtotime($b['date']) - strtotime($a['date']);
                        });
                        
                        foreach ($subjects as $sujet => $info):
                            $isUnread = $info['expediteur'] === 'admin' && !$info['est_lu'];
                            $preview = substr($conversations[$sujet][0]['message'], 0, 50) . (strlen($conversations[$sujet][0]['message']) > 50 ? '...' : '');
                        ?>
                            <div class="message-item <?= $isUnread ? 'unread' : '' ?>" onclick="location.href='messages.php?sujet=<?= urlencode($sujet) ?>'">
                                <div class="message-sender">
                                    <span><?= htmlspecialchars($sujet) ?></span>
                                    <?php if ($isUnread): ?>
                                        <span class="badge badge-secondary">Nouveau</span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-preview"><?= htmlspecialchars($preview) ?></div>
                                <div class="message-date"><?= format_date($info['date']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Vous n'avez pas encore de messages</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="message-card">
                    <?php if (isset($_GET['sujet']) && isset($conversations[$_GET['sujet']])): ?>
                        <?php 
                        $selectedSubject = $_GET['sujet'];
                        $conversation = $conversations[$selectedSubject];
                        usort($conversation, function($a, $b) {
                            return strtotime($a['date_envoi']) - strtotime($b['date_envoi']);
                        });
                        ?>
                        <div class="message-header">
                            <div class="message-subject"><?= htmlspecialchars($selectedSubject) ?></div>
                            <div class="message-info">
                                <span>Conversation avec Quali'Poêle</span>
                                <span><?= count($conversation) ?> message<?= count($conversation) > 1 ? 's' : '' ?></span>
                            </div>
                        </div>
                        <div class="message-content">
                            <div class="conversation">
                                <?php
                                $currentDate = null;
                                foreach ($conversation as $message):
                                    $messageDate = date('Y-m-d', strtotime($message['date_envoi']));
                                    if ($currentDate !== $messageDate):
                                        $currentDate = $messageDate;
                                ?>
                                    <div class="conversation-date-divider">
                                        <?= date('d/m/Y', strtotime($message['date_envoi'])) ?>
                                    </div>
                                <?php endif; ?>
                                    <div class="conversation-bubble <?= $message['expediteur'] === 'client' ? 'sent' : 'received' ?>">
                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                        <div class="bubble-time">
                                            <?= date('H:i', strtotime($message['date_envoi'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="message-form">
                            <form method="post" action="messages.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="sujet" value="<?= htmlspecialchars($selectedSubject) ?>">
                                
                                <div class="form-group">
                                    <label for="message" class="form-label">Répondre</label>
                                    <textarea id="message" name="message" class="form-input" required></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Envoyer</button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="message-form" style="height: 100%;">
                            <form method="post" action="messages.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <h2 style="margin-bottom: 20px;">Nouveau message</h2>
                                
                                <div class="form-group">
                                    <label for="sujet" class="form-label">Sujet</label>
                                    <input type="text" id="sujet" name="sujet" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea id="message" name="message" class="form-input" required></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Envoyer</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
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
</body>
</html> 