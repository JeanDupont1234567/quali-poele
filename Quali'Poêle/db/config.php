<?php
// Vérifier l'accès direct au fichier
if (!defined('SECURE_ACCESS')) {
    exit('Accès direct au fichier non autorisé');
}

// Informations de connexion à la base de données
define('DB_HOST', 'localhost');  // Hôte de la base de données
define('DB_NAME', 'quali_poele');  // Nom de la base de données
define('DB_USER', 'root');  // Nom d'utilisateur de la base de données
define('DB_PASS', '');  // Mot de passe de la base de données

// Clé de salt spécifique au site pour renforcer le hachage
define('SITE_KEY', 'qpoe2024_secure_salt!');

// Délai maximum d'inactivité de session en secondes (30 minutes)
define('SESSION_MAX_LIFETIME', 1800);

// Nombre maximum de tentatives de connexion avant blocage
define('MAX_LOGIN_ATTEMPTS', 5);

// Durée du blocage en secondes (15 minutes)
define('BLOCK_DURATION', 900);

// Connexion à la base de données avec PDO
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // En production, ne pas afficher les détails de l'erreur
    error_log('Erreur de connexion à la base de données: ' . $e->getMessage());
    exit('Une erreur est survenue lors de la connexion à la base de données.');
}

// Configuration de la session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_MAX_LIFETIME);
session_name('QUALI_SID');

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 