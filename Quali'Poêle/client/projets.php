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

try {
    // Récupérer tous les projets du client
    $stmt = $pdo->prepare("SELECT * FROM projets WHERE client_id = ? ORDER BY date_creation DESC");
    $stmt->execute([$user_id]);
    $projets = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des projets: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération de vos projets.";
}

/**
 * Retourne une classe CSS selon le statut du projet
 */
function getStatusClass($status) {
    switch ($status) {
        case 'En attente':
            return 'badge-warning';
        case 'Devis envoyé':
            return 'badge-info';
        case 'Rendez-vous planifié':
            return 'badge-primary';
        case 'Installation en cours':
            return 'badge-secondary';
        case 'Terminé':
            return 'badge-success';
        default:
            return 'badge-info';
    }
}

/**
 * Retourne une description du statut
 */
function getStatusDescription($status) {
    switch ($status) {
        case 'En attente':
            return "Votre demande a été reçue et est en cours d'étude par nos équipes.";
        case 'Devis envoyé':
            return "Un devis personnalisé a été préparé et vous a été envoyé.";
        case 'Rendez-vous planifié':
            return "Un rendez-vous a été planifié avec l'un de nos techniciens.";
        case 'Installation en cours':
            return "L'installation de votre système de chauffage est en cours.";
        case 'Terminé':
            return "Votre projet est terminé. Nous vous remercions de votre confiance.";
        default:
            return "Statut inconnu";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'">
    <title>Mes projets - Quali'Poêle</title>
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
        .project-card {
            background: var(--dark-card);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .project-title {
            font-size: 20px;
            font-weight: 500;
            color: var(--secondary-color);
        }
        .project-id {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 500;
        }
        .badge-primary {
            background: var(--primary-color);
            color: var(--dark-color);
        }
        .badge-secondary {
            background: var(--secondary-color);
            color: var(--dark-color);
        }
        .badge-warning {
            background: var(--warning-color);
            color: var(--dark-color);
        }
        .badge-success {
            background: var(--success-color);
            color: var(--dark-color);
        }
        .badge-info {
            background: #0dcaf0;
            color: var(--dark-color);
        }
        .project-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .detail-group {
            margin-bottom: 10px;
        }
        .detail-label {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 16px;
            color: rgba(255,255,255,0.9);
        }
        .project-status-info {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .status-description {
            margin-top: 10px;
            font-size: 14px;
            line-height: 1.5;
        }
        .project-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-dashboard {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
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
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: var(--dark-card);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .empty-state p {
            margin-bottom: 20px;
            color: rgba(255,255,255,0.7);
        }
        .progress-steps {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 5px;
            margin: 20px 0;
        }
        .progress-step {
            position: relative;
            text-align: center;
            padding: 15px 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 5px;
        }
        .progress-step.active {
            background: var(--secondary-color);
            color: var(--dark-color);
        }
        .progress-step.completed {
            background: rgba(var(--success-color-rgb), 0.2);
        }
        .progress-step-name {
            font-size: 14px;
            font-weight: 500;
        }
        .progress-step::after {
            content: '';
            position: absolute;
            right: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 2px;
            background: rgba(255,255,255,0.2);
            z-index: 1;
        }
        .progress-step:last-child::after {
            display: none;
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
        @media (max-width: 768px) {
            .project-details {
                grid-template-columns: 1fr;
            }
            .progress-steps {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .progress-step::after {
                display: none;
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
                <h1 class="main-title">Mes projets</h1>
                <a href="mon-compte.php" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                    </svg>
                    Retour au tableau de bord
                </a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (count($projets) > 0): ?>
                <?php foreach ($projets as $projet): ?>
                    <div class="project-card">
                        <div class="project-header">
                            <div>
                                <div class="project-title"><?= htmlspecialchars($projet['type_chauffage']) ?></div>
                                <div class="project-id">Projet #<?= $projet['id'] ?></div>
                            </div>
                            <span class="badge <?= getStatusClass($projet['statut']) ?>"><?= htmlspecialchars($projet['statut']) ?></span>
                        </div>
                        
                        <div class="project-status-info">
                            <div class="progress-steps">
                                <?php
                                $statuses = ['En attente', 'Devis envoyé', 'Rendez-vous planifié', 'Installation en cours', 'Terminé'];
                                $currentStatusIndex = array_search($projet['statut'], $statuses);
                                
                                foreach ($statuses as $index => $status):
                                    $class = '';
                                    if ($index < $currentStatusIndex) {
                                        $class = 'completed';
                                    } elseif ($index === $currentStatusIndex) {
                                        $class = 'active';
                                    }
                                ?>
                                    <div class="progress-step <?= $class ?>">
                                        <div class="progress-step-name"><?= $status ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="status-description">
                                <?= getStatusDescription($projet['statut']) ?>
                            </div>
                        </div>
                        
                        <div class="project-details">
                            <div class="detail-group">
                                <div class="detail-label">Type d'habitation</div>
                                <div class="detail-value"><?= htmlspecialchars($projet['type_habitation']) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Surface</div>
                                <div class="detail-value"><?= htmlspecialchars($projet['surface']) ?> m²</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Chauffage actuel</div>
                                <div class="detail-value"><?= htmlspecialchars($projet['chauffage_actuel']) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Budget estimé</div>
                                <div class="detail-value"><?= $projet['budget'] ? number_format($projet['budget'], 0, ',', ' ') . ' €' : 'Non spécifié' ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Délai du projet</div>
                                <div class="detail-value"><?= htmlspecialchars($projet['delai_projet'] ?: 'Non spécifié') ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Date de création</div>
                                <div class="detail-value"><?= format_date($projet['date_creation'], false) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Dernière mise à jour</div>
                                <div class="detail-value"><?= format_date($projet['date_modification'], false) ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Aides estimées</div>
                                <div class="detail-value"><?= $projet['estimation_aides'] ? number_format($projet['estimation_aides'], 0, ',', ' ') . ' €' : 'En cours d\'évaluation' ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($projet['commentaire'])): ?>
                            <div class="detail-group" style="margin-bottom: 20px;">
                                <div class="detail-label">Commentaire</div>
                                <div class="detail-value"><?= nl2br(htmlspecialchars($projet['commentaire'])) ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="project-actions">
                            <a href="envoyer-message.php?projet_id=<?= $projet['id'] ?>" class="btn-dashboard btn-secondary">Envoyer un message</a>
                            <a href="projet-detail.php?id=<?= $projet['id'] ?>" class="btn-dashboard btn-primary">Voir les détails</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h2>Vous n'avez pas encore de projet</h2>
                    <p>Demandez un devis pour commencer votre projet d'installation de chauffage</p>
                    <a href="../qualification.html" class="btn-dashboard btn-primary">Demander un devis</a>
                </div>
            <?php endif; ?>
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