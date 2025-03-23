<?php
// Définir une constante pour autoriser l'accès aux fichiers de configuration
define('SECURE_ACCESS', true);

// Charger la configuration
require_once '../db/config.php';
require_once '../client/functions.php';

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si un administrateur est connecté
if (isset($_SESSION['admin_id'])) {
    // Enregistrer la déconnexion
    $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, email, ip_address, type, status) VALUES (?, ?, ?, 'admin_logout', 'success')");
    $stmt->execute([$_SESSION['admin_id'], $_SESSION['admin_email'], $_SERVER['REMOTE_ADDR']]);
}

// Détruire toutes les données de session
$_SESSION = array();

// Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: login.php?logout=success');
exit; 