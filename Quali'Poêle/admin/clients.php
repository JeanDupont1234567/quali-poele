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
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Initialiser les variables de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchTerm = '%' . $search . '%';

// Récupérer le nombre total de clients pour la pagination
try {
    if (empty($search)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ? OR code_postal LIKE ?");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    $totalClients = $stmt->fetchColumn();
    $totalPages = ceil($totalClients / $perPage);
    
    // Ajuster la page si elle dépasse le total
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }
    
    // Récupérer les clients pour la page courante
    if (empty($search)) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(p.id) AS projet_count, 
                   COUNT(m.id) AS message_count,
                   SUM(CASE WHEN m.lu = 0 AND m.expediteur = 'client' THEN 1 ELSE 0 END) AS unread_messages
            FROM clients c
            LEFT JOIN projets p ON c.id = p.client_id
            LEFT JOIN messages m ON c.id = m.client_id
            GROUP BY c.id
            ORDER BY c.date_inscription DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(p.id) AS projet_count, 
                   COUNT(m.id) AS message_count,
                   SUM(CASE WHEN m.lu = 0 AND m.expediteur = 'client' THEN 1 ELSE 0 END) AS unread_messages
            FROM clients c
            LEFT JOIN projets p ON c.id = p.client_id
            LEFT JOIN messages m ON c.id = m.client_id
            WHERE c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ? OR c.telephone LIKE ? OR c.code_postal LIKE ?
            GROUP BY c.id
            ORDER BY c.date_inscription DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $perPage, $offset]);
    }
    
    $clients = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des clients: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des clients.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'">
    <title>Clients - Administration Quali'Poêle</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .search-form {
            display: flex;
            max-width: 400px;
            margin-bottom: 20px;
        }
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            border-right: none;
            border-radius: 5px 0 0 5px;
            background-color: var(--dark-color);
            color: var(--text-color);
        }
        .search-btn {
            padding: 10px 15px;
            background-color: var(--secondary-color);
            color: var(--dark-color);
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
        }
        .search-btn:hover {
            opacity: 0.9;
        }
        .table-container {
            background: var(--dark-card);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th {
            background-color: rgba(0,0,0,0.2);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        .admin-table td {
            padding: 12px 15px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .admin-table tr:hover {
            background-color: rgba(255,255,255,0.03);
        }
        .btn {
            display: inline-block;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
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
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-primary {
            background-color: rgba(33, 150, 243, 0.2);
            color: #2196F3;
        }
        .badge-warning {
            background-color: rgba(255, 152, 0, 0.2);
            color: #FF9800;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
        }
        .pagination a {
            background: rgba(255,255,255,0.1);
            color: var(--text-color);
        }
        .pagination a:hover {
            background: rgba(255,255,255,0.2);
        }
        .pagination .current {
            background: var(--secondary-color);
            color: var(--dark-color);
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.5);
        }
        .no-results {
            text-align: center;
            padding: 30px;
            font-size: 16px;
            color: rgba(255,255,255,0.7);
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
            <div class="admin-title">
                <h1>Clients (<?= $totalClients ?>)</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form action="" method="get" class="search-form">
                <input type="text" name="search" placeholder="Rechercher un client..." class="search-input" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Rechercher</button>
            </form>
            
            <div class="table-container">
                <?php if (empty($clients)): ?>
                    <div class="no-results">
                        <?php if (!empty($search)): ?>
                            Aucun client ne correspond à votre recherche.
                        <?php else: ?>
                            Aucun client n'a encore été enregistré.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Code postal</th>
                                <th>Inscription</th>
                                <th>Projets</th>
                                <th>Messages</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></td>
                                    <td><?= htmlspecialchars($client['email']) ?></td>
                                    <td><?= htmlspecialchars($client['telephone']) ?></td>
                                    <td><?= htmlspecialchars($client['code_postal']) ?></td>
                                    <td><?= format_date($client['date_inscription']) ?></td>
                                    <td>
                                        <?php if ($client['projet_count'] > 0): ?>
                                            <span class="badge badge-primary"><?= $client['projet_count'] ?></span>
                                        <?php else: ?>
                                            0
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($client['unread_messages'] > 0): ?>
                                            <span class="badge badge-warning"><?= $client['unread_messages'] ?> non lu(s)</span>
                                        <?php else: ?>
                                            <?= $client['message_count'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="client_detail.php?id=<?= $client['id'] ?>" class="btn btn-primary">Détails</a>
                                        <a href="envoyer_message.php?client_id=<?= $client['id'] ?>" class="btn btn-secondary">Message</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?>">«</a>
                        <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?>">‹</a>
                    <?php endif; ?>
                    
                    <?php
                    $range = 2;
                    $startPage = max(1, $page - $range);
                    $endPage = min($totalPages, $page + $range);
                    
                    if ($startPage > 1) {
                        echo '<span>...</span>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $page) {
                            echo '<span class="current">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . htmlspecialchars($search) : '') . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<span>...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?>">›</a>
                        <a href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?>">»</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html> 