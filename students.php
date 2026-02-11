<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

// Message de succès
if (isset($_GET['success'])) {
    $success_message = "Étudiant ajouté avec succès!";
}

// Ajouter un étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $formation_type = $_POST['formation_type'];
        $total_amount = $_POST['total_amount'];
        
        $sql = "INSERT INTO students (first_name, last_name, email, phone, formation_type, total_amount) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$first_name, $last_name, $email, $phone, $formation_type, $total_amount]);
        
        $_SESSION['success_message'] = "Étudiant ajouté avec succès!";
        header('Location: students.php?success=1');
        exit();
    }
    
    // Supprimer un étudiant
    if (isset($_POST['delete_student'])) {
        $student_id = $_POST['student_id'];
        
        // Vérifier s'il y a des paiements associés
        $check_sql = "SELECT COUNT(*) as count FROM payments WHERE student_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$student_id]);
        $has_payments = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($has_payments) {
            $error_message = "Impossible de supprimer cet étudiant car il a des paiements associés.";
        } else {
            $delete_sql = "DELETE FROM students WHERE id = ?";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([$student_id]);
            
            $_SESSION['success_message'] = "Étudiant supprimé avec succès!";
            header('Location: students.php');
            exit();
        }
    }
}

// Filtres
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$formation = $_GET['formation'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Construire la requête avec filtres
$where = "WHERE 1=1";
$params = [];
$param_types = [];

if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($status) {
    if ($status === 'paid') {
        $where .= " AND (total_amount - paid_amount) <= 0";
    } elseif ($status === 'partial') {
        $where .= " AND paid_amount > 0 AND (total_amount - paid_amount) > 0";
    } elseif ($status === 'pending') {
        $where .= " AND paid_amount = 0";
    } elseif ($status === 'overdue') {
        $where .= " AND (total_amount - paid_amount) > 0";
    }
}

if ($formation) {
    $where .= " AND formation_type = ?";
    $params[] = $formation;
}

// Trier
$order_by = "ORDER BY ";
switch ($sort) {
    case 'name':
        $order_by .= "first_name ASC, last_name ASC";
        break;
    case 'balance':
        $order_by .= "(total_amount - paid_amount) DESC";
        break;
    case 'recent':
        $order_by .= "registration_date DESC";
        break;
    case 'oldest':
        $order_by .= "registration_date ASC";
        break;
    default:
        $order_by .= "registration_date DESC";
}

// Récupérer les étudiants avec leur dernier mois payé
$sql = "SELECT s.*, 
               (s.total_amount - s.paid_amount) as balance,
               MAX(p.payment_month) as last_paid_month
        FROM students s
        LEFT JOIN payments p ON s.id = p.student_id
        $where 
        GROUP BY s.id
        $order_by";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les types de formation uniques pour le filtre
$formation_sql = "SELECT DISTINCT formation_type FROM students WHERE formation_type IS NOT NULL AND formation_type != '' ORDER BY formation_type";
$formation_types = $pdo->query($formation_sql)->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$stats_sql = "SELECT 
        COUNT(*) as total,
        SUM(total_amount) as total_revenue,
        SUM(paid_amount) as total_paid,
        SUM(total_amount - paid_amount) as total_balance,
        SUM(CASE WHEN paid_amount = 0 THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN (total_amount - paid_amount) > 0 AND paid_amount > 0 THEN 1 ELSE 0 END) as partial_count,
        SUM(CASE WHEN (total_amount - paid_amount) <= 0 THEN 1 ELSE 0 END) as paid_count
    FROM students
    $where
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Étudiants - <?php echo SITE_NAME; ?></title>
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
    }

    .students-container {
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
    }

    .btn-add {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
    }

    .btn-export {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: white;
        color: var(--dark);
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        border: 2px solid var(--light-gray);
        transition: all 0.3s;
    }

    .btn-export:hover {
        background: var(--light);
        border-color: var(--primary);
        color: var(--primary);
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
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card.total {
        border-top: 4px solid var(--primary);
    }

    .stat-card.revenue {
        border-top: 4px solid var(--success);
    }

    .stat-card.paid {
        border-top: 4px solid var(--warning);
    }

    .stat-card.balance {
        border-top: 4px solid var(--danger);
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        margin: 10px 0;
        color: var(--dark);
    }

    .stat-card.total .stat-value {
        color: var(--primary);
    }

    .stat-card.revenue .stat-value {
        color: var(--success);
    }

    .stat-card.paid .stat-value {
        color: var(--warning);
    }

    .stat-card.balance .stat-value {
        color: var(--danger);
    }

    .stat-label {
        font-size: 14px;
        color: var(--gray);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stat-trend {
        font-size: 12px;
        padding: 3px 8px;
        border-radius: 10px;
        font-weight: 600;
        margin-top: 5px;
        display: inline-block;
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
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
    }

    .status-badge.all {
        background: var(--light);
        color: var(--dark);
    }

    .status-badge.paid {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
    }

    .status-badge.partial {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
    }

    .status-badge.pending {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .status-badge.overdue {
        background: rgba(168, 85, 247, 0.1);
        color: #9333ea;
    }

    .status-badge.active {
        border-color: currentColor;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    /* Students Table */
    .students-table-container {
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

    .students-table {
        width: 100%;
        border-collapse: collapse;
    }

    .students-table thead {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .students-table th {
        padding: 18px 16px;
        text-align: left;
        font-weight: 600;
        color: var(--gray);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--light-gray);
    }

    .students-table tbody tr {
        border-bottom: 1px solid var(--light-gray);
        transition: background-color 0.2s;
    }

    .students-table tbody tr:hover {
        background-color: rgba(67, 97, 238, 0.03);
    }

    .students-table td {
        padding: 18px 16px;
        vertical-align: middle;
    }

    /* Student Avatar */
    .student-avatar {
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
        margin-right: 12px;
    }

    .student-info {
        display: flex;
        align-items: center;
    }

    .student-details {
        line-height: 1.4;
    }

    .student-name {
        font-weight: 600;
        color: var(--dark);
        font-size: 15px;
    }

    .student-email {
        font-size: 13px;
        color: var(--gray);
    }

    /* Status Indicators */
    .payment-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
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

    /* Amount Cells */
    .amount-cell {
        font-weight: 600;
        font-size: 15px;
    }

    .amount-total {
        color: var(--primary);
    }

    .amount-paid {
        color: var(--success);
    }

    .amount-balance {
        color: var(--danger);
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s;
        border: 1px solid transparent;
    }

    .btn-pay {
        background: linear-gradient(135deg, var(--success), #16a34a);
        color: white;
        border: none;
    }

    .btn-pay:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(34, 197, 94, 0.3);
    }

    .btn-view {
        background: white;
        color: var(--primary);
        border: 1px solid var(--light-gray);
    }

    .btn-view:hover {
        background: var(--light);
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .btn-edit {
        background: white;
        color: var(--warning);
        border: 1px solid var(--light-gray);
    }

    .btn-edit:hover {
        background: rgba(245, 158, 11, 0.1);
        border-color: var(--warning);
        transform: translateY(-2px);
    }

    .btn-delete {
        background: white;
        color: var(--danger);
        border: 1px solid var(--light-gray);
    }

    .btn-delete:hover {
        background: rgba(239, 68, 68, 0.1);
        border-color: var(--danger);
        transform: translateY(-2px);
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

    /* Modal Styles */
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
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 500px;
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

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
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

    /* Responsive */
    @media (max-width: 1024px) {
        .students-container {
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .stats-cards {
            grid-template-columns: repeat(2, 1fr);
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .students-table {
            display: block;
            overflow-x: auto;
        }
    }

    @media (max-width: 768px) {
        .stats-cards {
            grid-template-columns: 1fr;
        }

        .header-actions {
            flex-direction: column;
            width: 100%;
        }

        .btn-add,
        .btn-export {
            width: 100%;
            justify-content: center;
        }

        .students-table th:nth-child(4),
        .students-table td:nth-child(4),
        .students-table th:nth-child(5),
        .students-table td:nth-child(5) {
            display: none;
        }

        .action-buttons {
            flex-direction: column;
        }
    }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="students-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-users"></i>
                    Gestion des Étudiants
                </h1>
                <p style="color: var(--gray); margin-top: 5px;">Gérez les étudiants et leurs paiements</p>
            </div>
            <div class="header-actions">
                <button class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-user-plus"></i>
                    Ajouter un étudiant
                </button>
                <a href="export_students.php?<?php echo http_build_query($_GET); ?>" class="btn-export">
                    <i class="fas fa-file-export"></i>
                    Exporter
                </a>
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

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card total">
                <div class="stat-label">
                    <i class="fas fa-users"></i>
                    Total Étudiants
                </div>
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                <span class="stat-trend positive">+12 ce mois</span>
            </div>
            <div class="stat-card revenue">
                <div class="stat-label">
                    <i class="fas fa-euro-sign"></i>
                    Chiffre d'affaires
                </div>
                <div class="stat-value"><?php echo formatAmount($stats['total_revenue'] ?? 0); ?></div>
                <span class="stat-trend positive">+8%</span>
            </div>
            <div class="stat-card paid">
                <div class="stat-label">
                    <i class="fas fa-check-circle"></i>
                    Total perçu
                </div>
                <div class="stat-value"><?php echo formatAmount($stats['total_paid'] ?? 0); ?></div>
                <span class="stat-trend positive">+15%</span>
            </div>
            <div class="stat-card balance">
                <div class="stat-label">
                    <i class="fas fa-clock"></i>
                    À recevoir
                </div>
                <div class="stat-value"><?php echo formatAmount($stats['total_balance'] ?? 0); ?></div>
                <span class="stat-trend negative">-3%</span>
            </div>
        </div>

        <!-- Status Filters -->
        <div class="status-badges">
            <a href="students.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
                class="status-badge all <?php echo !$status ? 'active' : ''; ?>">
                Tous (<?php echo $stats['total'] ?? 0; ?>)
            </a>
            <a href="students.php?status=paid<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                class="status-badge paid <?php echo $status === 'paid' ? 'active' : ''; ?>">
                Payés (<?php echo $stats['paid_count'] ?? 0; ?>)
            </a>
            <a href="students.php?status=partial<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                class="status-badge partial <?php echo $status === 'partial' ? 'active' : ''; ?>">
                Partiels (<?php echo $stats['partial_count'] ?? 0; ?>)
            </a>
            <a href="students.php?status=pending<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                class="status-badge pending <?php echo $status === 'pending' ? 'active' : ''; ?>">
                En attente (<?php echo $stats['pending_count'] ?? 0; ?>)
            </a>
            <a href="students.php?status=overdue<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                class="status-badge overdue <?php echo $status === 'overdue' ? 'active' : ''; ?>">
                En retard
            </a>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header">
                <h3 class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtres et recherche
                </h3>
            </div>
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Recherche</label>
                    <input type="text" name="search" class="filter-input"
                        placeholder="Nom, prénom, email ou téléphone..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Formation</label>
                    <select name="formation" class="filter-select">
                        <option value="">Toutes les formations</option>
                        <?php foreach ($formation_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>"
                            <?php echo $formation === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Trier par</label>
                    <select name="sort" class="filter-select">
                        <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Plus récents</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nom A-Z</option>
                        <option value="balance" <?php echo $sort === 'balance' ? 'selected' : ''; ?>>Solde décroissant
                        </option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i>
                        Appliquer
                    </button>
                    <a href="students.php" class="btn-reset">
                        <i class="fas fa-times"></i>
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Students Table -->
        <!-- Modifiez la ligne du tableau -->
        <table class="students-table">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Téléphone</th>
                    <th>Formation</th>
                    <th>Dernier mois payé</th> <!-- Nouvelle colonne -->
                    <th>Inscription</th>
                    <th>Total</th>
                    <th>Payé</th>
                    <th>Reste</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): 
            $status_class = '';
            if ($student['balance'] <= 0) {
                $status_class = 'status-paid';
                $status_text = 'Payé';
            } elseif ($student['paid_amount'] > 0) {
                $status_class = 'status-partial';
                $status_text = 'Partiel';
            } else {
                $status_class = 'status-pending';
                $status_text = 'En attente';
            }
            
            // Formater le mois
            $last_month_display = '—';
            if (!empty($student['last_paid_month'])) {
                $month_name = getFrenchMonthName($student['last_paid_month']);
                $year = date('Y', strtotime($student['last_paid_month']));
                $last_month_display = $month_name . ' ' . $year;
            }
        ?>
                <tr>
                    <td>
                        <div class="student-info">
                            <div class="student-avatar">
                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                            </div>
                            <div class="student-details">
                                <div class="student-name">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </div>
                                <div class="student-email">
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($student['phone'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($student['formation_type']); ?></td>
                    <td>
                        <?php if (!empty($student['last_paid_month'])): ?>
                        <span style="font-weight: 500; color: var(--success);">
                            <?php echo $last_month_display; ?>
                        </span>
                        <?php else: ?>
                        <span style="color: var(--gray); font-style: italic;">
                            <?php echo $last_month_display; ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo formatDate($student['registration_date']); ?></td>
                    <td class="amount-cell amount-total"><?php echo formatAmount($student['total_amount']); ?></td>
                    <td class="amount-cell amount-paid"><?php echo formatAmount($student['paid_amount']); ?></td>
                    <td class="amount-cell amount-balance"><?php echo formatAmount($student['balance']); ?></td>
                    <td>
                        <span class="payment-status <?php echo $status_class; ?>">
                            <i
                                class="fas fa-<?php echo $status_class === 'status-paid' ? 'check-circle' : ($status_class === 'status-partial' ? 'clock' : 'exclamation-circle'); ?>"></i>
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="add_payment.php?student_id=<?php echo $student['id']; ?>"
                                class="btn-action btn-pay">
                                <i class="fas fa-cash-register"></i>
                                Payer
                            </a>
                            <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i>
                                Voir
                            </a>
                            <button class="btn-action btn-edit" onclick="editStudent(<?php echo $student['id']; ?>)">
                                <i class="fas fa-edit"></i>
                                Modifier
                            </button>
                            <button class="btn-action btn-delete"
                                onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                <i class="fas fa-trash"></i>
                                Supprimer
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Add Student Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-plus"></i>
                    Ajouter un nouvel étudiant
                </h3>
                <button class="modal-close" onclick="closeAddModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addStudentForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Prénom *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type de formation *</label>
                        <select name="formation_type" class="form-control" required>
                            <option value="">Sélectionnez une formation</option>
                            <?php foreach ($formation_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>">
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="other">Autre (à spécifier)</option>
                        </select>
                        <input type="text" name="formation_type_other" class="form-control"
                            style="margin-top: 10px; display: none;" placeholder="Précisez le type de formation">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Montant total de la formation *</label>
                        <div style="position: relative;">
                            <span
                                style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--gray);">FCFA</span>
                            <input type="number" name="total_amount" step="1" class="form-control"
                                style="padding-left: 30px;" required min="0">
                        </div>
                        <div class="quick-amounts" style="margin-top: 10px;">
                            <button type="button" class="quick-amount-btn" data-amount="50000">50 000 FCFA</button>
                            <button type="button" class="quick-amount-btn" data-amount="100000">100 000 FCFA</button>
                            <button type="button" class="quick-amount-btn" data-amount="150000">150 000 FCFA</button>
                            <button type="button" class="quick-amount-btn" data-amount="200000">200 000 FCFA</button>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                            Annuler
                        </button>
                        <button type="submit" name="add_student" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                    Confirmer la suppression
                </h3>
                <button class="modal-close" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Êtes-vous sûr de vouloir supprimer cet étudiant ? Cette action est irréversible.
                </p>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="student_id" id="deleteStudentId">
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                            Annuler
                        </button>
                        <button type="submit" name="delete_student" class="btn btn-danger">
                            <i class="fas fa-trash"></i>
                            Supprimer définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Modal functions
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('active');
    }

    function editStudent(studentId) {
        // Implement edit functionality
        alert('Modification de l\'étudiant #' + studentId + ' - Fonctionnalité à implémenter');
    }

    function deleteStudent(studentId, studentName) {
        document.getElementById('deleteStudentId').value = studentId;
        document.getElementById('deleteMessage').textContent =
            'Êtes-vous sûr de vouloir supprimer l\'étudiant "' + studentName + '" ? Cette action est irréversible.';
        document.getElementById('deleteModal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    // Quick amount buttons
    document.querySelectorAll('.quick-amount-btn').forEach(button => {
        button.addEventListener('click', function() {
            const amount = this.dataset.amount;
            document.querySelector('input[name="total_amount"]').value = amount;

            // Update active state
            document.querySelectorAll('.quick-amount-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    // Formation type other field
    const formationSelect = document.querySelector('select[name="formation_type"]');
    const otherInput = document.querySelector('input[name="formation_type_other"]');

    if (formationSelect && otherInput) {
        formationSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                otherInput.style.display = 'block';
                otherInput.required = true;
            } else {
                otherInput.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        });
    }

    // Form validation for add student
    document.getElementById('addStudentForm')?.addEventListener('submit', function(e) {
        const formationType = document.querySelector('select[name="formation_type"]').value;
        const otherInput = document.querySelector('input[name="formation_type_other"]');

        if (formationType === 'other' && (!otherInput.value || otherInput.value.trim() === '')) {
            e.preventDefault();
            alert('Veuillez spécifier le type de formation.');
            return;
        }

        if (formationType === 'other') {
            // Set the other value as the formation type
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'formation_type';
            hiddenInput.value = otherInput.value;
            this.appendChild(hiddenInput);
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + N to add new student
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            openAddModal();
        }

        // Escape to close modals
        if (e.key === 'Escape') {
            closeAddModal();
            closeDeleteModal();
        }
    });

    // Close modals when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Add animation to table rows
        const tableRows = document.querySelectorAll('.students-table tbody tr');
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
    </script>
</body>

</html>