<?php
// Empêcher l'accès direct au fichier
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès interdit');
}

/**
 * Nettoie les entrées utilisateur
 * @param string $input L'entrée à nettoyer
 * @return string L'entrée nettoyée
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Génère un token aléatoire
 * @param int $length Longueur du token
 * @return string Token généré
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hache un mot de passe avec un salt unique
 * @param string $password Mot de passe en clair
 * @return string Mot de passe haché
 */
function hash_password($password) {
    // Utiliser le salt du site pour renforcer la sécurité
    $pepper = SITE_KEY;
    $password_peppered = hash_hmac('sha256', $password, $pepper);
    
    // Utiliser password_hash avec un coût élevé (par défaut 10)
    return password_hash($password_peppered, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * Vérifie un mot de passe
 * @param string $password Mot de passe en clair
 * @param string $hash Hash stocké
 * @return bool Vrai si le mot de passe correspond
 */
function verify_password($password, $hash) {
    $pepper = SITE_KEY;
    $password_peppered = hash_hmac('sha256', $password, $pepper);
    
    return password_verify($password_peppered, $hash);
}

/**
 * Convertit une date au format français
 * @param string $date Date au format MySQL
 * @param bool $with_time Inclure l'heure
 * @return string Date formatée
 */
function format_date($date, $with_time = true) {
    $timestamp = strtotime($date);
    return $with_time ? 
        date('d/m/Y à H:i', $timestamp) : 
        date('d/m/Y', $timestamp);
}

/**
 * Journalise les tentatives d'accès
 * @param string $type Type d'accès (login, admin, etc.)
 * @param bool $status Succès ou échec
 * @param string $details Détails supplémentaires
 * @return bool Succès de l'opération
 */
function log_access_attempt($pdo, $type, $status, $details = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs 
                           (access_type, status, ip_address, user_agent, user_id, details) 
                           VALUES (?, ?, ?, ?, ?, ?)");
                           
        $stmt->execute([
            $type,
            $status ? 'success' : 'failure',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $_SESSION['user_id'] ?? null,
            $details
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur de journalisation: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un email est valide
 * @param string $email Adresse email à vérifier
 * @return bool Vrai si l'email est valide
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Vérifie si un numéro de téléphone est valide (format français)
 * @param string $phone Numéro de téléphone
 * @return bool Vrai si le téléphone est valide
 */
function is_valid_phone($phone) {
    // Nettoyer le numéro
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Format français: 10 chiffres commençant par 0, ou format international
    return (preg_match('/^0[1-9][0-9]{8}$/', $phone) || 
            preg_match('/^\+33[1-9][0-9]{8}$/', $phone));
}

/**
 * Redirection sécurisée
 * @param string $url URL de destination
 */
function secure_redirect($url) {
    // Assurer que l'URL est sécurisée (même domaine)
    if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)?qualipoele\.fr/', $url)) {
        $url = 'index.php'; // Redirection par défaut
    }
    
    header("Location: $url");
    exit;
}

/**
 * Vérifie si l'utilisateur est autorisé à voir une ressource
 * @param PDO $pdo Connexion PDO
 * @param int $resource_id ID de la ressource
 * @param string $resource_type Type de ressource
 * @return bool Autorisation
 */
function is_authorized($pdo, $resource_id, $resource_type = 'projet') {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        if ($resource_type === 'projet') {
            $stmt = $pdo->prepare("SELECT 1 FROM projets WHERE id = ? AND client_id = ? LIMIT 1");
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM messages WHERE id = ? AND client_id = ? LIMIT 1");
        }
        
        $stmt->execute([$resource_id, $_SESSION['user_id']]);
        return $stmt->fetchColumn() ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Envoie un email sécurisé
 * @param string $to Destinataire
 * @param string $subject Sujet
 * @param string $message Corps du message
 * @return bool Succès de l'envoi
 */
function send_secure_email($to, $subject, $message) {
    // En-têtes pour éviter les injections
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: Quali\'Poêle <contact@qualipoele.fr>',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1',
        'X-MSMail-Priority: High',
    ];
    
    // Sécuriser les données
    $to = filter_var($to, FILTER_SANITIZE_EMAIL);
    $subject = sanitize_input($subject);
    
    // Vérifier que le destinataire est valide
    if (!is_valid_email($to)) {
        return false;
    }
    
    // Envoyer l'email
    return mail($to, $subject, $message, implode("\r\n", $headers));
} 