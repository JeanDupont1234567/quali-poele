<?php
// Empêcher l'accès direct au fichier
define('SECURE_ACCESS', true);

// Chemin vers les fichiers de configuration
$config_path = dirname(__DIR__) . '/db/';

// Charger la configuration principale
require_once $config_path . 'config.php';

// Charger les fonctions utilitaires
require_once __DIR__ . '/functions.php';

// Charger le système d'authentification
require_once __DIR__ . '/auth.php';

/**
 * Crée les tables nécessaires au système d'authentification
 * @return bool Succès de l'opération
 */
function create_auth_tables() {
    global $pdo;
    
    try {
        // Table clients
        $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            telephone VARCHAR(20) NOT NULL,
            code_postal VARCHAR(5) NOT NULL,
            date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
            session_token VARCHAR(64) NULL,
            token_expiration DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Table projets
        $pdo->exec("CREATE TABLE IF NOT EXISTS projets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            type_chauffage VARCHAR(50) NOT NULL,
            surface INT NOT NULL,
            budget DECIMAL(10,2) NULL,
            statut ENUM('En attente', 'Devis envoyé', 'Rendez-vous planifié', 'Installation en cours', 'Terminé') DEFAULT 'En attente',
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Table messages
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            message TEXT NOT NULL,
            date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
            est_lu BOOLEAN DEFAULT FALSE,
            expediteur ENUM('client', 'admin') DEFAULT 'client',
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Table tentatives de connexion
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (email),
            INDEX (ip_address),
            INDEX (attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Table accès bloqués
        $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NULL,
            ip_address VARCHAR(45) NULL,
            blocked_until DATETIME NOT NULL,
            block_reason VARCHAR(255) DEFAULT 'Trop de tentatives échouées',
            UNIQUE KEY (email),
            UNIQUE KEY (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Table journaux d'accès
        $pdo->exec("CREATE TABLE IF NOT EXISTS access_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            access_type ENUM('login', 'admin_login', 'api', 'data_access') NOT NULL,
            status ENUM('success', 'failure') NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            user_id INT NULL,
            details TEXT NULL,
            log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (access_type),
            INDEX (status),
            INDEX (ip_address),
            INDEX (user_id),
            INDEX (log_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la création des tables: " . $e->getMessage());
        return false;
    }
} 