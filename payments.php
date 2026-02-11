<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

// Filtres
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$payment_method = $_GET['payment_method'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'recent';

// Construire la requête avec filtres
$where = "WHERE 1=1";
$params = [];

if ($start_date && $end_date) {
    $where .= " AND p.payment_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($payment_method) {
    $where .= " AND p.payment_method = ?";
    $params[] = $payment_method;
}

if ($student_id) {
    $where .= " AND p.student_id = ?";
    $params[] = $student_id;
}

if ($search) {
    $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR p.receipt_number LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($status === 'recent') {
    $where .= " AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
}

// Trier
$order_by = "ORDER BY ";
switch ($sort) {
    case 'amount_desc':
        $order_by .= "p.amount DESC";
        break;
    case 'amount_asc':
        $order_by .= "p.amount ASC";
        break;
    case 'date_asc':
        $order_by .= "p.payment_date ASC";
        break;
    case 'student':
        $order_by .= "s.last_name ASC, s.first_name ASC";
        break;
    default:
        $order_by .= "p.payment_date DESC, p.created_at DESC";
}

// Récupérer les paiements
$sql = "SELECT p.*, s.first_name, s.last_name, s.email, s.formation_type 
        FROM payments p 
        JOIN students s ON p.student_id = s.id 
        $where 
        $order_by";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les étudiants pour le filtre
$students_sql = "SELECT id, first_name, last_name FROM students ORDER BY last_name, first_name";
$students = $pdo->query($students_sql)->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$stats_sql = "SELECT 
        COUNT(*) as count,
        SUM(p.amount) as total,
        AVG(p.amount) as average,
        MIN(p.amount) as min,
        MAX(p.amount) as max,
        COUNT(DISTINCT p.student_id) as unique_students
    FROM payments p
    $where
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculer par méthode de paiement
$method_stats_sql = "
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(amount) as total
    FROM payments p
    $where
    GROUP BY payment_method
    ORDER BY total DESC
";
$method_stats_stmt = $pdo->prepare($method_stats_sql);
$method_stats_stmt->execute($params);
$method_stats = $method_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les tendances (comparaison avec le mois précédent)
$prev_month_start = date('Y-m-01', strtotime('-1 month'));
$prev_month_end = date('Y-m-t', strtotime('-1 month'));

$trend_sql = "
    SELECT SUM(amount) as total 
    FROM payments 
    WHERE payment_date BETWEEN ? AND ?
";
$trend_stmt = $pdo->prepare($trend_sql);
$trend_stmt->execute([$prev_month_start, $prev_month_end]);
$prev_month_total = $trend_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Calculer la variation
$trend_percentage = 0;
if ($prev_month_total > 0 && $stats['total'] > 0) {
    $trend_percentage = round((($stats['total'] - $prev_month_total) / $prev_month_total) * 100, 1);
}

// Récupérer les paiements récents pour les notifications
$recent_sql = "
    SELECT p.*, s.first_name, s.last_name 
    FROM payments p 
    JOIN students s ON p.student_id = s.id 
    ORDER BY p.created_at DESC 
    LIMIT 5
";
$recent_payments = $pdo->query($recent_sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - <?php echo SITE_NAME; ?></title>
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

    .payments-container {
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

    /* Stats Cards */
    .stats-cards {
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

    .stat-card.count::before {
        background: linear-gradient(to bottom, var(--success), #16a34a);
    }

    .stat-card.average::before {
        background: linear-gradient(to bottom, var(--warning), #ea580c);
    }

    .stat-card.students::before {
        background: linear-gradient(to bottom, var(--secondary), #0ea5e9);
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

    .stat-card.count .stat-icon {
        background: linear-gradient(135deg, var(--success), #16a34a);
    }

    .stat-card.average .stat-icon {
        background: linear-gradient(135deg, var(--warning), #ea580c);
    }

    .stat-card.students .stat-icon {
        background: linear-gradient(135deg, var(--secondary), #0ea5e9);
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

    .stat-trend {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 5px;
    }

    .stat-trend.positive {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
    }

    .stat-trend.negative {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    /* Filters Section */
    .filters-section {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .filters-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .filter-group {
        margin-bottom: 15px;
    }

    .filter-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }

    .filter-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }

    .filter-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }

    .filter-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        font-size: 14px;
        background: white;
        cursor: pointer;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .btn-filter {
        padding: 12px 24px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }

    .btn-reset {
        padding: 12px 24px;
        background: white;
        color: var(--dark);
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }

    .btn-reset:hover {
        background: var(--light);
        border-color: var(--danger);
        color: var(--danger);
    }

    /* Status Badges */
    .status-badges {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        border: 2px solid transparent;
        text-decoration: none;
        color: inherit;
    }

    .status-badge.all {
        background: var(--light);
        color: var(--dark);
    }

    .status-badge.recent {
        background: rgba(67, 97, 238, 0.1);
        color: var(--primary);
    }

    .status-badge.large {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .status-badge.active {
        border-color: currentColor;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    /* Method Stats */
    .method-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .method-stat {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        border-top: 4px solid;
    }

    .method-stat.cash {
        border-color: var(--success);
    }

    .method-stat.card {
        border-color: #2563eb;
    }

    .method-stat.transfer {
        border-color: #9333ea;
    }

    .method-stat.check {
        border-color: #d97706;
    }

    .method-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 16px;
        color: white;
    }

    .method-stat.cash .method-icon {
        background: var(--success);
    }

    .method-stat.card .method-icon {
        background: #2563eb;
    }

    .method-stat.transfer .method-icon {
        background: #9333ea;
    }

    .method-stat.check .method-icon {
        background: #d97706;
    }

    .method-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--dark);
        margin: 5px 0;
    }

    .method-label {
        font-size: 13px;
        color: var(--gray);
    }

    /* Payments Table */
    .payments-table-container {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
    }

    .table-header {
        padding: 25px;
        border-bottom: 1px solid var(--light-gray);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .table-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .table-sort {
        padding: 8px 16px;
        border: 2px solid var(--light-gray);
        border-radius: 8px;
        font-size: 14px;
        background: white;
        cursor: pointer;
    }

    .payments-table {
        width: 100%;
        border-collapse: collapse;
    }

    .payments-table thead {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .payments-table th {
        padding: 18px 16px;
        text-align: left;
        font-weight: 600;
        color: var(--gray);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--light-gray);
    }

    .payments-table tbody tr {
        border-bottom: 1px solid var(--light-gray);
        transition: background-color 0.2s;
    }

    .payments-table tbody tr:hover {
        background-color: rgba(67, 97, 238, 0.03);
    }

    .payments-table td {
        padding: 18px 16px;
        vertical-align: middle;
    }

    /* Student Info */
    .student-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .student-avatar {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
        color: white;
    }

    .student-details {
        line-height: 1.4;
    }

    .student-name {
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }

    .student-formation {
        font-size: 12px;
        color: var(--gray);
    }

    /* Payment Details */
    .payment-amount {
        font-weight: 700;
        font-size: 15px;
        color: var(--success);
    }

    .payment-date {
        display: flex;
        flex-direction: column;
    }

    .date-day {
        font-weight: 600;
        color: var(--dark);
    }

    .date-time {
        font-size: 12px;
        color: var(--gray);
    }

    .payment-method-badge {
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
        font-size: 13px;
    }

    /* Action Buttons */
    .action-buttons {
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
        text-decoration: none;
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

    /* Recent Payments */
    .recent-payments {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .recent-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .recent-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .recent-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .recent-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: var(--light);
        border-radius: 10px;
        transition: all 0.3s;
    }

    .recent-item:hover {
        background: var(--light-gray);
        transform: translateX(5px);
    }

    .recent-student {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .recent-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
        color: white;
    }

    .recent-info h4 {
        font-weight: 600;
        font-size: 14px;
        color: var(--dark);
        margin-bottom: 3px;
    }

    .recent-info p {
        font-size: 12px;
        color: var(--gray);
    }

    .recent-amount {
        font-weight: 700;
        color: var(--success);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .payments-container {
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

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .payments-table {
            display: block;
            overflow-x: auto;
        }

        .method-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-cards {
            grid-template-columns: 1fr 1fr;
        }

        .method-stats {
            grid-template-columns: 1fr;
        }

        .table-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .table-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .payments-table th:nth-child(4),
        .payments-table td:nth-child(4) {
            display: none;
        }

        .action-buttons {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .stats-cards {
            grid-template-columns: 1fr;
        }

        .header-actions {
            flex-direction: column;
        }

        .btn-action {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="payments-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-credit-card"></i>
                    Gestion des Paiements
                </h1>
                <p style="color: var(--gray); margin-top: 5px;">Suivi et analyse de tous les paiements</p>
            </div>
            <div class="header-actions">
                <a href="add_payment.php" class="btn-action btn-success">
                    <i class="fas fa-cash-register"></i>
                    Nouveau Paiement
                </a>
                <a href="export_payments.php?<?php echo http_build_query($_GET); ?>" class="btn-action btn-secondary">
                    <i class="fas fa-file-export"></i>
                    Exporter
                </a>
                <button class="btn-action btn-primary" onclick="printReport()">
                    <i class="fas fa-print"></i>
                    Imprimer
                </button>
            </div>
        </div>

        <!-- Status Badges -->
        <div class="status-badges">
            <a href="payments.php" class="status-badge all <?php echo !$status ? 'active' : ''; ?>">
                Tous les paiements
            </a>
            <a href="payments.php?status=recent<?php echo $start_date ? '&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) : ''; ?>"
                class="status-badge recent <?php echo $status === 'recent' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> 7 derniers jours
            </a>
            <a href="payments.php?sort=amount_desc<?php echo $start_date ? '&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) : ''; ?>"
                class="status-badge large <?php echo $sort === 'amount_desc' ? 'active' : ''; ?>">
                <i class="fas fa-sort-amount-down"></i> Plus gros paiements
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <div class="stat-value"><?php echo formatAmount($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total collecté</div>
                <?php if ($trend_percentage != 0): ?>
                <span class="stat-trend <?php echo $trend_percentage > 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-arrow-<?php echo $trend_percentage > 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs($trend_percentage); ?>% vs mois précédent
                </span>
                <?php endif; ?>
            </div>
            <div class="stat-card count">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value"><?php echo $stats['count'] ?? 0; ?></div>
                <div class="stat-label">Nombre de paiements</div>
            </div>
            <div class="stat-card average">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo formatAmount($stats['average'] ?? 0); ?></div>
                <div class="stat-label">Moyenne par paiement</div>
            </div>
            <div class="stat-card students">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['unique_students'] ?? 0; ?></div>
                <div class="stat-label">Étudiants uniques</div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header">
                <h3 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtres avancés
                </h3>
            </div>
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Période</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <input type="date" name="start_date" class="filter-input" value="<?php echo $start_date; ?>">
                        <input type="date" name="end_date" class="filter-input" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Étudiant</label>
                    <select name="student_id" class="filter-select">
                        <option value="">Tous les étudiants</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>"
                            <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['last_name'] . ' ' . $student['first_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Méthode de paiement</label>
                    <select name="payment_method" class="filter-select">
                        <option value="">Toutes les méthodes</option>
                        <option value="Espèces" <?php echo $payment_method == 'Espèces' ? 'selected' : ''; ?>>Espèces
                        </option>
                        <option value="Carte" <?php echo $payment_method == 'Carte' ? 'selected' : ''; ?>>Carte bancaire
                        </option>
                        <option value="Virement" <?php echo $payment_method == 'Virement' ? 'selected' : ''; ?>>Virement
                        </option>
                        <option value="Chèque" <?php echo $payment_method == 'Chèque' ? 'selected' : ''; ?>>Chèque
                        </option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Recherche</label>
                    <input type="text" name="search" class="filter-input" placeholder="Nom, email ou N° reçu..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Trier par</label>
                    <select name="sort" class="filter-select">
                        <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Plus récents</option>
                        <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Plus anciens
                        </option>
                        <option value="amount_desc" <?php echo $sort === 'amount_desc' ? 'selected' : ''; ?>>Montant
                            décroissant</option>
                        <option value="amount_asc" <?php echo $sort === 'amount_asc' ? 'selected' : ''; ?>>Montant
                            croissant</option>
                        <option value="student" <?php echo $sort === 'student' ? 'selected' : ''; ?>>Nom étudiant
                        </option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i>
                        Appliquer les filtres
                    </button>
                    <a href="payments.php" class="btn-reset">
                        <i class="fas fa-times"></i>
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Method Statistics -->
        <?php if (!empty($method_stats)): ?>
        <div class="method-stats">
            <?php foreach ($method_stats as $method): 
                $method_class = strtolower($method['payment_method']);
                if ($method_class === 'espèces') $method_class = 'cash';
                if ($method_class === 'carte') $method_class = 'card';
                if ($method_class === 'virement') $method_class = 'transfer';
                if ($method_class === 'chèque') $method_class = 'check';
            ?>
            <div class="method-stat <?php echo $method_class; ?>">
                <div class="method-icon">
                    <?php if ($method_class === 'cash'): ?>
                    <i class="fas fa-money-bill-wave"></i>
                    <?php elseif ($method_class === 'card'): ?>
                    <i class="fas fa-credit-card"></i>
                    <?php elseif ($method_class === 'transfer'): ?>
                    <i class="fas fa-university"></i>
                    <?php elseif ($method_class === 'check'): ?>
                    <i class="fas fa-file-invoice-dollar"></i>
                    <?php endif; ?>
                </div>
                <div class="method-value"><?php echo formatAmount($method['total']); ?></div>
                <div class="method-label"><?php echo $method['payment_method']; ?></div>
                <div style="font-size: 11px; color: var(--gray); margin-top: 5px;">
                    <?php echo $method['count']; ?> paiement<?php echo $method['count'] > 1 ? 's' : ''; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Chart Section -->
        <?php if (!empty($payments)): ?>
        <div class="chart-container">
            <h3 class="filters-title">
                <i class="fas fa-chart-bar"></i>
                Répartition par jour
            </h3>
            <div class="chart-wrapper">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payments Table -->
        <div class="payments-table-container">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-list"></i>
                    Liste des paiements (<?php echo count($payments); ?>)
                </h3>
                <div class="table-actions">
                    <span style="font-size: 14px; color: var(--gray);">
                        Période: <?php echo formatDate($start_date); ?> au <?php echo formatDate($end_date); ?>
                    </span>
                </div>
            </div>

            <?php if (empty($payments)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>Aucun paiement trouvé</h3>
                <p><?php echo $start_date || $payment_method || $search ? 'Essayez de modifier vos critères de recherche.' : 'Aucun paiement enregistré pour le moment.'; ?>
                </p>
                <?php if (!$start_date && !$payment_method && !$search): ?>
                <a href="add_payment.php" class="btn-action btn-success" style="margin-top: 20px;">
                    <i class="fas fa-cash-register"></i>
                    Enregistrer le premier paiement
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>N° Reçu</th>
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
                                <div class="payment-date">
                                    <span class="date-day"><?php echo formatDate($payment['payment_date']); ?></span>
                                    <span class="date-time">
                                        <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($payment['first_name'], 0, 1) . substr($payment['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="student-details">
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                        </div>
                                        <div class="student-email" style="font-size: 12px; color: var(--gray);">
                                            <?php echo htmlspecialchars($payment['email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="student-formation">
                                    <?php echo htmlspecialchars($payment['formation_type']); ?>
                                </div>
                            </td>
                            <td class="payment-amount"><?php echo formatAmount($payment['amount']); ?></td>
                            <td>
                                <span class="payment-method-badge method-<?php echo $method_class; ?>">
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
                                <div class="action-buttons">
                                    <a href="student_details.php?id=<?php echo $payment['student_id']; ?>"
                                        class="btn-icon" title="Voir l'étudiant">
                                        <i class="fas fa-user"></i>
                                    </a>
                                    <a href="receipt.php?id=<?php echo $payment['id']; ?>" class="btn-icon"
                                        title="Voir le reçu" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
                            <td colspan="7" style="padding: 20px; text-align: right;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="font-weight: 600; color: var(--dark);">
                                        <?php echo count($payments); ?>
                                        paiement<?php echo count($payments) > 1 ? 's' : ''; ?>
                                        sélectionné<?php echo count($payments) > 1 ? 's' : ''; ?>
                                    </div>
                                    <div style="font-size: 18px; font-weight: 700; color: var(--primary);">
                                        Total: <?php echo formatAmount($stats['total'] ?? 0); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Payments -->
        <?php if (!empty($recent_payments)): ?>
        <div class="recent-payments">
            <div class="recent-header">
                <h3 class="recent-title">
                    <i class="fas fa-bolt"></i>
                    Paiements récents
                </h3>
                <a href="payments.php?status=recent"
                    style="font-size: 14px; color: var(--primary); text-decoration: none;">
                    Voir tout →
                </a>
            </div>
            <div class="recent-list">
                <?php foreach ($recent_payments as $payment): ?>
                <div class="recent-item">
                    <div class="recent-student">
                        <div class="recent-avatar">
                            <?php echo strtoupper(substr($payment['first_name'], 0, 1) . substr($payment['last_name'], 0, 1)); ?>
                        </div>
                        <div class="recent-info">
                            <h4><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                            </h4>
                            <p><?php echo formatDate($payment['payment_date']); ?> •
                                <?php echo $payment['payment_method']; ?></p>
                        </div>
                    </div>
                    <div class="recent-amount">
                        <?php echo formatAmount($payment['amount']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }

    // Print report
    function printReport() {
        const printContent = `
            <html>
                <head>
                    <title>Rapport des paiements - <?php echo SITE_NAME; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { color: #333; margin-bottom: 10px; }
                        .header { margin-bottom: 30px; }
                        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
                        .stat { padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
                        .stat .value { font-size: 24px; font-weight: bold; margin: 5px 0; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .total { font-weight: bold; text-align: right; margin-top: 20px; }
                        @media print {
                            body { padding: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Rapport des paiements</h1>
                        <p><strong>Période:</strong> <?php echo formatDate($start_date); ?> au <?php echo formatDate($end_date); ?></p>
                        <p><strong>Date d'édition:</strong> ${new Date().toLocaleDateString('fr-FR')}</p>
                    </div>
                    
                    <div class="stats">
                        <div class="stat">
                            <div>Total collecté</div>
                            <div class="value"><?php echo formatAmount($stats['total'] ?? 0); ?></div>
                        </div>
                        <div class="stat">
                            <div>Nombre de paiements</div>
                            <div class="value"><?php echo $stats['count'] ?? 0; ?></div>
                        </div>
                        <div class="stat">
                            <div>Moyenne par paiement</div>
                            <div class="value"><?php echo formatAmount($stats['average'] ?? 0); ?></div>
                        </div>
                        <div class="stat">
                            <div>Étudiants uniques</div>
                            <div class="value"><?php echo $stats['unique_students'] ?? 0; ?></div>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Étudiant</th>
                                <th>Montant</th>
                                <th>Méthode</th>
                                <th>N° Reçu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                <td><?php echo formatAmount($payment['amount']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['receipt_number']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="total">
                        Total: <?php echo formatAmount($stats['total'] ?? 0); ?>
                    </div>
                </body>
            </html>
        `;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.print();
    }

    // Edit payment
    function editPayment(paymentId) {
        // Implement edit functionality
        alert('Modification du paiement #' + paymentId + ' - Fonctionnalité à implémenter');
    }

    // Delete payment
    function deletePayment(paymentId, receiptNumber) {
        if (confirm('Êtes-vous sûr de vouloir supprimer le paiement ' + receiptNumber +
                ' ? Cette action est irréversible.')) {
            window.location.href = 'delete_payment.php?id=' + paymentId;
        }
    }

    // Create daily chart
    <?php if (!empty($payments)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Group payments by date
        const paymentsByDate = {};
        <?php foreach ($payments as $payment): ?>
        const date = '<?php echo $payment['payment_date']; ?>';
        if (!paymentsByDate[date]) {
            paymentsByDate[date] = 0;
        }
        paymentsByDate[date] += parseFloat(<?php echo $payment['amount']; ?>);
        <?php endforeach; ?>

        // Prepare chart data
        const dates = Object.keys(paymentsByDate).sort();
        const amounts = dates.map(date => paymentsByDate[date]);

        // Format dates for display
        const formattedDates = dates.map(date => {
            const d = new Date(date);
            return d.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'short'
            });
        });

        // Create chart
        const ctx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: formattedDates,
                datasets: [{
                    label: 'Montant collecté',
                    data: amounts,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: '#4361ee',
                    borderWidth: 1,
                    borderRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return formatCurrency(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Add animations to table rows
        const tableRows = document.querySelectorAll('.payments-table tbody tr');
        tableRows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.03}s`;
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
    <?php endif; ?>

    // Quick date filters
    function setDateRange(range) {
        const today = new Date();
        let start, end;

        switch (range) {
            case 'today':
                start = end = today.toISOString().split('T')[0];
                break;
            case 'week':
                start = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
                end = new Date().toISOString().split('T')[0];
                break;
            case 'month':
                start = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                end = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
                break;
            case 'year':
                start = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                end = new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0];
                break;
        }

        document.querySelector('input[name="start_date"]').value = start;
        document.querySelector('input[name="end_date"]').value = end;
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + F to focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.querySelector('input[name="search"]').focus();
        }

        // Ctrl + P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printReport();
        }

        // Ctrl + N for new payment
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'add_payment.php';
        }
    });
    </script>
</body>

</html>