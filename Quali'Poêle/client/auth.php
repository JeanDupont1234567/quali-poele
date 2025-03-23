<?php
// Empêcher l'accès direct au fichier
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès interdit');
}

/**
 * Classe gérant l'authentification des utilisateurs
 */
class Authentication {
    private $pdo;
    
    /**
     * Constructeur
     * @param PDO $pdo Instance PDO pour la connexion à la base de données
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Vérifie les identifiants d'un utilisateur
     * @param string $email Email de l'utilisateur
     * @param string $password Mot de passe en clair
     * @return array|false Données de l'utilisateur ou false
     */
    public function verify_credentials($email, $password) {
        // Vérifier l'email
        if (!is_valid_email($email)) {
            return false;
        }
        
        // Requête sécurisée avec requête préparée
        $stmt = $this->pdo->prepare("SELECT id, nom, prenom, email, password, telephone, code_postal 
                                     FROM clients 
                                     WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Si l'utilisateur n'existe pas
        if (!$user) {
            // Délai aléatoire pour prévenir les timing attacks
            usleep(random_int(300000, 600000)); // 300-600ms
            return false;
        }
        
        // Vérification du mot de passe avec la fonction de vérification sécurisée
        if (!verify_password($password, $user['password'])) {
            $this->log_failed_attempt($email);
            return false;
        }
        
        // Vérifier si le hash doit être mis à jour (si PHP a amélioré son algo)
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT, ['cost' => 12])) {
            $this->update_password_hash($user['id'], $password);
        }
        
        return $user;
    }
    
    /**
     * Met à jour le hash du mot de passe
     * @param int $user_id ID de l'utilisateur
     * @param string $password Mot de passe en clair
     * @return bool Succès de l'opération
     */
    private function update_password_hash($user_id, $password) {
        $new_hash = hash_password($password);
        
        try {
            $stmt = $this->pdo->prepare("UPDATE clients SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du hash: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enregistre une tentative de connexion échouée
     * @param string $email Email utilisé
     * @return void
     */
    private function log_failed_attempt($email) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        try {
            // Enregistrer la tentative
            $stmt = $this->pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
            $stmt->execute([$email, $ip]);
            
            // Vérifier si trop de tentatives (5 en 15 minutes)
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM login_attempts 
                                        WHERE (email = ? OR ip_address = ?) 
                                        AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$email, $ip]);
            $count = $stmt->fetchColumn();
            
            if ($count >= 5) {
                // Bloquer temporairement l'IP ou l'email
                $stmt = $this->pdo->prepare("INSERT INTO blocked_access (email, ip_address, blocked_until) 
                                            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE)) 
                                            ON DUPLICATE KEY UPDATE blocked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE)");
                $stmt->execute([$email, $ip]);
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de l'enregistrement de la tentative: " . $e->getMessage());
        }
    }
    
    /**
     * Vérifie si l'IP ou l'email est bloqué
     * @param string $email Email à vérifier
     * @return bool Vrai si bloqué
     */
    public function is_blocked($email) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM blocked_access 
                                        WHERE (email = ? OR ip_address = ?) 
                                        AND blocked_until > NOW() LIMIT 1");
            $stmt->execute([$email, $ip]);
            
            return $stmt->fetchColumn() ? true : false;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de blocage: " . $e->getMessage());
            return false; // Par défaut, considérer comme non bloqué en cas d'erreur
        }
    }
    
    /**
     * Crée une session utilisateur après connexion réussie
     * @param array $user Données de l'utilisateur
     * @return bool Succès de l'opération
     */
    public function create_user_session($user) {
        // Régénérer l'ID de session pour prévenir la fixation de session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        
        // Enregistrer les données de session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Générer et stocker un token de session pour validation supplémentaire
        $token = generate_token(32);
        $_SESSION['session_token'] = $token;
        
        try {
            // Stocker le token en base de données
            $stmt = $this->pdo->prepare("UPDATE clients 
                                        SET session_token = ?, token_expiration = DATE_ADD(NOW(), INTERVAL 1 DAY) 
                                        WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la création de session: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie si la session utilisateur est valide
     * @return bool Validité de la session
     */
    public function verify_session() {
        if (!isset($_SESSION['user_id']) || 
            !isset($_SESSION['user_ip']) || 
            !isset($_SESSION['user_agent']) ||
            !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Vérifier que l'IP et l'user agent n'ont pas changé
        if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] ||
            $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            $this->logout();
            return false;
        }
        
        // Vérifier l'expiration de la session
        if (time() - $_SESSION['login_time'] > SESSION_EXPIRY) {
            $this->logout();
            return false;
        }
        
        try {
            // Vérifier la validité du token en base de données
            $stmt = $this->pdo->prepare("SELECT 1 FROM clients 
                                        WHERE id = ? AND session_token = ? AND token_expiration > NOW() 
                                        LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
            
            if (!$stmt->fetchColumn()) {
                $this->logout();
                return false;
            }
            
            // Rafraîchir le temps de session
            $_SESSION['login_time'] = time();
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de session: " . $e->getMessage());
            $this->logout();
            return false;
        }
    }
    
    /**
     * Déconnecte l'utilisateur
     * @return bool Succès de l'opération
     */
    public function logout() {
        try {
            if (isset($_SESSION['user_id'])) {
                // Invalider le token en base de données
                $stmt = $this->pdo->prepare("UPDATE clients 
                                            SET session_token = NULL, token_expiration = NULL 
                                            WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la déconnexion: " . $e->getMessage());
        }
        
        // Détruire la session
        $_SESSION = array();
        
        // Détruire le cookie de session si présent
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Finalement, détruire la session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        return true;
    }
    
    /**
     * Crée un nouveau compte utilisateur
     * @param array $user_data Données de l'utilisateur
     * @return int|false ID du nouvel utilisateur ou false
     */
    public function register_user($user_data) {
        // Vérifier si l'email existe déjà
        $stmt = $this->pdo->prepare("SELECT 1 FROM clients WHERE email = ? LIMIT 1");
        $stmt->execute([$user_data['email']]);
        
        if ($stmt->fetchColumn()) {
            return false; // Email déjà utilisé
        }
        
        // Hasher le mot de passe
        $hashed_password = hash_password($user_data['password']);
        
        try {
            // Insertion avec requête préparée
            $stmt = $this->pdo->prepare("INSERT INTO clients 
                                        (nom, prenom, email, password, telephone, code_postal) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $user_data['nom'],
                $user_data['prenom'],
                $user_data['email'],
                $hashed_password,
                $user_data['telephone'],
                $user_data['code_postal']
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de l'inscription: " . $e->getMessage());
            return false;
        }
    }
}

// Créer l'instance d'authentification
$auth = new Authentication($pdo); 