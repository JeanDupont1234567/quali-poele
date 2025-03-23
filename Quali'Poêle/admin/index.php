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

// Récupérer les statistiques de base
try {
    // Nombre total de clients
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clients");
    $stmt->execute();
    $clientsCount = $stmt->fetchColumn();
    
    // Nombre total de projets
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM projets");
    $stmt->execute();
    $projetsCount = $stmt->fetchColumn();
    
    // Nombre de projets par statut
    $stmt = $pdo->prepare("SELECT statut, COUNT(*) as count FROM projets GROUP BY statut");
    $stmt->execute();
    $projetsByStatus = $stmt->fetchAll();
    
    // Nombre de messages non lus
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE lu = 0 AND expediteur = 'client'");
    $stmt->execute();
    $messagesNonLus = $stmt->fetchColumn();
    
    // Projets récents
    $stmt = $pdo->prepare("
        SELECT p.id, p.titre, p.statut, p.date_creation, c.nom, c.prenom
        FROM projets p
        JOIN clients c ON p.client_id = c.id
        ORDER BY p.date_creation DESC
        LIMIT 5
    ");
    $stmt->execute();
    $projetsRecents = $stmt->fetchAll();
    
    // Messages récents
    $stmt = $pdo->prepare("
        SELECT m.id, m.sujet, m.date_creation, m.lu, c.nom, c.prenom, c.id as client_id
        FROM messages m
        JOIN clients c ON m.client_id = c.id
        WHERE m.expediteur = 'client'
        ORDER BY m.date_creation DESC
        LIMIT 5
    ");
    $stmt->execute();
    $messagesRecents = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des données.";
}

// Fonction pour traduire le statut en français et obtenir la classe CSS correspondante
function getStatusInfo($status) {
    $statusInfo = [
        'new' => ['label' => 'Nouveau', 'class' => 'status-new'],
        'pending' => ['label' => 'En attente', 'class' => 'status-pending'],
        'in_progress' => ['label' => 'En cours', 'class' => 'status-progress'],
        'completed' => ['label' => 'Terminé', 'class' => 'status-completed'],
        'canceled' => ['label' => 'Annulé', 'class' => 'status-canceled']
    ];
    
    return isset($statusInfo[$status]) ? $statusInfo[$status] : ['label' => $status, 'class' => ''];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'">
    <title>Tableau de bord - Administration Quali'Poêle</title>
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
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        .stat-label {
            font-size: 16px;
            color: var(--text-color);
        }
        .recent-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .recent-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .recent-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
            color: var(--text-color);
        }
        .list-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .list-item:last-child {
            border-bottom: none;
        }
        .list-item-content {
            flex: 1;
        }
        .list-item-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .list-item-detail {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }
        .list-item-actions {
            display: flex;
            gap: 10px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 4px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-new {
            background-color: rgba(33, 150, 243, 0.2);
            color: #2196F3;
        }
        .status-pending {
            background-color: rgba(255, 152, 0, 0.2);
            color: #FF9800;
        }
        .status-progress {
            background-color: rgba(156, 39, 176, 0.2);
            color: #9C27B0;
        }
        .status-completed {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        .status-canceled {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
        }
        .unread-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #FF9800;
            display: inline-block;
            margin-right: 5px;
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
            <h1 class="admin-title">Tableau de bord</h1>
            
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $clientsCount ?></div>
                    <div class="stat-label">Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $projetsCount ?></div>
                    <div class="stat-label">Projets</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $messagesNonLus ?></div>
                    <div class="stat-label">Messages non lus</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php
                        $completed = 0;
                        foreach ($projetsByStatus as $status) {
                            if ($status['statut'] === 'completed') {
                                $completed = $status['count'];
                                break;
                            }
                        }
                        echo $completed;
                        ?>
                    </div>
                    <div class="stat-label">Projets terminés</div>
                </div>
            </div>
            
            <div class="recent-grid">
                <div class="recent-card">
                    <h2>Projets récents</h2>
                    
                    <?php if (empty($projetsRecents)): ?>
                        <p>Aucun projet récent.</p>
                    <?php else: ?>
                        <?php foreach ($projetsRecents as $projet): ?>
                            <div class="list-item">
                                <div class="list-item-content">
                                    <div class="list-item-title"><?= htmlspecialchars($projet['titre'] ?: 'Projet #' . $projet['id']) ?></div>
                                    <div class="list-item-detail">
                                        <?= htmlspecialchars($projet['prenom'] . ' ' . $projet['nom']) ?> - 
                                        <?= format_date($projet['date_creation']) ?>
                                    </div>
                                </div>
                                <div class="list-item-actions">
                                    <?php $statusInfo = getStatusInfo($projet['statut']); ?>
                                    <span class="status-badge <?= $statusInfo['class'] ?>"><?= htmlspecialchars($statusInfo['label']) ?></span>
                                    <a href="projet_detail.php?id=<?= $projet['id'] ?>" class="btn btn-sm btn-primary">Voir</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <a href="projets.php" style="color: var(--secondary-color); text-decoration: none;">Voir tous les projets →</a>
                    </div>
                </div>
                
                <div class="recent-card">
                    <h2>Messages récents</h2>
                    
                    <?php if (empty($messagesRecents)): ?>
                        <p>Aucun message récent.</p>
                    <?php else: ?>
                        <?php foreach ($messagesRecents as $message): ?>
                            <div class="list-item">
                                <div class="list-item-content">
                                    <div class="list-item-title">
                                        <?php if (!$message['lu']): ?>
                                            <span class="unread-indicator"></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($message['sujet']) ?>
                                    </div>
                                    <div class="list-item-detail">
                                        <?= htmlspecialchars($message['prenom'] . ' ' . $message['nom']) ?> - 
                                        <?= format_date($message['date_creation']) ?>
                                    </div>
                                </div>
                                <div class="list-item-actions">
                                    <a href="envoyer_message.php?client_id=<?= $message['client_id'] ?>&reply_to=<?= $message['id'] ?>" class="btn btn-sm btn-primary">Répondre</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <a href="messages.php" style="color: var(--secondary-color); text-decoration: none;">Voir tous les messages →</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 