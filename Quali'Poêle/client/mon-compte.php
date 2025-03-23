<?php
// Empêcher l'accès direct au fichier
define('SECURE_ACCESS', true);

// Charger la configuration et l'authentification
require_once 'init.php';
require_once 'auth.php';

// Initialiser l'authentification
$auth = new Authentication($pdo);

// Vérifier si l'utilisateur est connecté
if (!$auth->verify_session()) {
    // Rediriger vers la page de connexion
    header('Location: connexion.php');
    exit;
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$user = null;

try {
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email, telephone, code_postal, date_inscription FROM clients WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des informations utilisateur: " . $e->getMessage());
}

// Récupérer le nombre de projets de l'utilisateur
$projet_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projets WHERE client_id = ?");
    $stmt->execute([$user_id]);
    $projet_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur lors du comptage des projets: " . $e->getMessage());
}

// Récupérer le nombre de messages non lus
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE client_id = ? AND est_lu = 0 AND expediteur = 'admin'");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur lors du comptage des messages: " . $e->getMessage());
}

// Récupérer le dernier projet
$latest_project = null;
if ($projet_count > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, type_chauffage, surface, budget, statut, date_creation 
            FROM projets 
            WHERE client_id = ? 
            ORDER BY date_creation DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $latest_project = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du dernier projet: " . $e->getMessage());
    }
}

// Fonction pour obtenir la classe CSS correspondant au statut
function getStatusClass($status) {
    switch ($status) {
        case 'En attente':
            return 'badge-pending';
        case 'Devis envoyé':
            return 'badge-sent';
        case 'Rendez-vous planifié':
            return 'badge-planned';
        case 'Installation en cours':
            return 'badge-progress';
        case 'Terminé':
            return 'badge-completed';
        default:
            return 'badge-pending';
    }
}

// Fonction pour obtenir la description du statut
function getStatusDescription($status) {
    switch ($status) {
        case 'En attente':
            return 'Votre demande est en cours d\'analyse par notre équipe.';
        case 'Devis envoyé':
            return 'Un devis a été envoyé. Consultez vos messages.';
        case 'Rendez-vous planifié':
            return 'Un rendez-vous technique a été planifié.';
        case 'Installation en cours':
            return 'L\'installation de votre équipement est en cours.';
        case 'Terminé':
            return 'Votre projet est terminé. Nous restons à votre disposition.';
        default:
            return 'Statut indéterminé.';
    }
}

// Date au format français
function formatFrenchDate($date) {
    $timestamp = strtotime($date);
    return date('d/m/Y à H:i', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'">
    <title>Mon Compte - Quali'Poêle</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/compte.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            color: var(--text-color);
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .user-welcome {
            font-size: 2.5em;
            margin: 0;
            font-weight: 700;
        }
        .logout-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background-color: rgba(255,255,255,0.2);
        }
        .logout-btn svg {
            margin-right: 8px;
        }
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .dashboard-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 1.25em;
            margin: 0;
            font-weight: 600;
        }
        .card-link {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s;
        }
        .card-link:hover {
            opacity: 0.8;
        }
        .card-link svg {
            margin-left: 5px;
        }
        .profile-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .profile-item {
            margin-bottom: 15px;
        }
        .profile-label {
            display: block;
            font-size: 0.9em;
            color: rgba(255,255,255,0.6);
            margin-bottom: 5px;
        }
        .profile-value {
            font-weight: 500;
        }
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            grid-column: span 2;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
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
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .stat-item {
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 2em;
            font-weight: 700;
            margin: 0;
            color: var(--secondary-color);
        }
        .stat-label {
            font-size: 0.9em;
            color: rgba(255,255,255,0.7);
            margin-top: 5px;
        }
        .projects-list, .messages-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .project-item, .message-item {
            padding: 15px;
            border-radius: 8px;
            background: rgba(0,0,0,0.2);
            margin-bottom: 10px;
        }
        .project-item:last-child, .message-item:last-child {
            margin-bottom: 0;
        }
        .project-title {
            margin: 0 0 5px;
            font-weight: 600;
            font-size: 1.1em;
        }
        .project-meta, .message-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.9em;
            color: rgba(255,255,255,0.7);
            margin-bottom: 10px;
        }
        .project-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
        }
        .status-new {
            background-color: #3498db;
            color: #fff;
        }
        .status-progress {
            background-color: #f39c12;
            color: #fff;
        }
        .status-qualified {
            background-color: #2ecc71;
            color: #fff;
        }
        .status-not-qualified {
            background-color: #e74c3c;
            color: #fff;
        }
        .status-completed {
            background-color: #9b59b6;
            color: #fff;
        }
        .message-subject {
            margin: 0 0 5px;
            font-weight: 600;
            font-size: 1.1em;
        }
        .message-content {
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            font-size: 0.95em;
            color: rgba(255,255,255,0.8);
        }
        .message-unread {
            position: relative;
        }
        .message-unread::before {
            content: '';
            display: block;
            width: 10px;
            height: 10px;
            background-color: var(--secondary-color);
            border-radius: 50%;
            position: absolute;
            top: 15px;
            right: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 20px;
            color: rgba(255,255,255,0.7);
        }
        .empty-state p {
            margin-bottom: 15px;
        }
        .empty-icon {
            font-size: 3em;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            .profile-info {
                grid-template-columns: 1fr;
            }
            .profile-actions {
                grid-column: span 1;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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
                <h1 class="user-welcome">Bienvenue, <?php echo htmlspecialchars($user['prenom']); ?></h1>
                <a href="deconnexion.php" class="logout-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                        <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                    </svg>
                    Déconnexion
                </a>
            </div>
            
            <div class="dashboard-cards">
                <!-- Carte Profil -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Mon Profil</h2>
                        <a href="modifier-profil.php" class="card-link">
                            Modifier
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                            </svg>
                        </a>
                    </div>
                    <div class="profile-info">
                        <div class="profile-item">
                            <span class="profile-label">Nom</span>
                            <span class="profile-value"><?php echo htmlspecialchars($user['nom']); ?></span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Prénom</span>
                            <span class="profile-value"><?php echo htmlspecialchars($user['prenom']); ?></span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Email</span>
                            <span class="profile-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Téléphone</span>
                            <span class="profile-value"><?php echo htmlspecialchars($user['telephone']); ?></span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Code postal</span>
                            <span class="profile-value"><?php echo htmlspecialchars($user['code_postal']); ?></span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Membre depuis</span>
                            <span class="profile-value"><?php echo formatFrenchDate($user['date_inscription']); ?></span>
                        </div>
                        <div class="profile-actions">
                            <a href="modifier-profil.php" class="btn btn-primary">Modifier mon profil</a>
                            <a href="modifier-mot-de-passe.php" class="btn btn-secondary">Changer de mot de passe</a>
                        </div>
                    </div>
                </div>
                
                <!-- Carte Statistiques -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Tableau de bord</h2>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <p class="stat-value"><?php echo $projet_count; ?></p>
                            <p class="stat-label">Projets</p>
                        </div>
                        <div class="stat-item">
                            <p class="stat-value"><?php echo $unread_count; ?></p>
                            <p class="stat-label">Messages non lus</p>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <a href="../qualification.html" class="btn btn-primary" style="width: 100%;">Demander un nouveau devis</a>
                    </div>
                </div>
                
                <!-- Carte Projets Récents -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Mes Projets Récents</h2>
                        <a href="projets.php" class="card-link">
                            Voir tous
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </a>
                    </div>
                    
                    <?php if (empty($latest_project)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <p>Vous n'avez pas encore de projet.</p>
                            <a href="../qualification.html" class="btn btn-primary btn-sm">Demander un devis</a>
                        </div>
                    <?php else: ?>
                        <div class="latest-project">
                            <h3>Dernier projet</h3>
                            <div class="project-details">
                                <p>Type: <strong><?php echo htmlspecialchars($latest_project['type_chauffage']); ?></strong></p>
                                <p>Surface: <strong><?php echo htmlspecialchars($latest_project['surface']); ?> m²</strong></p>
                                <?php if ($latest_project['budget']): ?>
                                    <p>Budget: <strong><?php echo number_format($latest_project['budget'], 2, ',', ' '); ?> €</strong></p>
                                <?php endif; ?>
                                <p>Créé le: <strong><?php echo formatFrenchDate($latest_project['date_creation']); ?></strong></p>
                                <p>Statut: 
                                    <span class="status-badge <?php echo getStatusClass($latest_project['statut']); ?>">
                                        <?php echo htmlspecialchars($latest_project['statut']); ?>
                                    </span>
                                </p>
                                <p class="status-description">
                                    <?php echo getStatusDescription($latest_project['statut']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Carte Messages Récents -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Mes Messages Récents</h2>
                        <a href="messages.php" class="card-link">
                            Voir tous
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </a>
                    </div>
                    
                    <?php if (empty($latest_project)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">✉️</div>
                            <p>Vous n'avez pas encore de messages.</p>
                            <a href="envoyer-message.php" class="btn btn-primary btn-sm">Envoyer un message</a>
                        </div>
                    <?php else: ?>
                        <ul class="messages-list">
                            <?php if ($unread_count > 0): ?>
                                <li class="notification-alert">
                                    <p>Vous avez <?php echo $unread_count; ?> message<?php echo $unread_count > 1 ? 's' : ''; ?> non lu<?php echo $unread_count > 1 ? 's' : ''; ?>.</p>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <div style="margin-top: 15px;">
                            <a href="envoyer-message.php" class="btn btn-primary" style="width: 100%;">Nouveau message</a>
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
                <p>&copy; <?php echo date('Y'); ?> Quali'Poêle - Tous droits réservés</p>
            </div>
        </div>
    </footer>
</body>
</html> 