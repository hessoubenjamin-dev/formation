<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

$student_id = $_GET['id'] ?? 0;

// Récupérer les informations de l'étudiant
$sql_student = "SELECT * FROM students WHERE id = ?";
$stmt = $pdo->prepare($sql_student);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: students.php');
    exit();
}

// Récupérer l'historique des paiements
$sql_payments = "SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC";
$stmt = $pdo->prepare($sql_payments);
$stmt->execute([$student_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$total_paid = $student['paid_amount'];
$balance = $student['total_amount'] - $student['paid_amount'];
$percentage_paid = $student['total_amount'] > 0 ? round(($student['paid_amount'] / $student['total_amount']) * 100, 1) : 0;
$percentage_remaining = 100 - $percentage_paid;

// Calculer les tendances (moyenne des paiements)
$payment_count = count($payments);
$average_payment = $payment_count > 0 ? round($total_paid / $payment_count, 2) : 0;

// Dernier paiement
$last_payment = $payments[0] ?? null;
$days_since_last_payment = $last_payment ? floor((time() - strtotime($last_payment['payment_date'])) / (60 * 60 * 24)) : null;

// Mettre à jour l'étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $formation_type = $_POST['formation_type'];
    $total_amount = $_POST['total_amount'];
    
    $sql = "UPDATE students SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?, 
            formation_type = ?, 
            total_amount = ?,
            balance = ? - paid_amount
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$first_name, $last_name, $email, $phone, $formation_type, $total_amount, $total_amount, $student_id])) {
        $_SESSION['success_message'] = "Informations de l'étudiant mises à jour avec succès!";
        header("Location: student_details.php?id=$student_id&success=1");
        exit();
    } else {
        $error_message = "Erreur lors de la mise à jour des informations.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> -
        <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    }

    .student-details-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 30px;
    }

    /* Page Header */
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

    .header-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success), #16a34a);
        color: white;
        box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
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

    .btn-danger {
        background: linear-gradient(135deg, var(--danger), #dc2626);
        color: white;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
    }

    /* Student Header */
    .student-header {
        background: white;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .student-profile {
        display: flex;
        align-items: center;
        gap: 25px;
        margin-bottom: 30px;
    }

    .student-avatar-lg {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        font-weight: 700;
        color: white;
        box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
    }

    .student-info {
        flex: 1;
    }

    .student-name {
        font-size: 28px;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 5px;
    }

    .student-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 15px;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--gray);
        font-size: 14px;
    }

    .student-status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-top: 10px;
    }

    .status-fully-paid {
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
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--light-gray);
        transition: transform 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 6px;
        height: 100%;
    }

    .stat-card.total::before {
        background: linear-gradient(to bottom, var(--primary), var(--primary-dark));
    }

    .stat-card.paid::before {
        background: linear-gradient(to bottom, var(--success), #16a34a);
    }

    .stat-card.balance::before {
        background: linear-gradient(to bottom, var(--danger), #dc2626);
    }

    .stat-card.average::before {
        background: linear-gradient(to bottom, var(--warning), #ea580c);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
        font-size: 20px;
        color: white;
    }

    .stat-card.total .stat-icon {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .stat-card.paid .stat-icon {
        background: linear-gradient(135deg, var(--success), #16a34a);
    }

    .stat-card.balance .stat-icon {
        background: linear-gradient(135deg, var(--danger), #dc2626);
    }

    .stat-card.average .stat-icon {
        background: linear-gradient(135deg, var(--warning), #ea580c);
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        margin: 10px 0;
    }

    .stat-label {
        font-size: 14px;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Progress Section */
    .progress-section {
        background: white;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .section-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .progress-percentage {
        font-size: 32px;
        font-weight: 800;
        color: var(--primary);
    }

    .progress-bar-container {
        height: 16px;
        background: var(--light-gray);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 15px;
        position: relative;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--success), var(--primary));
        border-radius: 8px;
        transition: width 1.5s ease-in-out;
        position: relative;
    }

    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        color: var(--gray);
    }

    .progress-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }

    .progress-detail {
        text-align: center;
        padding: 20px;
        background: var(--light);
        border-radius: 10px;
    }

    .progress-detail .value {
        font-size: 24px;
        font-weight: 700;
        color: var(--dark);
        margin: 10px 0;
    }

    .progress-detail .label {
        font-size: 13px;
        color: var(--gray);
    }

    /* Payment History */
    .payment-history {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
    }

    .history-header {
        padding: 25px;
        border-bottom: 1px solid var(--light-gray);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .history-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .history-actions {
        display: flex;
        gap: 10px;
    }

    .payment-table {
        width: 100%;
        border-collapse: collapse;
    }

    .payment-table thead {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .payment-table th {
        padding: 18px 16px;
        text-align: left;
        font-weight: 600;
        color: var(--gray);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--light-gray);
    }

    .payment-table tbody tr {
        border-bottom: 1px solid var(--light-gray);
        transition: background-color 0.2s;
    }

    .payment-table tbody tr:hover {
        background-color: rgba(67, 97, 238, 0.03);
    }

    .payment-table td {
        padding: 18px 16px;
        vertical-align: middle;
    }

    .payment-method {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .method-cash {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
    }

    .method-card {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }

    .method-transfer {
        background: rgba(168, 85, 247, 0.1);
        color: #9333ea;
    }

    .method-check {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .receipt-number {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: var(--primary);
    }

    .payment-actions {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--light);
        color: var(--gray);
        border: none;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-icon:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
    }

    /* Chart Container */
    .chart-container {
        background: white;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .chart-wrapper {
        height: 300px;
        margin-top: 20px;
    }

    /* Edit Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        padding: 25px;
        border-bottom: 1px solid var(--light-gray);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 20px;
        color: var(--gray);
        cursor: pointer;
        transition: color 0.3s;
    }

    .modal-close:hover {
        color: var(--danger);
    }

    .modal-body {
        padding: 25px;
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
        padding: 12px 16px;
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 30px;
        padding-top: 25px;
        border-top: 1px solid var(--light-gray);
    }

    /* Alert Messages */
    .alert {
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
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

    .alert-success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    .alert-success i {
        color: var(--success);
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .alert-danger i {
        color: var(--danger);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray);
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        color: var(--light-gray);
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--dark);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .student-details-container {
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .student-profile {
            flex-direction: column;
            text-align: center;
        }

        .student-meta {
            justify-content: center;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .payment-table {
            display: block;
            overflow-x: auto;
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .progress-details {
            grid-template-columns: 1fr;
        }

        .history-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .history-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .payment-table th:nth-child(5),
        .payment-table td:nth-child(5) {
            display: none;
        }
    }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="student-details-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-user-graduate"></i>
                    Détails de l'étudiant
                </h1>
                <p style="color: var(--gray); margin-top: 5px;">Gestion complète des informations et paiements</p>
            </div>
            <div class="header-actions">
                <a href="add_payment.php?student_id=<?php echo $student_id; ?>" class="btn-action btn-success">
                    <i class="fas fa-cash-register"></i>
                    Nouveau Paiement
                </a>
                <button class="btn-action btn-primary" onclick="openEditModal()">
                    <i class="fas fa-edit"></i>
                    Modifier
                </button>
                <a href="students.php" class="btn-action btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour à la liste
                </a>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <button class="btn-action btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i>
                    Supprimer
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Student Header -->
        <div class="student-header">
            <div class="student-profile">
                <div class="student-avatar-lg">
                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                </div>
                <div class="student-info">
                    <h2 class="student-name">
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                    <div class="student-meta">
                        <div class="meta-item">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($student['email']); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($student['phone'] ?: 'Non renseigné'); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <?php echo htmlspecialchars($student['formation_type']); ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            Inscrit le <?php echo formatDate($student['registration_date']); ?>
                        </div>
                    </div>
                    <div
                        class="student-status <?php echo $balance == 0 ? 'status-fully-paid' : ($student['paid_amount'] > 0 ? 'status-partial' : 'status-pending'); ?>">
                        <i
                            class="fas fa-<?php echo $balance == 0 ? 'check-circle' : ($student['paid_amount'] > 0 ? 'clock' : 'exclamation-circle'); ?>"></i>
                        <?php echo $balance == 0 ? 'Formation entièrement payée' : ($student['paid_amount'] > 0 ? 'Paiement partiel' : 'En attente de paiement'); ?>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            // Modifier les statistiques pour afficher FCFA
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <div class="stat-value"><?php echo formatAmount($student['total_amount']); ?></div>
                    <div class="stat-label">Montant total</div>
                </div>
                <div class="stat-card paid">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo formatAmount($student['paid_amount']); ?></div>
                    <div class="stat-label">Déjà payé</div>
                </div>
                <div class="stat-card balance">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo formatAmount($balance); ?></div>
                    <div class="stat-label">Reste à payer</div>
                </div>
                <div class="stat-card average">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo formatAmount($average_payment); ?></div>
                    <div class="stat-label">Moyenne par paiement</div>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="progress-section">
            <div class="progress-header">
                <h3 class="section-title">
                    <i class="fas fa-chart-pie"></i>
                    Progression du paiement
                </h3>
                <div class="progress-percentage"><?php echo $percentage_paid; ?>%</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar" style="width: 0%"></div>
            </div>
            <div class="progress-labels">
                <span><?php echo formatAmount($student['paid_amount']); ?> payé</span>
                <span><?php echo formatAmount($balance); ?> restant</span>
            </div>
            <div class="progress-details">
                <div class="progress-detail">
                    <div class="label">Total à payer</div>
                    <div class="value"><?php echo formatAmount($student['total_amount']); ?></div>
                </div>
                <div class="progress-detail">
                    <div class="label">Nombre de paiements</div>
                    <div class="value"><?php echo $payment_count; ?></div>
                </div>
                <div class="progress-detail">
                    <div class="label">Dernier paiement</div>
                    <div class="value">
                        <?php echo $last_payment ? formatDate($last_payment['payment_date']) : 'Aucun'; ?></div>
                </div>
                <div class="progress-detail">
                    <div class="label">Statut</div>
                    <div class="value">
                        <?php echo $balance == 0 ? 'Complet' : ($student['paid_amount'] > 0 ? 'Partiel' : 'En attente'); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($payment_count > 0): ?>
        <!-- Chart Section -->
        <div class="chart-container">
            <h3 class="section-title">
                <i class="fas fa-chart-bar"></i>
                Historique des paiements
            </h3>
            <div class="chart-wrapper">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment History -->
        <div class="payment-history">
            <div class="history-header">
                <h3 class="history-title">
                    <i class="fas fa-history"></i>
                    Historique détaillé des paiements
                </h3>
                <div class="history-actions">
                    <a href="export_payments.php?student_id=<?php echo $student_id; ?>"
                        class="btn-action btn-secondary">
                        <i class="fas fa-file-export"></i>
                        Exporter
                    </a>
                    <button class="btn-action btn-primary" onclick="printPaymentHistory()">
                        <i class="fas fa-print"></i>
                        Imprimer
                    </button>
                </div>
            </div>

            <?php if (empty($payments)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>Aucun paiement enregistré</h3>
                <p>Cet étudiant n'a pas encore effectué de paiement.</p>
                <a href="add_payment.php?student_id=<?php echo $student_id; ?>" class="btn-action btn-success"
                    style="margin-top: 20px;">
                    <i class="fas fa-cash-register"></i>
                    Enregistrer le premier paiement
                </a>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>N° Reçu</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): 
                            $method_class = strtolower($payment['payment_method']);
                            if ($method_class === 'espèces') $method_class = 'cash';
                            if ($method_class === 'carte') $method_class = 'card';
                            if ($method_class === 'virement') $method_class = 'transfer';
                            if ($method_class === 'chèque') $method_class = 'check';
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--dark);">
                                    <?php echo formatDate($payment['payment_date']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--gray);">
                                    <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                                </div>
                            </td>
                            <td style="font-weight: 700; color: var(--success);">
                                <?php echo formatAmount($payment['amount']); ?>
                            </td>
                            <td>
                                <span class="payment-method method-<?php echo $method_class; ?>">
                                    <?php if ($method_class === 'cash'): ?>
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?php elseif ($method_class === 'card'): ?>
                                    <i class="fas fa-credit-card"></i>
                                    <?php elseif ($method_class === 'transfer'): ?>
                                    <i class="fas fa-university"></i>
                                    <?php elseif ($method_class === 'check'): ?>
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($payment['payment_method']); ?>
                                </span>
                            </td>
                            <td class="receipt-number"><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                            <td>
                                <?php if ($payment['notes']): ?>
                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                    title="<?php echo htmlspecialchars($payment['notes']); ?>">
                                    <?php echo htmlspecialchars($payment['notes']); ?>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="payment-actions">
                                    <button class="btn-icon" title="Voir le reçu"
                                        onclick="viewReceipt(<?php echo $payment['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" title="Éditer"
                                        onclick="editPayment(<?php echo $payment['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($_SESSION['role'] == 'admin'): ?>
                                    <button class="btn-icon" title="Supprimer"
                                        onclick="deletePayment(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['receipt_number']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--light);">
                            <td colspan="6" style="padding: 20px; text-align: right;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="font-weight: 600; color: var(--dark);">
                                        <?php echo $payment_count; ?>
                                        paiement<?php echo $payment_count > 1 ? 's' : ''; ?> au total
                                    </div>
                                    <div style="font-size: 18px; font-weight: 700; color: var(--success);">
                                        Total payé: <?php echo formatAmount($student['paid_amount']); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-edit"></i>
                    Modifier l'étudiant
                </h3>
                <button class="modal-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Prénom *</label>
                            <input type="text" name="first_name" class="form-control" required
                                value="<?php echo htmlspecialchars($student['first_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="last_name" class="form-control" required
                                value="<?php echo htmlspecialchars($student['last_name']); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required
                                value="<?php echo htmlspecialchars($student['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" name="phone" class="form-control"
                                value="<?php echo htmlspecialchars($student['phone']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type de formation *</label>
                        <input type="text" name="formation_type" class="form-control" required
                            value="<?php echo htmlspecialchars($student['formation_type']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Montant total de la formation *</label>
                        <input type="number" name="total_amount" step="0.01" class="form-control" required
                            value="<?php echo $student['total_amount']; ?>">
                        <small style="color: var(--gray); font-size: 13px; display: block; margin-top: 5px;">
                            Note: Modifier ce montant recalculera automatiquement le solde restant.
                        </small>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-action btn-secondary" onclick="closeEditModal()">
                            Annuler
                        </button>
                        <button type="submit" name="update_student" class="btn-action btn-primary">
                            <i class="fas fa-save"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }

    // Modal functions
    function openEditModal() {
        document.getElementById('editModal').classList.add('active');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    function confirmDelete() {
        if (confirm(
                'Êtes-vous sûr de vouloir supprimer cet étudiant ? Cette action supprimera également tous ses paiements associés.'
            )) {
            window.location.href = 'delete_student.php?id=<?php echo $student_id; ?>';
        }
    }

    function viewReceipt(paymentId) {
        window.open('receipt.php?id=' + paymentId, '_blank');
    }

    function editPayment(paymentId) {
        // Implement edit payment functionality
        alert('Modification du paiement #' + paymentId + ' - Fonctionnalité à implémenter');
    }

    function deletePayment(paymentId, receiptNumber) {
        if (confirm('Êtes-vous sûr de vouloir supprimer le paiement ' + receiptNumber +
                ' ? Cette action est irréversible.')) {
            window.location.href = 'delete_payment.php?id=' + paymentId + '&student_id=<?php echo $student_id; ?>';
        }
    }

    function printPaymentHistory() {
        const printContent = `
            <html>
                <head>
                    <title>Historique des paiements - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { color: #333; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .total { font-weight: bold; text-align: right; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <h1>Historique des paiements</h1>
                    <p><strong>Étudiant:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                    <p><strong>Date d'édition:</strong> ${new Date().toLocaleDateString('fr-FR')}</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Méthode</th>
                                <th>N° Reçu</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><?php echo formatAmount($payment['amount']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                                <td><?php echo htmlspecialchars($payment['notes'] ?: '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="total">
                        Total payé: <?php echo formatAmount($student['paid_amount']); ?>
                    </div>
                </body>
            </html>
        `;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.print();
    }

    // Initialize progress bar animation
    document.addEventListener('DOMContentLoaded', function() {
        // Animate progress bar
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            setTimeout(() => {
                progressBar.style.width = '<?php echo $percentage_paid; ?>%';
            }, 300);
        }

        // Create payment chart if there are payments
        <?php if ($payment_count > 0): ?>
        const ctx = document.getElementById('paymentChart').getContext('2d');

        // Prepare chart data
        const paymentDates =
            <?php echo json_encode(array_map(function($p) { return formatDate($p['payment_date']); }, array_reverse($payments))); ?>;
        const paymentAmounts =
            <?php echo json_encode(array_map(function($p) { return $p['amount']; }, array_reverse($payments))); ?>;
        const cumulativeAmounts = [];
        let cumulative = 0;
        paymentAmounts.forEach(amount => {
            cumulative += amount;
            cumulativeAmounts.push(cumulative);
        });

        const paymentChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: paymentDates,
                datasets: [{
                        label: 'Montant du paiement',
                        data: paymentAmounts,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Cumul payé',
                        data: cumulativeAmounts,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += formatCurrency(context.parsed.y);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Montant par paiement'
                        },
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Cumul total'
                        },
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Add fade-in animation to table rows
        const tableRows = document.querySelectorAll('.payment-table tbody tr');
        tableRows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            row.style.animation = 'fadeInUp 0.5s ease forwards';
        });

        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    });

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + E to edit
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            openEditModal();
        }

        // Escape to close modal
        if (e.key === 'Escape') {
            closeEditModal();
        }

        // Ctrl + P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printPaymentHistory();
        }
    });
    </script>
</body>

</html>