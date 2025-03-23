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
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: clients.php');
    exit;
}

$clientId = (int)$_GET['id'];

// Récupérer les informations du client
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
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

// Récupérer les projets du client
try {
    $stmt = $pdo->prepare("SELECT * FROM projets WHERE client_id = ? ORDER BY date_creation DESC");
    $stmt->execute([$clientId]);
    $projets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des projets: " . $e->getMessage());
    $error_projets = "Une erreur est survenue lors de la récupération des projets.";
    $projets = [];
}

// Récupérer les messages du client
try {
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE client_id = ? ORDER BY date_creation DESC");
    $stmt->execute([$clientId]);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des messages: " . $e->getMessage());
    $error_messages = "Une erreur est survenue lors de la récupération des messages.";
    $messages = [];
}

// Compter les messages non lus
$messagesNonLus = 0;
foreach ($messages as $message) {
    if ($message['expediteur'] === 'client' && !$message['lu']) {
        $messagesNonLus++;
    }
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
    <title>Détail client - Quali'Poêle</title>
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
            position: relative;
        }
        .badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: #F44336;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .admin-title {
            font-size: 24px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .admin-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .client-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .client-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .client-info-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
        }
        .client-info-item:last-child {
            border-bottom: none;
        }
        .client-info-label {
            font-weight: 500;
            color: var(--text-color);
            opacity: 0.8;
        }
        .client-info-value {
            font-weight: 500;
            color: var(--secondary-color);
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s;
            font-weight: 500;
            border-bottom: 3px solid transparent;
        }
        .tab.active {
            opacity: 1;
            border-bottom-color: var(--secondary-color);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--secondary-color);
            color: var(--dark-color);
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th, .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .admin-table th {
            background-color: rgba(0,0,0,0.2);
        }
        .admin-table tr:hover {
            background-color: rgba(255,255,255,0.05);
        }
        .action-btn {
            background: none;
            border: none;
            font-size: 14px;
            cursor: pointer;
            margin-right: 10px;
            color: var(--secondary-color);
            text-decoration: none;
        }
        .delete-btn {
            color: #F44336;
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
        .message-details {
            background-color: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            white-space: pre-wrap;
            display: none;
        }
        .expand-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--secondary-color);
            text-decoration: underline;
            padding: 0;
            margin: 0;
            font-size: 14px;
        }
        .message-unread {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #F44336;
            border-radius: 50%;
            margin-right: 5px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: var(--dark-card);
            border-radius: 10px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
        }
        .modal-title {
            margin-top: 0;
            color: var(--secondary-color);
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        .modal-btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            border: none;
        }
        .modal-btn-cancel {
            background-color: rgba(255,255,255,0.1);
            color: var(--text-color);
        }
        .modal-btn-delete {
            background-color: #F44336;
            color: white;
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
                    <a href="messages.php">
                        Messages
                        <?php if ($messagesNonLus > 0): ?>
                            <span class="badge"><?= $messagesNonLus ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php">Déconnexion</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="admin-container">
            <div class="admin-title">
                <h1>Détail du client</h1>
                <div>
                    <a href="envoyer_message.php?client_id=<?= $clientId ?>" class="btn btn-primary">Envoyer un message</a>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="client-grid">
                <!-- Informations personnelles -->
                <div class="admin-card">
                    <h2>Informations personnelles</h2>
                    <ul class="client-info-list">
                        <li class="client-info-item">
                            <span class="client-info-label">Nom</span>
                            <span class="client-info-value"><?= htmlspecialchars($client['nom']) ?></span>
                        </li>
                        <li class="client-info-item">
                            <span class="client-info-label">Prénom</span>
                            <span class="client-info-value"><?= htmlspecialchars($client['prenom']) ?></span>
                        </li>
                        <li class="client-info-item">
                            <span class="client-info-label">Email</span>
                            <span class="client-info-value"><?= htmlspecialchars($client['email']) ?></span>
                        </li>
                        <li class="client-info-item">
                            <span class="client-info-label">Téléphone</span>
                            <span class="client-info-value"><?= htmlspecialchars($client['telephone']) ?></span>
                        </li>
                        <li class="client-info-item">
                            <span class="client-info-label">Code postal</span>
                            <span class="client-info-value"><?= htmlspecialchars($client['code_postal']) ?></span>
                        </li>
                        <li class="client-info-item">
                            <span class="client-info-label">Date d'inscription</span>
                            <span class="client-info-value"><?= format_date($client['date_inscription']) ?></span>
                        </li>
                    </ul>
                </div>
                
                <!-- Résumé -->
                <div class="admin-card">
                    <h2>Résumé</h2>
                    <ul class="client-info-list">
                        <li class="client-info-item">
                            <span class="client-info-label">Nombre de projets</span>
                            <span class="client-info-value"><?= count($projets) ?></span>
                        </li>
                        <li class="client-info-item">
                            <span class="client-info-label">Messages non lus</span>
                            <span class="client-info-value"><?= $messagesNonLus ?></span>
                        </li>
                        <li class="client-info-item">
                            <span class="client-info-label">Total des messages</span>
                            <span class="client-info-value"><?= count($messages) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Onglets pour les projets et messages -->
            <div class="admin-card">
                <div class="tabs">
                    <div class="tab active" data-tab="projets">Projets</div>
                    <div class="tab" data-tab="messages">Messages</div>
                </div>
                
                <!-- Contenu des projets -->
                <div class="tab-content active" id="projets-content">
                    <?php if (isset($error_projets)): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error_projets) ?></div>
                    <?php elseif (empty($projets)): ?>
                        <p>Ce client n'a pas encore de projets.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Type</th>
                                    <th>Surface</th>
                                    <th>Statut</th>
                                    <th>Créé le</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projets as $projet): ?>
                                    <?php $statusInfo = getStatusInfo($projet['statut']); ?>
                                    <tr>
                                        <td><?= $projet['id'] ?></td>
                                        <td><?= htmlspecialchars($projet['titre']) ?></td>
                                        <td><?= htmlspecialchars($projet['type_chauffage']) ?></td>
                                        <td><?= htmlspecialchars($projet['surface']) ?> m²</td>
                                        <td>
                                            <span class="status-badge <?= $statusInfo['class'] ?>"><?= htmlspecialchars($statusInfo['label']) ?></span>
                                        </td>
                                        <td><?= format_date($projet['date_creation']) ?></td>
                                        <td>
                                            <a href="projet_detail.php?id=<?= $projet['id'] ?>" class="action-btn">Voir</a>
                                            <a href="#" class="action-btn delete-btn" onclick="showDeleteProjetModal(<?= $projet['id'] ?>, '<?= htmlspecialchars(addslashes($projet['titre'])) ?>')">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Contenu des messages -->
                <div class="tab-content" id="messages-content">
                    <?php if (isset($error_messages)): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error_messages) ?></div>
                    <?php elseif (empty($messages)): ?>
                        <p>Ce client n'a pas encore de messages.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sujet</th>
                                    <th>Expéditeur</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $key => $message): ?>
                                    <tr class="<?= ($message['expediteur'] === 'client' && !$message['lu']) ? 'unread-row' : '' ?>">
                                        <td><?= $message['id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($message['sujet']) ?>
                                            <button class="expand-btn" onclick="toggleMessage(<?= $key ?>)">Voir le message</button>
                                            <div id="message-<?= $key ?>" class="message-details">
                                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($message['expediteur'] === 'client'): ?>
                                                Client
                                            <?php else: ?>
                                                <?= htmlspecialchars($message['nom_expediteur']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= format_date($message['date_creation']) ?></td>
                                        <td>
                                            <?php if ($message['expediteur'] === 'client'): ?>
                                                <?php if ($message['lu']): ?>
                                                    <span style="color: #4CAF50;">Lu</span>
                                                <?php else: ?>
                                                    <span style="color: #2196F3;">
                                                        <span class="message-unread"></span>Non lu
                                                    </span>
                                                    <a href="messages.php?action=mark_read&id=<?= $message['id'] ?>&redirect=client_detail.php?id=<?= $clientId ?>" class="action-btn">Marquer comme lu</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span>Envoyé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="#" class="action-btn delete-btn" onclick="showDeleteMessageModal(<?= $message['id'] ?>, '<?= htmlspecialchars(addslashes($message['sujet'])) ?>')">Supprimer</a>
                                            <?php if ($message['expediteur'] === 'client'): ?>
                                                <a href="envoyer_message.php?client_id=<?= $clientId ?>&reply_to=<?= $message['id'] ?>" class="action-btn">Répondre</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Modal de confirmation de suppression de projet -->
    <div id="deleteProjetModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Confirmer la suppression du projet</h2>
            <p id="deleteProjetMessage">Êtes-vous sûr de vouloir supprimer ce projet ?</p>
            <div class="modal-buttons">
                <button id="cancelProjetDelete" class="modal-btn modal-btn-cancel" onclick="hideDeleteProjetModal()">Annuler</button>
                <a id="confirmProjetDelete" href="#" class="modal-btn modal-btn-delete">Supprimer</a>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmation de suppression de message -->
    <div id="deleteMessageModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Confirmer la suppression du message</h2>
            <p id="deleteMessageMessage">Êtes-vous sûr de vouloir supprimer ce message ?</p>
            <div class="modal-buttons">
                <button id="cancelMessageDelete" class="modal-btn modal-btn-cancel" onclick="hideDeleteMessageModal()">Annuler</button>
                <a id="confirmMessageDelete" href="#" class="modal-btn modal-btn-delete">Supprimer</a>
            </div>
        </div>
    </div>
    
    <script>
        // Fonction pour gérer les onglets
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Désactiver tous les onglets
                    tabs.forEach(t => t.classList.remove('active'));
                    // Activer l'onglet cliqué
                    tab.classList.add('active');
                    
                    // Masquer tous les contenus d'onglets
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Afficher le contenu correspondant à l'onglet
                    const contentId = tab.getAttribute('data-tab') + '-content';
                    document.getElementById(contentId).classList.add('active');
                });
            });
        });
        
        // Fonction pour afficher/masquer le contenu d'un message
        function toggleMessage(key) {
            const messageElement = document.getElementById(`message-${key}`);
            if (messageElement.style.display === 'block') {
                messageElement.style.display = 'none';
            } else {
                messageElement.style.display = 'block';
            }
        }
        
        // Fonctions pour gérer la modal de suppression de projet
        function showDeleteProjetModal(id, titre) {
            document.getElementById('deleteProjetMessage').textContent = `Êtes-vous sûr de vouloir supprimer le projet "${titre}" ?`;
            document.getElementById('confirmProjetDelete').href = `projets.php?action=delete&id=${id}&redirect=client_detail.php?id=<?= $clientId ?>`;
            document.getElementById('deleteProjetModal').style.display = 'flex';
        }
        
        function hideDeleteProjetModal() {
            document.getElementById('deleteProjetModal').style.display = 'none';
        }
        
        // Fonctions pour gérer la modal de suppression de message
        function showDeleteMessageModal(id, sujet) {
            document.getElementById('deleteMessageMessage').textContent = `Êtes-vous sûr de vouloir supprimer le message "${sujet}" ?`;
            document.getElementById('confirmMessageDelete').href = `messages.php?action=delete&id=${id}&redirect=client_detail.php?id=<?= $clientId ?>`;
            document.getElementById('deleteMessageModal').style.display = 'flex';
        }
        
        function hideDeleteMessageModal() {
            document.getElementById('deleteMessageModal').style.display = 'none';
        }
        
        // Fermer les modals si on clique en dehors
        window.onclick = function(event) {
            const projetModal = document.getElementById('deleteProjetModal');
            const messageModal = document.getElementById('deleteMessageModal');
            
            if (event.target === projetModal) {
                hideDeleteProjetModal();
            }
            
            if (event.target === messageModal) {
                hideDeleteMessageModal();
            }
        }
    </script>
</body>
</html> 