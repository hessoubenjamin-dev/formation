<?php
require_once 'config/database.php';

// Fonction de connexion
function login($username, $password) {
    global $pdo;
    
    $sql = "SELECT id, username, password, full_name, role FROM users WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    
    return false;
}

// Fonction de déconnexion
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}
?>