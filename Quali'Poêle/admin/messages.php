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

// Initialiser les variables de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Initialiser les variables de filtrage
$readStatus = isset($_GET['read']) ? sanitize_input($_GET['read']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Construction de la condition WHERE
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(m.sujet LIKE ? OR m.message LIKE ? OR c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ?)";
    $searchValue = "%$search%";
    $params = array_merge($params, [$searchValue, $searchValue, $searchValue, $searchValue, $searchValue]);
}

if ($readStatus === '0' || $readStatus === '1') {
    $whereConditions[] = "m.lu = ?";
    $params[] = (int)$readStatus;
}

$whereClause = "";
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Récupérer le nombre total de messages pour la pagination
try {
    $countSql = "SELECT COUNT(*) as total FROM messages m 
                 LEFT JOIN clients c ON m.client_id = c.id 
                 $whereClause";
    $countStmt = $pdo->prepare($countSql);
    if (!empty($params)) {
        $countStmt->execute($params);
    } else {
        $countStmt->execute();
    }
    $totalMessages = $countStmt->fetch()['total'];
    $totalPages = ceil($totalMessages / $perPage);
} catch (PDOException $e) {
    error_log("Erreur lors du comptage des messages: " . $e->getMessage());
    $error = "Une erreur est survenue lors du comptage des messages.";
    $totalMessages = 0;
    $totalPages = 1;
}

// Récupérer les messages avec pagination et jointure sur les clients
try {
    $sql = "SELECT m.*, c.prenom, c.nom, c.email 
            FROM messages m 
            LEFT JOIN clients c ON m.client_id = c.id 
            $whereClause 
            ORDER BY m.date_creation DESC 
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmtParams = array_merge($params, [$perPage, $offset]);
    $stmt->execute($stmtParams);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des messages: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des messages.";
    $messages = [];
}

// Marquer un message comme lu
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    
    try {
        // Vérifier que le message existe
        $checkStmt = $pdo->prepare("SELECT id FROM messages WHERE id = ?");
        $checkStmt->execute([$messageId]);
        if ($checkStmt->rowCount() === 0) {
            $error = "Le message n'existe pas.";
        } else {
            // Marquer comme lu
            $updateStmt = $pdo->prepare("UPDATE messages SET lu = 1 WHERE id = ?");
            $updateStmt->execute([$messageId]);
            
            // Rediriger pour éviter la soumission multiple
            header("Location: messages.php?marked=1" . (!empty($search) ? "&search=" . urlencode($search) : "") . ($readStatus !== '' ? "&read=" . $readStatus : "") . "&page=" . $page);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors du marquage du message: " . $e->getMessage());
        $error = "Une erreur est survenue lors du marquage du message.";
    }
}

// Supprimer un message
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    
    try {
        // Vérifier que le message existe
        $checkStmt = $pdo->prepare("SELECT id FROM messages WHERE id = ?");
        $checkStmt->execute([$messageId]);
        if ($checkStmt->rowCount() === 0) {
            $error = "Le message n'existe pas.";
        } else {
            // Supprimer le message
            $deleteStmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $deleteStmt->execute([$messageId]);
            
            // Rediriger pour éviter la soumission multiple
            header("Location: messages.php?deleted=1" . (!empty($search) ? "&search=" . urlencode($search) : "") . ($readStatus !== '' ? "&read=" . $readStatus : "") . "&page=" . $page);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du message: " . $e->getMessage());
        $error = "Une erreur est survenue lors de la suppression du message.";
    }
}

// Compter les messages non lus (pour le badge)
try {
    $unreadStmt = $pdo->query("SELECT COUNT(*) as total FROM messages WHERE lu = 0");
    $unreadCount = $unreadStmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Erreur lors du comptage des messages non lus: " . $e->getMessage());
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'">
    <title>Gestion des messages - Quali'Poêle</title>
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
        }
        .admin-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .unread-row {
            font-weight: bold;
            background-color: rgba(33, 150, 243, 0.05);
        }
        .action-btn {
            background: none;
            border: none;
            font-size: 14px;
            cursor: pointer;
            margin-right: 10px;
            color: var(--secondary-color);
        }
        .delete-btn {
            color: #F44336;
        }
        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-group label {
            font-weight: 500;
        }
        .filter-group select {
            padding: 10px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background-color: rgba(0,0,0,0.2);
            color: var(--text-color);
        }
        .search-container {
            flex: 1;
            display: flex;
            gap: 10px;
        }
        .search-container input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            background-color: rgba(0,0,0,0.2);
            color: var(--text-color);
        }
        .search-container button, .filter-btn {
            padding: 10px 20px;
            background: var(--secondary-color);
            color: var(--dark-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            background-color: rgba(0,0,0,0.2);
            color: var(--text-color);
            text-decoration: none;
        }
        .pagination a:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .pagination .current {
            background-color: var(--secondary-color);
            color: var(--dark-color);
            font-weight: 600;
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
        .message-details {
            background-color: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            white-space: pre-wrap;
            display: none;
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
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="logout.php">Déconnexion</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="admin-container">
            <h1 class="admin-title">Gestion des messages</h1>
            
            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
                <div class="alert alert-success">Le message a été supprimé avec succès.</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['marked']) && $_GET['marked'] == 1): ?>
                <div class="alert alert-success">Le message a été marqué comme lu.</div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="admin-card">
                <form action="" method="get" class="filters-container">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Rechercher dans les messages..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit">Rechercher</button>
                    </div>
                    
                    <div class="filter-group">
                        <label for="read">Statut:</label>
                        <select name="read" id="read">
                            <option value="">Tous</option>
                            <option value="0" <?= $readStatus === '0' ? 'selected' : '' ?>>Non lus</option>
                            <option value="1" <?= $readStatus === '1' ? 'selected' : '' ?>>Lus</option>
                        </select>
                    </div>
                    
                    <?php if (!empty($search) || $readStatus !== ''): ?>
                        <a href="messages.php" style="display: flex; align-items: center; text-decoration: none; color: var(--text-color); margin-left: 10px;">
                            Réinitialiser
                        </a>
                    <?php endif; ?>
                </form>
                
                <p>Total: <?= $totalMessages ?> message<?= $totalMessages > 1 ? 's' : '' ?></p>
                
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Sujet</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Aucun message trouvé</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($messages as $key => $message): ?>
                                <tr class="<?= $message['lu'] ? '' : 'unread-row' ?>">
                                    <td><?= $message['id'] ?></td>
                                    <td>
                                        <?php if ($message['client_id']): ?>
                                            <a href="client_detail.php?id=<?= $message['client_id'] ?>" class="action-btn">
                                                <?= htmlspecialchars($message['prenom'] . ' ' . $message['nom']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span><?= htmlspecialchars($message['nom_expediteur']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($message['sujet']) ?>
                                        <button class="expand-btn" onclick="toggleMessage(<?= $key ?>)">Voir le message</button>
                                        <div id="message-<?= $key ?>" class="message-details">
                                            <strong>Email:</strong> <?= htmlspecialchars($message['client_id'] ? $message['email'] : $message['email_expediteur']) ?><br>
                                            <strong>Téléphone:</strong> <?= htmlspecialchars($message['telephone_expediteur']) ?><br>
                                            <strong>Message:</strong><br><?= nl2br(htmlspecialchars($message['message'])) ?>
                                        </div>
                                    </td>
                                    <td><?= format_date($message['date_creation']) ?></td>
                                    <td>
                                        <?php if ($message['lu']): ?>
                                            <span style="color: #4CAF50;">Lu</span>
                                        <?php else: ?>
                                            <span style="color: #2196F3;">Non lu</span>
                                            <a href="messages.php?action=mark_read&id=<?= $message['id'] ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $readStatus !== '' ? '&read=' . $readStatus : '' ?>&page=<?= $page ?>" class="action-btn">Marquer comme lu</a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" class="action-btn delete-btn" onclick="showDeleteModal(<?= $message['id'] ?>, '<?= htmlspecialchars(addslashes($message['sujet'])) ?>')">Supprimer</a>
                                        <?php if ($message['client_id']): ?>
                                            <a href="envoyer_message.php?client_id=<?= $message['client_id'] ?>&reply_to=<?= $message['id'] ?>" class="action-btn">Répondre</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $readStatus !== '' ? '&read=' . $readStatus : '' ?>">Précédent</a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1) {
                            echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . ($readStatus !== '' ? '&read=' . $readStatus : '') . '">1</a>';
                            if ($startPage > 2) {
                                echo '<span>...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $page) {
                                echo '<span class="current">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . ($readStatus !== '' ? '&read=' . $readStatus : '') . '">' . $i . '</a>';
                            }
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span>...</span>';
                            }
                            echo '<a href="?page=' . $totalPages . (!empty($search) ? '&search=' . urlencode($search) : '') . ($readStatus !== '' ? '&read=' . $readStatus : '') . '">' . $totalPages . '</a>';
                        }
                        ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $readStatus !== '' ? '&read=' . $readStatus : '' ?>">Suivant</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Confirmer la suppression</h2>
            <p id="deleteMessage">Êtes-vous sûr de vouloir supprimer ce message ?</p>
            <div class="modal-buttons">
                <button id="cancelDelete" class="modal-btn modal-btn-cancel" onclick="hideDeleteModal()">Annuler</button>
                <a id="confirmDelete" href="#" class="modal-btn modal-btn-delete">Supprimer</a>
            </div>
        </div>
    </div>
    
    <script>
        function toggleMessage(key) {
            const messageElement = document.getElementById(`message-${key}`);
            if (messageElement.style.display === 'block') {
                messageElement.style.display = 'none';
            } else {
                messageElement.style.display = 'block';
            }
        }
        
        function showDeleteModal(id, sujet) {
            document.getElementById('deleteMessage').textContent = `Êtes-vous sûr de vouloir supprimer le message "${sujet}" ?`;
            document.getElementById('confirmDelete').href = `messages.php?action=delete&id=${id}&page=<?= $page ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $readStatus !== '' ? '&read=' . $readStatus : '' ?>`;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Fermer la modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                hideDeleteModal();
            }
        }
        
        // Soumettre automatiquement le formulaire lorsque les filtres changent
        document.getElementById('read').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html> 