<?php
// Empêcher l'accès direct au fichier
define('SECURE_ACCESS', true);

// Charger la configuration et l'authentification
require_once 'init.php';
require_once 'auth.php';

// Initialiser l'authentification
$auth = new Authentication($pdo);

// Déconnecter l'utilisateur
$auth->logout();

// Rediriger vers la page d'accueil
header('Location: ../index.html');
exit; 