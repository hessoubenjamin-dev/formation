<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (isset($_SESSION['user_id'])) {
    // Rediriger vers le tableau de bord
    header('Location: dashboard.php');
} else {
    // Rediriger vers la page de connexion
    header('Location: login.php');
}
exit();
?>