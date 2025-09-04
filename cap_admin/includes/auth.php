<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function login($email, $password) {
        $hashedPassword = md5($password);
        
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND password = ?", 
            [$email, $hashedPassword]
        );
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['ime_prezime'];
            $_SESSION['user_type'] = $user['tip_korisnika'];
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'upravitelj';
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name'],
            'type' => $_SESSION['user_type']
        ];
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function requireAdmin() {
        if (!$this->isAdmin()) {
            header('Location: login.php');
            exit;
        }
    }
}
?>