<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

function hasColumn(PDO $pdo, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    $cache[$key] = (int) $stmt->fetchColumn() > 0;

    return $cache[$key];
}

$has_payment_month = hasColumn($pdo, 'payments', 'payment_month');
$has_start_date = hasColumn($pdo, 'students', 'start_date');
$has_duration_months = hasColumn($pdo, 'students', 'duration_months');

$student_id = $_GET['student_id'] ?? 0;

// Récupérer les informations de l'étudiant
$sql_student = "SELECT * FROM students WHERE id = ?";
$stmt = $pdo->prepare($sql_student);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: students.php');
    exit();
}

// Récupérer les mois déjà payés
$paid_months = [];
if ($has_payment_month) {
    $sql_paid_months = "SELECT payment_month FROM payments 
                        WHERE student_id = ? AND payment_month IS NOT NULL 
                        ORDER BY payment_month";
    $stmt_months = $pdo->prepare($sql_paid_months);
    $stmt_months->execute([$student_id]);
    $paid_months = $stmt_months->fetchAll(PDO::FETCH_COLUMN);
}

// Fonction pour obtenir le nom du mois en français
function getFrenchMonthName($date) {
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
    
    $english_month = date('F', strtotime($date));
    return $months_fr[$english_month] ?? $english_month;
}

// Calculer les prochains mois à payer (2 mois en avant)
$current_date = new DateTime();
$months_to_pay = [];

// Ajouter le mois en cours
$current_month = $current_date->format('Y-m-01');
$months_to_pay[] = $current_month;

// Ajouter le mois suivant
$next_month = (clone $current_date)->modify('+1 month')->format('Y-m-01');
$months_to_pay[] = $next_month;

// Ajouter le mois d'après (2 mois en avant)
$next_next_month = (clone $current_date)->modify('+2 month')->format('Y-m-01');
$months_to_pay[] = $next_next_month;

// Si l'étudiant a une date de début et une durée, ajouter ces mois aussi
if ($has_start_date && !empty($student['start_date'])) {
    $start_date = new DateTime($student['start_date']);
    $duration = $has_duration_months && !empty($student['duration_months']) ? (int) $student['duration_months'] : 3;
    
    for ($i = 0; $i < $duration; $i++) {
        $month = (clone $start_date)->modify("+$i months")->format('Y-m-01');
        if (!in_array($month, $months_to_pay)) {
            $months_to_pay[] = $month;
        }
    }
}

// Trier les mois
sort($months_to_pay);

// Fonction pour suggérer le prochain mois à payer
function getNextMonthToPay($student_id, $pdo, $paid_months, $months_to_pay) {
    // Trouver le premier mois non payé
    foreach ($months_to_pay as $month) {
        if (!in_array($month, $paid_months)) {
            return $month;
        }
    }
    // Si tous les mois sont payés, retourner le mois suivant
    if (!empty($months_to_pay)) {
        $last_month = end($months_to_pay);
        return date('Y-m-01', strtotime($last_month . ' +1 month'));
    }
    // Sinon retourner le mois en cours
    return date('Y-m-01');
}

$suggested_month = getNextMonthToPay($student_id, $pdo, $paid_months, $months_to_pay);

// Récupérer l'historique des paiements pour calculer les échéances
$sql_history = "SELECT payment_date, amount" . ($has_payment_month ? ", payment_month" : "") . " FROM payments WHERE student_id = ? ORDER BY payment_date DESC";
$stmt_history = $pdo->prepare($sql_history);
$stmt_history->execute([$student_id]);
$payment_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $payment_month = $has_payment_month ? ($_POST['payment_month'] ?? null) : null;
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'];
    $receipt_number = generateReceiptNumber();
    
    // Démarrer une transaction
    $pdo->beginTransaction();
    
    try {
        // Insérer le paiement
        if ($has_payment_month) {
            $sql_payment = "INSERT INTO payments 
                            (student_id, amount, payment_date, payment_month, payment_method, receipt_number, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_params = [$student_id, $amount, $payment_date, $payment_month, $payment_method, $receipt_number, $notes];
        } else {
            $sql_payment = "INSERT INTO payments 
                            (student_id, amount, payment_date, payment_method, receipt_number, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            $insert_params = [$student_id, $amount, $payment_date, $payment_method, $receipt_number, $notes];
        }
        $stmt = $pdo->prepare($sql_payment);
        $stmt->execute($insert_params);
        
        // Mettre à jour le montant payé de l'étudiant
        $sql_update = "UPDATE students 
                      SET paid_amount = paid_amount + ?, 
                          balance = total_amount - (paid_amount + ?) 
                      WHERE id = ?";
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute([$amount, $amount, $student_id]);
        
        $pdo->commit();
        
        // Formater le mois en français pour le message
        $month_fr = $payment_month ? (getFrenchMonthName($payment_month) . ' ' . date('Y', strtotime($payment_month))) : 'non spécifié';
        
        // Redirection avec message de succès
        $_SESSION['success_message'] = "Paiement de " . formatAmount($amount) . " pour le mois de " . $month_fr . " enregistré avec succès!";
        header('Location: student_details.php?id=' . $student_id . '&success=1');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'enregistrement: " . $e->getMessage();
    }
}

// Calculer le solde restant
$balance = $student['total_amount'] - $student['paid_amount'];

// Calculer le pourcentage payé
$percentage_paid = $student['total_amount'] > 0 ? round(($student['paid_amount'] / $student['total_amount']) * 100, 1) : 0;
$percentage_remaining = 100 - $percentage_paid;

// Générer une suggestion de paiement (1/3 du solde restant)
$suggested_amount = round($balance / 3, 0); // Arrondi à l'entier pour FCFA

// Récupérer les paiements mensuels pour l'historique
$monthly_payments = [];
if ($has_payment_month) {
    $sql_monthly = "SELECT payment_month, amount, payment_date, payment_method 
                    FROM payments 
                    WHERE student_id = ? AND payment_month IS NOT NULL 
                    ORDER BY payment_month DESC";
    $stmt_monthly = $pdo->prepare($sql_monthly);
    $stmt_monthly->execute([$student_id]);
    $monthly_payments = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Paiement - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary: #4361ee;
        --primary-dark: #3a0ca3;
        --secondary: #4cc9f0;
        --success: #22c55e;
        --warning: #f97316;
        --danger: #ef4444;
        --light: #f8fafc;
        --dark: #1e293b;
        --gray: #64748b;
        --light-gray: #e2e8f0;
        --violet: #8b5cf6;
    }

    .payment-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--light-gray);
    }

    .page-title {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-title i {
        color: var(--primary);
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: white;
        color: var(--dark);
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        border: 1px solid var(--light-gray);
        transition: all 0.3s;
    }

    .back-btn:hover {
        background: var(--light);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-2px);
    }

    /* Student Summary Card */
    .student-summary {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .student-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 25px;
    }

    .student-identity {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .student-avatar {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 600;
        color: white;
    }

    .student-info h3 {
        font-size: 22px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 5px;
    }

    .student-info p {
        color: var(--gray);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .student-status {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }

    .status-paid {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
    }

    .status-partial {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .status-pending {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    /* Payment Progress */
    .payment-progress {
        background: var(--light);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .progress-title {
        font-weight: 600;
        color: var(--dark);
        font-size: 16px;
    }

    .progress-percentage {
        font-weight: 700;
        font-size: 20px;
        color: var(--primary);
    }

    .progress-bar-container {
        height: 10px;
        background: #e5e7eb;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--success), var(--primary));
        border-radius: 5px;
        transition: width 1s ease-in-out;
    }

    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        color: var(--gray);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--light-gray);
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card .value {
        font-size: 28px;
        font-weight: 700;
        margin: 10px 0;
    }

    .stat-card.total .value {
        color: var(--primary);
    }

    .stat-card.formation-price .value {
        color: var(--violet);
    }

    .stat-card.paid .value {
        color: var(--success);
    }

    .stat-card.balance .value {
        color: var(--danger);
    }

    .stat-card .label {
        font-size: 13px;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Payment Form */
    .payment-form-container {
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .form-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--light-gray);
    }

    .form-header i {
        color: var(--primary);
        font-size: 24px;
    }

    .form-header h2 {
        font-size: 22px;
        font-weight: 700;
        color: var(--dark);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 25px;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }

    .form-control {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.3s;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }

    .form-control:disabled {
        background: var(--light);
        cursor: not-allowed;
    }

    .form-hint {
        display: block;
        margin-top: 6px;
        font-size: 13px;
        color: var(--gray);
    }

    .amount-input-container {
        position: relative;
    }

    .currency-symbol {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        font-weight: 600;
        color: var(--gray);
    }

    .amount-input {
        padding-left: 35px;
    }

    /* Payment Method Cards */
    .payment-methods {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .payment-method-card {
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: white;
    }

    .payment-method-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .payment-method-card.selected {
        border-color: var(--primary);
        background: rgba(67, 97, 238, 0.05);
    }

    .payment-method-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 20px;
        color: white;
    }

    .payment-method-card[data-method="cash"] .payment-method-icon {
        background: linear-gradient(135deg, var(--success), #16a34a);
    }

    .payment-method-card[data-method="card"] .payment-method-icon {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
    }

    .payment-method-card[data-method="transfer"] .payment-method-icon {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    }

    .payment-method-card[data-method="check"] .payment-method-icon {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .payment-method-name {
        font-weight: 600;
        font-size: 14px;
        color: var(--dark);
    }

    /* Quick Amounts */
    .quick-amounts {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .quick-amount-btn {
        padding: 8px 16px;
        background: var(--light);
        border: 1px solid var(--light-gray);
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        color: var(--dark);
        cursor: pointer;
        transition: all 0.3s;
    }

    .quick-amount-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .quick-amount-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 30px;
        padding-top: 25px;
        border-top: 1px solid var(--light-gray);
    }

    .btn {
        padding: 14px 28px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-secondary {
        background: white;
        color: var(--dark);
        border: 2px solid var(--light-gray);
    }

    .btn-secondary:hover {
        background: var(--light);
        border-color: var(--gray);
        transform: translateY(-2px);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success), #16a34a);
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
    }

    /* Alert Messages */
    .alert {
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .alert-danger i {
        color: var(--danger);
    }

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    /* Receipt Preview */
    .receipt-preview {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-top: 30px;
        border: 2px dashed var(--light-gray);
        display: none;
    }

    .receipt-preview.active {
        display: block;
        animation: slideDown 0.5s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .receipt-header {
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--light-gray);
    }

    .receipt-header h3 {
        color: var(--primary);
        font-size: 20px;
        margin-bottom: 5px;
    }

    .receipt-number {
        font-weight: 600;
        color: var(--gray);
        font-size: 14px;
    }

    .receipt-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .receipt-item {
        margin-bottom: 15px;
    }

    .receipt-label {
        font-size: 13px;
        color: var(--gray);
        margin-bottom: 5px;
    }

    .receipt-value {
        font-weight: 600;
        color: var(--dark);
        font-size: 16px;
    }

    .receipt-footer {
        text-align: center;
        padding-top: 20px;
        border-top: 2px solid var(--light-gray);
        color: var(--gray);
        font-size: 13px;
    }

    /* Payment Months Summary */
    .payment-months-summary {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        border: 1px solid var(--light-gray);
    }

    .months-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .month-card {
        border: 1px solid var(--light-gray);
        border-radius: 10px;
        padding: 15px;
        transition: all 0.3s;
    }

    .month-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .month-card.current-month {
        border-color: var(--primary);
        background: rgba(67, 97, 238, 0.05);
    }

    .month-name {
        font-weight: 600;
        color: var(--dark);
        font-size: 16px;
        margin-bottom: 8px;
    }

    .month-amount {
        font-size: 18px;
        font-weight: 700;
        color: var(--success);
        margin-bottom: 5px;
    }

    .month-date {
        font-size: 12px;
        color: var(--gray);
        margin-bottom: 8px;
    }

    .month-method {
        font-size: 11px;
        color: var(--gray);
        margin-bottom: 8px;
        font-style: italic;
    }

    .month-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        padding: 3px 8px;
        border-radius: 12px;
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
    }

    .no-months {
        text-align: center;
        padding: 30px;
        color: var(--gray);
    }

    .no-months i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .payment-container {
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .student-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .payment-methods {
            grid-template-columns: repeat(2, 1fr);
        }

        .months-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="payment-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-cash-register"></i>
                    Nouveau Paiement Mensuel
                </h1>
                <p style="color: var(--gray); margin-top: 5px;">Enregistrement d'un nouveau paiement (mois en cours + 2
                    mois en avant)</p>
            </div>
            <a href="student_details.php?id=<?php echo $student_id; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Retour aux détails
            </a>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Erreur:</strong> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Student Summary -->
        <div class="student-summary">
            <div class="student-header">
                <div class="student-identity">
                    <div class="student-avatar">
                        <?php 
                        echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
                        ?>
                    </div>
                    <div class="student-info">
                        <h3><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                        <p>
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($student['email']); ?>
                        </p>
                        <p>
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($student['phone'] ?: 'Non renseigné'); ?>
                        </p>
                        <?php if ($has_start_date && !empty($student['start_date'])): ?>
                        <p>
                            <i class="fas fa-calendar-alt"></i>
                            Début formation: <?php echo date('d/m/Y', strtotime($student['start_date'])); ?>
                            | Durée: <?php echo $student['duration_months'] ?? 3; ?> mois
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div
                    class="student-status <?php echo $balance == 0 ? 'status-paid' : ($percentage_paid > 0 ? 'status-partial' : 'status-pending'); ?>">
                    <i
                        class="fas fa-<?php echo $balance == 0 ? 'check-circle' : ($percentage_paid > 0 ? 'clock' : 'exclamation-circle'); ?>"></i>
                    <?php echo $balance == 0 ? 'Entièrement payé' : ($percentage_paid > 0 ? 'Paiement partiel' : 'En attente'); ?>
                </div>
            </div>

            <!-- Payment Progress -->
            <div class="payment-progress">
                <div class="progress-header">
                    <div class="progress-title">Progression du paiement</div>
                    <div class="progress-percentage"><?php echo $percentage_paid; ?>%</div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo $percentage_paid; ?>%"></div>
                </div>
                <div class="progress-labels">
                    <span><?php echo formatAmount($student['paid_amount']); ?> payé</span>
                    <span><?php echo formatAmount($balance); ?> restant</span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="value"><?php echo formatAmount($student['total_amount']); ?></div>
                    <div class="label">Montant Total</div>
                </div>
                <div class="stat-card formation-price">
                    <div class="value">
                        <?php 
                        $formation_price = $student['formation_price'] ?? $student['total_amount'];
                        echo formatAmount($formation_price); 
                        ?>
                    </div>
                    <div class="label">Prix Formation</div>
                </div>
                <div class="stat-card paid">
                    <div class="value"><?php echo formatAmount($student['paid_amount']); ?></div>
                    <div class="label">Déjà Payé</div>
                </div>
                <div class="stat-card balance">
                    <div class="value"><?php echo formatAmount($balance); ?></div>
                    <div class="label">Reste à Payer</div>
                </div>
            </div>

            <!-- Historique des mois payés -->
            <?php if ($has_payment_month): ?>
            <div class="payment-months-summary">
                <h4 style="margin-bottom: 15px; color: var(--dark);">
                    <i class="fas fa-calendar-alt"></i> Historique des mois payés
                </h4>

                <?php if ($monthly_payments): ?>
                <div class="months-grid">
                    <?php foreach ($monthly_payments as $payment): 
                        $is_current_month = date('Y-m', strtotime($payment['payment_month'])) == date('Y-m');
                        $month_display = getFrenchMonthName($payment['payment_month']) . ' ' . date('Y', strtotime($payment['payment_month']));
                    ?>
                    <div class="month-card <?php echo $is_current_month ? 'current-month' : ''; ?>">
                        <div class="month-name"><?php echo $month_display; ?></div>
                        <div class="month-amount"><?php echo formatAmount($payment['amount']); ?></div>
                        <div class="month-date">Payé le:
                            <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></div>
                        <div class="month-method">Méthode: <?php echo htmlspecialchars($payment['payment_method']); ?>
                        </div>
                        <div class="month-status">
                            <i class="fas fa-check-circle"></i> Payé
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-months">
                    <i class="fas fa-calendar-times"></i>
                    <p>Aucun mois payé pour le moment</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Payment Form -->
        <div class="payment-form-container">
            <div class="form-header">
                <i class="fas fa-file-invoice-dollar"></i>
                <h2>Détails du paiement mensuel</h2>
            </div>

            <form method="POST" id="paymentForm">
                <div class="form-row">
                    <!-- Left Column -->
                    <div>
                        <!-- Month Selection -->
                        <?php if ($has_payment_month): ?>
                        <div class="form-group">
                            <label class="form-label">Mois à payer</label>
                            <select name="payment_month" id="paymentMonth" class="form-control" required onchange="updateReceiptPreview()">
                                <option value="">Sélectionner le mois</option>

                                <?php
        // Option 1: Afficher tous les mois de l'année en cours
        $current_year = date('Y');
        for ($i = 1; $i <= 12; $i++) {
            $month = sprintf('%d-%02d-01', $current_year, $i);
            $month_display = getFrenchMonthName($month) . ' ' . $current_year;
            $is_paid = in_array($month, $paid_months);
            $is_suggested = $month == $suggested_month && !$is_paid;
            $is_current_month = date('Y-m', strtotime($month)) == date('Y-m');
        ?>
                                <option value="<?php echo $month; ?>"
                                    <?php echo $is_paid ? 'disabled style="color:#ccc"' : ''; ?>
                                    <?php echo $is_suggested ? 'selected' : ''; ?>
                                    <?php echo $is_current_month ? 'data-current="true"' : ''; ?>>
                                    <?php echo $month_display; ?>
                                    <?php if ($is_paid): ?>
                                    ✓ (Déjà payé)
                                    <?php elseif ($is_current_month): ?>
                                    📅 (Mois en cours)
                                    <?php elseif ($is_suggested): ?>
                                    ⭐ (Suggéré)
                                    <?php endif; ?>
                                </option>
                                <?php } ?>

                                <?php
        // Option 2: Afficher aussi les mois de l'année suivante
        $next_year = $current_year + 1;
        for ($i = 1; $i <= 12; $i++) {
            $month = sprintf('%d-%02d-01', $next_year, $i);
            $month_display = getFrenchMonthName($month) . ' ' . $next_year;
            $is_paid = in_array($month, $paid_months);
            $is_suggested = $month == $suggested_month && !$is_paid;
        ?>
                                <option value="<?php echo $month; ?>"
                                    <?php echo $is_paid ? 'disabled style="color:#ccc"' : ''; ?>
                                    <?php echo $is_suggested ? 'selected' : ''; ?>>
                                    <?php echo $month_display; ?>
                                    <?php echo $is_paid ? '✓ (Déjà payé)' : ''; ?>
                                    <?php echo $is_suggested ? '⭐ (Suggéré)' : ''; ?>
                                </option>
                                <?php } ?>
                            </select>
                            <span class="form-hint">
                                ⭐ Le mois suggéré est le premier mois non payé
                            </span>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="payment_month" id="paymentMonth" value="">
                        <?php endif; ?>

                        <!-- Amount -->
                        <div class="form-group">
                            <label class="form-label">Montant du paiement (FCFA)</label>
                            <div class="amount-input-container">
                                <span class="currency-symbol">FCFA</span>
                                <input type="number" name="amount" id="amountInput" step="1"
                                    class="form-control amount-input" required min="1" max="<?php echo $balance; ?>"
                                    value="<?php echo $suggested_amount; ?>" onchange="updateReceiptPreview()">
                            </div>
                            <span class="form-hint">
                                Maximum: <strong><?php echo formatAmount($balance); ?></strong>
                                <?php if ($balance > 0): ?>
                                | Solde après paiement: <strong
                                    id="remainingBalance"><?php echo formatAmount($balance - $suggested_amount); ?></strong>
                                <?php endif; ?>
                            </span>

                            <!-- Quick Amounts -->
                            <?php if ($balance > 0): ?>
                            <div class="quick-amounts" style="margin-top: 10px;">
                                <button type="button" class="quick-amount-btn" data-amount="25000">25 000 FCFA</button>
                                <button type="button" class="quick-amount-btn" data-amount="50000">50 000 FCFA</button>
                                <button type="button" class="quick-amount-btn" data-amount="100000">100 000
                                    FCFA</button>
                                <button type="button" class="quick-amount-btn" data-amount="150000">150 000
                                    FCFA</button>
                                <button type="button" class="quick-amount-btn" data-amount="200000">200 000
                                    FCFA</button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Payment Date -->
                        <div class="form-group">
                            <label class="form-label">Date du paiement</label>
                            <input type="date" name="payment_date" id="paymentDate" class="form-control" required
                                value="<?php echo date('Y-m-d'); ?>" onchange="updateReceiptPreview()">
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div>
                        <!-- Receipt Number -->
                        <div class="form-group">
                            <label class="form-label">Numéro de reçu</label>
                            <input type="text" class="form-control" value="<?php echo generateReceiptNumber(); ?>"
                                id="receiptNumber" readonly>
                            <span class="form-hint">Généré automatiquement - Non modifiable</span>
                        </div>

                        <!-- Payment Method -->
                        <div class="form-group">
                            <label class="form-label">Méthode de paiement</label>
                            <div class="payment-methods">
                                <div class="payment-method-card selected" data-method="cash"
                                    onclick="selectPaymentMethod('cash')">
                                    <div class="payment-method-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="payment-method-name">Espèces</div>
                                </div>
                                <div class="payment-method-card" data-method="card"
                                    onclick="selectPaymentMethod('card')">
                                    <div class="payment-method-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="payment-method-name">Carte</div>
                                </div>
                                <div class="payment-method-card" data-method="transfer"
                                    onclick="selectPaymentMethod('transfer')">
                                    <div class="payment-method-icon">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="payment-method-name">Virement</div>
                                </div>
                                <div class="payment-method-card" data-method="check"
                                    onclick="selectPaymentMethod('check')">
                                    <div class="payment-method-icon">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </div>
                                    <div class="payment-method-name">Chèque</div>
                                </div>
                            </div>
                            <input type="hidden" name="payment_method" id="paymentMethod" value="Espèces" required>
                        </div>

                        <!-- Notes -->
                        <div class="form-group">
                            <label class="form-label">Notes (optionnel)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="4"
                                placeholder="Ajoutez des notes concernant ce paiement..."
                                onchange="updateReceiptPreview()"></textarea>
                            <span class="form-hint">Ces notes apparaîtront sur le reçu</span>
                        </div>
                    </div>
                </div>

                <!-- Receipt Preview -->
                <div class="receipt-preview" id="receiptPreview">
                    <div class="receipt-header">
                        <h3>Aperçu du reçu</h3>
                        <div class="receipt-number" id="previewReceiptNumber"></div>
                    </div>
                    <div class="receipt-body">
                        <div>
                            <div class="receipt-item">
                                <div class="receipt-label">Étudiant</div>
                                <div class="receipt-value">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label">Formation</div>
                                <div class="receipt-value"><?php echo htmlspecialchars($student['formation_type']); ?>
                                </div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label">Date paiement</div>
                                <div class="receipt-value" id="previewDate"></div>
                            </div>
                        </div>
                        <div>
                            <div class="receipt-item">
                                <div class="receipt-label">Montant</div>
                                <div class="receipt-value" id="previewAmount"></div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label">Mois payé</div>
                                <div class="receipt-value" id="previewMonth"></div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label">Méthode</div>
                                <div class="receipt-value" id="previewMethod"></div>
                            </div>
                        </div>
                    </div>
                    <div class="receipt-footer">
                        Ceci est un aperçu du reçu qui sera généré après validation.
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="previewBtn">
                        <i class="fas fa-eye"></i>
                        Aperçu
                    </button>
                    <button type="submit" name="add_payment" class="btn btn-success">
                        <i class="fas fa-check-circle"></i>
                        Enregistrer le paiement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
    // Format currency in FCFA
    function formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    }

    // Format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // Format month in French
    function formatMonth(monthString) {
        if (!monthString) return 'Non spécifié';
        const date = new Date(monthString);

        // Map English month names to French
        const monthNames = {
            'January': 'Janvier',
            'February': 'Février',
            'March': 'Mars',
            'April': 'Avril',
            'May': 'Mai',
            'June': 'Juin',
            'July': 'Juillet',
            'August': 'Août',
            'September': 'Septembre',
            'October': 'Octobre',
            'November': 'Novembre',
            'December': 'Décembre'
        };

        const monthName = date.toLocaleDateString('fr-FR', {
            month: 'long'
        });
        const year = date.getFullYear();

        return monthName.charAt(0).toUpperCase() + monthName.slice(1) + ' ' + year;
    }

    // Select payment method
    function selectPaymentMethod(method) {
        // Remove selected class from all cards
        document.querySelectorAll('.payment-method-card').forEach(card => {
            card.classList.remove('selected');
        });

        // Add selected class to clicked card
        event.currentTarget.classList.add('selected');

        // Update hidden input value
        const methodNames = {
            'cash': 'Espèces',
            'card': 'Carte',
            'transfer': 'Virement',
            'check': 'Chèque'
        };

        document.getElementById('paymentMethod').value = methodNames[method];
        updateReceiptPreview();
    }

    // Quick amount buttons
    document.querySelectorAll('.quick-amount-btn').forEach(button => {
        button.addEventListener('click', function() {
            const amount = parseInt(this.dataset.amount);
            document.getElementById('amountInput').value = amount;

            // Update active state
            document.querySelectorAll('.quick-amount-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');

            updateReceiptPreview();
        });
    });

    // Update remaining balance
    document.getElementById('amountInput').addEventListener('input', function() {
        const amount = parseInt(this.value) || 0;
        const balance = <?php echo $balance; ?>;
        const remaining = balance - amount;

        const remainingBalanceEl = document.getElementById('remainingBalance');
        if (remainingBalanceEl) {
            remainingBalanceEl.textContent = formatCurrency(Math.max(0, remaining));
        }

        // Update quick buttons active state
        document.querySelectorAll('.quick-amount-btn').forEach(btn => {
            btn.classList.remove('active');
        });
    });

    // Update receipt preview
    function updateReceiptPreview() {
        const amount = parseInt(document.getElementById('amountInput').value) || 0;
        const date = document.getElementById('paymentDate').value;
        const monthEl = document.getElementById('paymentMonth');
        const month = monthEl ? monthEl.value : '';
        const method = document.getElementById('paymentMethod').value;
        const receiptNumber = document.getElementById('receiptNumber').value;
        const notes = document.getElementById('notes').value;

        // Update preview elements
        document.getElementById('previewAmount').textContent = formatCurrency(amount);
        document.getElementById('previewDate').textContent = formatDate(date);
        document.getElementById('previewMonth').textContent = formatMonth(month);
        document.getElementById('previewMethod').textContent = method;
        document.getElementById('previewReceiptNumber').textContent = 'Reçu #' + receiptNumber;

        // Show preview if amount > 0 and month selected
        if (amount > 0 && (!monthEl || month)) {
            document.getElementById('receiptPreview').classList.add('active');
        } else {
            document.getElementById('receiptPreview').classList.remove('active');
        }
    }

    // Preview button
    document.getElementById('previewBtn').addEventListener('click', function() {
        updateReceiptPreview();
        document.getElementById('receiptPreview').scrollIntoView({
            behavior: 'smooth'
        });
    });

    // Form validation
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        const amount = parseInt(document.getElementById('amountInput').value) || 0;
        const balance = <?php echo $balance; ?>;
        const monthEl = document.getElementById('paymentMonth');
        const month = monthEl ? monthEl.value : '';

        if (monthEl && monthEl.required && !month) {
            e.preventDefault();
            alert('Veuillez sélectionner un mois.');
            return;
        }

        if (amount <= 0) {
            e.preventDefault();
            alert('Veuillez entrer un montant valide supérieur à 0.');
            return;
        }

        if (amount > balance) {
            e.preventDefault();
            alert('Le montant ne peut pas dépasser le solde restant de ' + formatCurrency(balance) + '.');
            return;
        }

        const monthText = month ? (' pour le mois de ' + formatMonth(month)) : '';
        if (!confirm('Confirmez-vous l\'enregistrement de ce paiement de ' + formatCurrency(amount) + monthText + ' ?')) {
            e.preventDefault();
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Generate new receipt number
        const timestamp = Date.now();
        const random = Math.floor(Math.random() * 10000);
        const receiptNumber = 'RC-' + timestamp.toString().slice(-6) + '-' + random.toString().padStart(4, '0');
        document.getElementById('receiptNumber').value = receiptNumber;

        // Initial preview update
        updateReceiptPreview();

        // Animate progress bar
        const progressBar = document.querySelector('.progress-bar');
        progressBar.style.width = '0%';
        setTimeout(() => {
            progressBar.style.width = '<?php echo $percentage_paid; ?>%';
        }, 300);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter to submit
        if (e.ctrlKey && e.key === 'Enter') {
            document.querySelector('button[name="add_payment"]').click();
        }

        // Escape to go back
        if (e.key === 'Escape') {
            window.history.back();
        }
    });
    </script>
</body>

</html>
