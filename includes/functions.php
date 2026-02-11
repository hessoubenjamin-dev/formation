<?php
// Fonction pour formater les dates
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Fonction pour formater les montants
function formatAmount($amount) {
    global $currency_config;
    return number_format($amount, DECIMAL_PLACES, ',', ' ') . ' ' . CURRENCY_SYMBOL;
}

function formatAmountDecimal($amount, $decimals = 0) {
    return number_format($amount, $decimals, ',', ' ') . ' FCFA';
}

// Fonction pour générer un numéro de reçu
function generateReceiptNumber() {
    return 'RC-' . date('Ymd') . '-' . substr(md5(uniqid()), 0, 6);
}

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction pour rediriger si non connecté
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Fonction pour obtenir le solde d'un étudiant
function getStudentBalance($pdo, $student_id) {
    $sql = "SELECT total_amount, paid_amount, (total_amount - paid_amount) as balance 
            FROM students WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fonction pour obtenir le nom du mois en français
function getFrenchMonthName($date) {
    // Vérifier si la date est valide
    if (empty($date) || $date === '0000-00-00' || $date === null) {
        return '';
    }
    
    // Vérifier le format de date
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    
    $months_fr = [
        'January' => 'Janvier',
        'February' => 'Février',
        'March' => 'Mars',
        'April' => 'Avril',
        'May' => 'Mai',
        'June' => 'Juin',
        'July' => 'Juillet',
        'August' => 'Août',
        'September' => 'Septembre',
        'October' => 'Octobre',
        'November' => 'Novembre',
        'December' => 'Décembre'
    ];
    
    $english_month = date('F', $timestamp);
    return $months_fr[$english_month] ?? $english_month;
}

// Fonction pour formater un mois complet en français
function formatMonthYear($date) {
    if (empty($date) || $date === '0000-00-00' || $date === null) {
        return '';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    
    $month_fr = getFrenchMonthName($date);
    $year = date('Y', $timestamp);
    
    return $month_fr . ' ' . $year;
}
?>