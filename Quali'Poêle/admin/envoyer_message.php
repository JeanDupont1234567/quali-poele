<?php
// Définir une constante pour autoriser l'accès aux fichiers de configuration
define('SECURE_ACCESS', true);

// Charger la configuration
require_once '../db/config.php';
require_once '../client/functions.php';

// Vérifier si l'administrateur est connecté
session_start();

// Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier si un ID client est fourni
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    header('Location: clients.php');
    exit;
}

$clientId = (int)$_GET['client_id'];
$replyToId = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : null;

// Récupérer les informations du client
try {
    $stmt = $pdo->prepare("SELECT id, prenom, nom, email FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    
    if (!$client) {
        header('Location: clients.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du client: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des informations du client.";
}

// Récupérer le message d'origine si c'est une réponse
$originalMessage = null;
if ($replyToId) {
    try {
        $stmt = $pdo->prepare("SELECT sujet, message FROM messages WHERE id = ? AND client_id = ?");
        $stmt->execute([$replyToId, $clientId]);
        $originalMessage = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du message d'origine: " . $e->getMessage());
    }
}

// Traitement du formulaire d'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sujet = isset($_POST['sujet']) ? sanitize_input($_POST['sujet']) : '';
    $message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';
    
    // Validation basique
    if (empty($sujet)) {
        $errors['sujet'] = "Le sujet est requis.";
    }
    
    if (empty($message)) {
        $errors['message'] = "Le message est requis.";
    }
    
    // Si pas d'erreurs, insérer le message
    if (!isset($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (client_id, sujet, message, expediteur, nom_expediteur, email_expediteur, date_creation, lu)
                VALUES (?, ?, ?, 'admin', ?, ?, NOW(), 0)
            ");
            $stmt->execute([
                $clientId,
                $sujet,
                $message,
                "Administration Quali'Poêle",
                "admin@qualipoele.fr"
            ]);
            
            // Si c'est une réponse, marquer le message d'origine comme lu
            if ($replyToId) {
                $updateStmt = $pdo->prepare("UPDATE messages SET lu = 1 WHERE id = ?");
                $updateStmt->execute([$replyToId]);
            }
            
            // Envoi d'une notification par email au client
            $to = $client['email'];
            $emailSubject = "Nouveau message - Quali'Poêle";
            $emailMessage = "Bonjour " . $client['prenom'] . " " . $client['nom'] . ",\n\n";
            $emailMessage .= "Vous avez reçu un nouveau message de Quali'Poêle.\n\n";
            $emailMessage .= "Sujet: " . $sujet . "\n\n";
            $emailMessage .= "Pour y accéder, connectez-vous à votre espace client: https://www.qualipoele.fr/client/mon-compte.php\n\n";
            $emailMessage .= "Cordialement,\n";
            $emailMessage .= "L'équipe Quali'Poêle";
            
            $headers = "From: Quali'Poêle <contact@qualipoele.fr>\r\n";
            $headers .= "Reply-To: contact@qualipoele.fr\r\n";
            
            // Tentative d'envoi d'email, mais on ne bloque pas si ça échoue
            mail($to, $emailSubject, $emailMessage, $headers);
            
            // Redirection avec un message de succès
            header("Location: messages.php?sent=1");
            exit;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de l'envoi du message: " . $e->getMessage());
            $error = "Une erreur est survenue lors de l'envoi du message.";
        }
    }
}
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
        .admin-header {
            background: var(--primary-color);
            color: var(--dark-color);
            padding: 15px 0;
        }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-menu {
            display: flex;
            gap: 20px;
        }
        .admin-menu a {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
        }
        .admin-title {
            font-size: 24px;
            margin: 20px 0;
        }
        .admin-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background-color: rgba(0,0,0,0.2);
            color: var(--text-color);
            font-size: 16px;
        }
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
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
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .client-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: rgba(255,255,255,0.05);
            border-radius: 8px;
        }
        .client-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .client-email {
            color: var(--secondary-color);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        .original-message {
            margin-top: 20px;
            padding: 15px;
            background-color: rgba(255,255,255,0.05);
            border-radius: 8px;
            border-left: 4px solid var(--secondary-color);
        }
        .original-subject {
            font-weight: 600;
            margin-bottom: 10px;
        }
        .text-danger {
            color: #F44336;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body class="dark-theme">
    <header class="admin-header">
        <div class="admin-container">
            <nav class="admin-nav">
                <a href="index.php" class="admin-logo">
                    <strong>Quali'Poêle</strong> | Administration
                </a>
                <div class="admin-menu">
                    <a href="index.php">Tableau de bord</a>
                    <a href="clients.php">Clients</a>
                    <a href="projets.php">Projets</a>
                    <a href="messages.php">Messages</a>
                    <a href="logout.php">Déconnexion</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="admin-container">
            <h1 class="admin-title">Envoyer un message</h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <div class="client-info">
                    <div class="client-name">Destinataire: <?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></div>
                    <div class="client-email"><?= htmlspecialchars($client['email']) ?></div>
                </div>
                
                <?php if ($originalMessage): ?>
                <div class="original-message">
                    <h3>Message d'origine:</h3>
                    <div class="original-subject">Sujet: <?= htmlspecialchars($originalMessage['sujet']) ?></div>
                    <div><?= nl2br(htmlspecialchars($originalMessage['message'])) ?></div>
                </div>
                <?php endif; ?>
                
                <form action="" method="post">
                    <div class="form-group">
                        <label for="sujet">Sujet</label>
                        <input type="text" id="sujet" name="sujet" class="form-control" value="<?= isset($_POST['sujet']) ? htmlspecialchars($_POST['sujet']) : ($originalMessage ? 'RE: ' . htmlspecialchars($originalMessage['sujet']) : '') ?>">
                        <?php if (isset($errors['sujet'])): ?>
                            <div class="text-danger"><?= htmlspecialchars($errors['sujet']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control"><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                            <div class="text-danger"><?= htmlspecialchars($errors['message']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <a href="<?= $replyToId ? 'messages.php' : 'client_detail.php?id=' . $clientId ?>" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">Envoyer</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html> 