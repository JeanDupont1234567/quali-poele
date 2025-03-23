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

// Vérifier si un ID projet est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: projets.php');
    exit;
}

$projetId = (int)$_GET['id'];

// Gestion de la mise à jour du statut
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $csrf = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
    
    if (!$csrf || $csrf !== $_SESSION['csrf_token']) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $newStatus = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        $allowedStatuses = ['new', 'pending', 'in_progress', 'completed', 'canceled'];
        
        if (!in_array($newStatus, $allowedStatuses)) {
            $error = "Statut invalide.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE projets SET statut = ? WHERE id = ?");
                $success = $stmt->execute([$newStatus, $projetId]);
                
                if ($success) {
                    $successMessage = "Le statut du projet a été mis à jour avec succès.";
                } else {
                    $error = "Impossible de mettre à jour le statut du projet.";
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la mise à jour du statut du projet: " . $e->getMessage());
                $error = "Une erreur est survenue lors de la mise à jour du statut du projet.";
            }
        }
    }
}

// Récupérer les informations du projet
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nom, c.prenom, c.email, c.telephone, c.code_postal 
        FROM projets p
        JOIN clients c ON p.client_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$projetId]);
    $projet = $stmt->fetch();
    
    if (!$projet) {
        header('Location: projets.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du projet: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des informations du projet.";
}

// Génération d'un token CSRF pour les formulaires
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    <title>Détail du projet - Quali'Poêle</title>
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
        .projet-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .projet-info-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
        }
        .projet-info-item:last-child {
            border-bottom: none;
        }
        .projet-info-label {
            font-weight: 500;
            color: var(--text-color);
            opacity: 0.8;
        }
        .projet-info-value {
            font-weight: 500;
            color: var(--secondary-color);
        }
        .projet-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .projet-desc {
            white-space: pre-wrap;
            line-height: 1.6;
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
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.5);
        }
        .alert-error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.5);
        }
        .status-form {
            margin-top: 20px;
        }
        .status-select {
            padding: 10px;
            border-radius: 5px;
            background-color: var(--dark-color);
            color: var(--text-color);
            border: 1px solid rgba(255,255,255,0.2);
            width: 100%;
            margin-bottom: 10px;
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
                <h1>Détail du projet</h1>
                <div>
                    <a href="client_detail.php?id=<?= $projet['client_id'] ?>" class="btn btn-primary">Voir le client</a>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            
            <div class="projet-grid">
                <div>
                    <div class="admin-card">
                        <h2><?= htmlspecialchars($projet['titre']) ?></h2>
                        <?php $statusInfo = getStatusInfo($projet['statut']); ?>
                        <span class="status-badge <?= $statusInfo['class'] ?>"><?= htmlspecialchars($statusInfo['label']) ?></span>
                        
                        <h3>Description</h3>
                        <div class="projet-desc">
                            <?= nl2br(htmlspecialchars($projet['description'])) ?>
                        </div>
                    </div>
                    
                    <!-- Informations détaillées du projet -->
                    <div class="admin-card">
                        <h3>Caractéristiques du projet</h3>
                        <ul class="projet-info-list">
                            <li class="projet-info-item">
                                <span class="projet-info-label">Type de chauffage</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['type_chauffage']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Surface</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['surface']) ?> m²</span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Budget</span>
                                <span class="projet-info-value"><?= $projet['budget'] ? htmlspecialchars($projet['budget']) . ' €' : 'Non spécifié' ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Type de logement</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['type_logement']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Année de construction</span>
                                <span class="projet-info-value"><?= $projet['annee_construction'] ? htmlspecialchars($projet['annee_construction']) : 'Non spécifié' ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Chauffage actuel</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['chauffage_actuel']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Date de création</span>
                                <span class="projet-info-value"><?= format_date($projet['date_creation']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Dernière mise à jour</span>
                                <span class="projet-info-value"><?= format_date($projet['date_maj']) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div>
                    <!-- Informations du client -->
                    <div class="admin-card">
                        <h3>Client</h3>
                        <ul class="projet-info-list">
                            <li class="projet-info-item">
                                <span class="projet-info-label">Nom</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['nom']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Prénom</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['prenom']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Email</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['email']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Téléphone</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['telephone']) ?></span>
                            </li>
                            <li class="projet-info-item">
                                <span class="projet-info-label">Code postal</span>
                                <span class="projet-info-value"><?= htmlspecialchars($projet['code_postal']) ?></span>
                            </li>
                        </ul>
                        <div class="status-form">
                            <h3>Modifier le statut</h3>
                            <form method="post" action="">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <select name="status" class="status-select">
                                    <option value="new" <?= $projet['statut'] === 'new' ? 'selected' : '' ?>>Nouveau</option>
                                    <option value="pending" <?= $projet['statut'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                                    <option value="in_progress" <?= $projet['statut'] === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                                    <option value="completed" <?= $projet['statut'] === 'completed' ? 'selected' : '' ?>>Terminé</option>
                                    <option value="canceled" <?= $projet['statut'] === 'canceled' ? 'selected' : '' ?>>Annulé</option>
                                </select>
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Mettre à jour</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 