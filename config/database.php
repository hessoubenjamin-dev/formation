<?php
session_start();

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'u156402572_formation');
define('DB_USER', 'u156402572_user_formation');
define('DB_PASS', '05878442Benja@');

// Configuration du site
define('SITE_NAME', 'Gestion des Paiements Formation');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/');
define('COMPANY_LOGO_PATH', 'assets/images/logo.png');

// Connexion à la base de données
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données: ' . $e->getMessage());
}


require_once __DIR__ . '/../includes/training_schema.php';
initializeTrainingSchema($pdo);

// Inclure la configuration de la devise
require_once 'currency.php';
?>