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

// Initialiser les variables de filtrage
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchTerm = '%' . $search . '%';

// Construction de la requête de base
$queryBase = "
    FROM projets p
    JOIN clients c ON p.client_id = c.id
";

$whereClause = [];
$queryParams = [];

if (!empty($search)) {
    $whereClause[] = "(p.titre LIKE ? OR p.description LIKE ? OR c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ?)";
    $queryParams = array_merge($queryParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $whereClause[] = "p.statut = ?";
    $queryParams[] = $status;
}

$whereSQL = '';
if (!empty($whereClause)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClause);
}

// Récupérer le nombre total de projets pour la pagination
try {
    $countQuery = "SELECT COUNT(*) " . $queryBase . $whereSQL;
    $stmt = $pdo->prepare($countQuery);
    if (!empty($queryParams)) {
        $stmt->execute($queryParams);
    } else {
        $stmt->execute();
    }
    $totalProjets = $stmt->fetchColumn();
    $totalPages = ceil($totalProjets / $perPage);
    
    // Ajuster la page si elle dépasse le total
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }
    
    // Récupérer les projets pour la page courante
    $query = "
        SELECT p.*, c.nom, c.prenom, c.email
        " . $queryBase . "
        " . $whereSQL . "
        ORDER BY p.date_creation DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $allParams = array_merge($queryParams, [$perPage, $offset]);
    $stmt->execute($allParams);
    $projets = $stmt->fetchAll();
    
    // Récupérer les statuts pour le filtre
    $stmtStatuts = $pdo->query("SELECT DISTINCT statut FROM projets");
    $statuts = $stmtStatuts->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des projets: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des projets.";
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
    <title>Projets - Administration Quali'Poêle</title>
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
        .filters-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            align-items: center;
        }
        .filter-label {
            margin-right: 8px;
            font-weight: 500;
        }
        .filter-select {
            padding: 8px 12px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 5px;
            background-color: var(--dark-color);
            color: var(--text-color);
        }
        .search-form {
            display: flex;
            flex: 1;
            max-width: 500px;
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
        .reset-filters {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 14px;
            margin-left: 10px;
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
        .truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
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
                <h1>Projets (<?= $totalProjets ?>)</h1>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="filters-container">
                <form action="" method="get" class="search-form">
                    <input type="text" name="search" placeholder="Rechercher un projet..." class="search-input" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="search-btn">Rechercher</button>
                    
                    <?php if (!empty($status)): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <?php endif; ?>
                </form>
                
                <div class="filter-group">
                    <span class="filter-label">Statut:</span>
                    <select name="status" id="status-filter" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <?php foreach ($statuts as $s): ?>
                            <?php $statusInfo = getStatusInfo($s); ?>
                            <option value="<?= $s ?>" <?= $s === $status ? 'selected' : '' ?>><?= $statusInfo['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($search) || !empty($status)): ?>
                    <a href="projets.php" class="reset-filters">Réinitialiser les filtres</a>
                <?php endif; ?>
            </div>
            
            <div class="table-container">
                <?php if (empty($projets)): ?>
                    <div class="no-results">
                        <?php if (!empty($search) || !empty($status)): ?>
                            Aucun projet ne correspond à vos critères de recherche.
                        <?php else: ?>
                            Aucun projet n'a encore été enregistré.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Projet</th>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Surface</th>
                                <th>Statut</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projets as $projet): ?>
                                <tr>
                                    <td><span class="truncate"><?= htmlspecialchars($projet['titre'] ?: 'Projet #' . $projet['id']) ?></span></td>
                                    <td><?= htmlspecialchars($projet['prenom'] . ' ' . $projet['nom']) ?></td>
                                    <td><?= htmlspecialchars($projet['type_chauffage']) ?></td>
                                    <td><?= htmlspecialchars($projet['surface']) ?> m²</td>
                                    <td>
                                        <?php $statusInfo = getStatusInfo($projet['statut']); ?>
                                        <span class="status-badge <?= $statusInfo['class'] ?>"><?= htmlspecialchars($statusInfo['label']) ?></span>
                                    </td>
                                    <td><?= format_date($projet['date_creation']) ?></td>
                                    <td>
                                        <a href="projet_detail.php?id=<?= $projet['id'] ?>" class="btn btn-primary">Détails</a>
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
                        <a href="?page=1<?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?><?= !empty($status) ? '&status=' . htmlspecialchars($status) : '' ?>">«</a>
                        <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?><?= !empty($status) ? '&status=' . htmlspecialchars($status) : '' ?>">‹</a>
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
                            echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . htmlspecialchars($search) : '') . (!empty($status) ? '&status=' . htmlspecialchars($status) : '') . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($endPage < $totalPages) {
                        echo '<span>...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?><?= !empty($status) ? '&status=' . htmlspecialchars($status) : '' ?>">›</a>
                        <a href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . htmlspecialchars($search) : '' ?><?= !empty($status) ? '&status=' . htmlspecialchars($status) : '' ?>">»</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Script pour soumettre le formulaire lors du changement de filtre
        document.getElementById('status-filter').addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('status', this.value);
            
            if (this.value === '') {
                currentUrl.searchParams.delete('status');
            }
            
            window.location.href = currentUrl.toString();
        });
    </script>
</body>
</html> 