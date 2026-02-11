<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

// Récupérer les statistiques
$sql_stats = "
    SELECT 
        COUNT(*) as total_students,
        SUM(total_amount) as total_revenue,
        SUM(paid_amount) as total_paid,
        SUM(total_amount - paid_amount) as total_balance
    FROM students
";
$stats = $pdo->query($sql_stats)->fetch(PDO::FETCH_ASSOC);

// Statistiques par mois (pour le graphique)
$sql_monthly = "
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        COUNT(*) as payments_count,
        SUM(amount) as monthly_total
    FROM payments 
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$monthly_stats = $pdo->query($sql_monthly)->fetchAll(PDO::FETCH_ASSOC);

// Derniers paiements
$sql_payments = "
    SELECT p.*, s.first_name, s.last_name 
    FROM payments p 
    JOIN students s ON p.student_id = s.id 
    ORDER BY p.created_at DESC 
    LIMIT 8
";
$recent_payments = $pdo->query($sql_payments)->fetchAll(PDO::FETCH_ASSOC);

// Étudiants avec solde
$sql_balances = "
    SELECT *, (total_amount - paid_amount) as balance 
    FROM students 
    WHERE (total_amount - paid_amount) > 0 
    ORDER BY balance DESC 
    LIMIT 8
";
$students_balance = $pdo->query($sql_balances)->fetchAll(PDO::FETCH_ASSOC);

// Formater les données pour le graphique
$chart_labels = [];
$chart_data = [];
foreach (array_reverse($monthly_stats) as $month) {
    $chart_labels[] = date('M Y', strtotime($month['month'] . '-01'));
    $chart_data[] = floatval($month['monthly_total']);
}

// Calculer les pourcentages
$paid_percentage = ($stats['total_revenue'] > 0) ? round(($stats['total_paid'] / $stats['total_revenue']) * 100, 1) : 0;
$balance_percentage = ($stats['total_revenue'] > 0) ? round(($stats['total_balance'] / $stats['total_revenue']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    :root {
        --primary: #4361ee;
        --secondary: #3a0ca3;
        --success: #4cc9f0;
        --warning: #f72585;
        --danger: #e63946;
        --light: #f8f9fa;
        --dark: #212529;
        --gray: #6c757d;
        --light-gray: #e9ecef;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
        color: var(--dark);
        min-height: 100vh;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .dashboard-header {
        margin-bottom: 30px;
    }

    .dashboard-header h1 {
        font-size: 28px;
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 10px;
    }

    .welcome-message {
        color: var(--gray);
        font-size: 16px;
    }

    .welcome-message .user-name {
        color: var(--primary);
        font-weight: 600;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 6px;
        height: 100%;
    }

    .stat-card.students::before {
        background: linear-gradient(to bottom, #4cc9f0, #4361ee);
    }

    .stat-card.revenue::before {
        background: linear-gradient(to bottom, #7209b7, #3a0ca3);
    }

    .stat-card.paid::before {
        background: linear-gradient(to bottom, #4ade80, #22c55e);
    }

    .stat-card.balance::before {
        background: linear-gradient(to bottom, #f97316, #ef4444);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
    }

    .stat-icon.students {
        background: linear-gradient(135deg, #4cc9f0, #4361ee);
    }

    .stat-icon.revenue {
        background: linear-gradient(135deg, #7209b7, #3a0ca3);
    }

    .stat-icon.paid {
        background: linear-gradient(135deg, #4ade80, #22c55e);
    }

    .stat-icon.balance {
        background: linear-gradient(135deg, #f97316, #ef4444);
    }

    .stat-title {
        font-size: 14px;
        color: var(--gray);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: var(--dark);
        line-height: 1;
        margin: 10px 0;
    }

    .stat-trend {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .stat-trend.positive {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    .stat-trend.negative {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .progress-bar {
        height: 6px;
        background: var(--light-gray);
        border-radius: 3px;
        margin-top: 15px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 1s ease-in-out;
    }

    .progress-fill.paid {
        background: linear-gradient(to right, #4ade80, #22c55e);
    }

    .progress-fill.balance {
        background: linear-gradient(to right, #f97316, #ef4444);
    }

    .progress-text {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: var(--gray);
        margin-top: 5px;
    }

    /* Main Content Grid */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 40px;
    }

    @media (max-width: 1200px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Cards */
    .card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .card-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--dark);
    }

    .card-link {
        color: var(--primary);
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: color 0.3s;
    }

    .card-link:hover {
        color: var(--secondary);
    }

    /* Tables */
    .table-responsive {
        overflow-x: auto;
        border-radius: 10px;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table thead {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .table th {
        padding: 16px 12px;
        text-align: left;
        font-weight: 600;
        color: var(--gray);
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--light-gray);
    }

    .table td {
        padding: 16px 12px;
        border-bottom: 1px solid var(--light-gray);
        vertical-align: middle;
    }

    .table tbody tr {
        transition: background-color 0.2s;
    }

    .table tbody tr:hover {
        background-color: rgba(67, 97, 238, 0.03);
    }

    .student-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }

    .payment-method {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .payment-method.cash {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    .payment-method.card {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }

    .payment-method.transfer {
        background: rgba(168, 85, 247, 0.1);
        color: #9333ea;
    }

    .payment-method.check {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .balance-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .balance-badge.high {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .balance-badge.medium {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .balance-badge.low {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    /* Chart Container */
    .chart-container {
        height: 300px;
        margin-top: 10px;
    }

    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }

    .action-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        text-decoration: none;
        color: var(--dark);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: var(--primary);
    }

    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
    }

    .action-icon.student {
        background: linear-gradient(135deg, #4cc9f0, #4361ee);
    }

    .action-icon.payment {
        background: linear-gradient(135deg, #7209b7, #3a0ca3);
    }

    .action-icon.report {
        background: linear-gradient(135deg, #4ade80, #22c55e);
    }

    .action-icon.settings {
        background: linear-gradient(135deg, #f97316, #ef4444);
    }

    .action-content h4 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .action-content p {
        font-size: 13px;
        color: var(--gray);
    }

    /* Footer */
    .dashboard-footer {
        text-align: center;
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid var(--light-gray);
        color: var(--gray);
        font-size: 14px;
    }

    .last-update {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--gray);
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: var(--light-gray);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 15px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .content-grid {
            gap: 20px;
        }

        .card {
            padding: 20px;
        }

        .table th,
        .table td {
            padding: 12px 8px;
        }

        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Tableau de bord</h1>
            <p class="welcome-message">Bonjour <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>,
                voici un aperçu de votre activité.</p>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card students">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Total Étudiants</div>
                        <div class="stat-value"><?php echo $stats['total_students'] ?? 0; ?></div>
                        <div class="stat-trend positive">
                            <i class="fas fa-arrow-up"></i> 12% ce mois
                        </div>
                    </div>
                    <div class="stat-icon students">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill paid" style="width: 100%"></div>
                </div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Chiffre d'affaires</div>
                        <div class="stat-value"><?php echo formatAmount($stats['total_revenue'] ?? 0); ?></div>
                        <div class="stat-trend positive">
                            <i class="fas fa-arrow-up"></i> 8% ce mois
                        </div>
                    </div>
                    <div class="stat-icon revenue">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill paid" style="width: 100%"></div>
                </div>
            </div>

            <div class="stat-card paid">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Total Perçu</div>
                        <div class="stat-value"><?php echo formatAmount($stats['total_paid'] ?? 0); ?></div>
                        <div class="progress-text">
                            <span><?php echo $paid_percentage; ?>% du total</span>
                            <span><?php echo formatAmount($stats['total_paid'] ?? 0); ?></span>
                        </div>
                    </div>
                    <div class="stat-icon paid">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill paid" style="width: <?php echo $paid_percentage; ?>%"></div>
                </div>
            </div>

            <div class="stat-card balance">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Total à Recevoir</div>
                        <div class="stat-value"><?php echo formatAmount($stats['total_balance'] ?? 0); ?></div>
                        <div class="progress-text">
                            <span><?php echo $balance_percentage; ?>% du total</span>
                            <span><?php echo formatAmount($stats['total_balance'] ?? 0); ?></span>
                        </div>
                    </div>
                    <div class="stat-icon balance">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill balance" style="width: <?php echo $balance_percentage; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Graphique et Derniers Paiements -->
        <div class="content-grid">
            <!-- Graphique des revenus -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Revenus des 6 derniers mois</h2>
                    <a href="reports.php" class="card-link">
                        Voir les rapports <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Derniers Paiements -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Derniers Paiements</h2>
                    <a href="payments.php" class="card-link">
                        Voir tout <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <?php if (!empty($recent_payments)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Étudiant</th>
                                <th>Montant</th>
                                <th>Méthode</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($payment['first_name'], 0, 1) . substr($payment['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-weight: 600; color: var(--success);">
                                    <?php echo formatAmount($payment['amount']); ?>
                                </td>
                                <td>
                                    <?php 
                                    $method_class = strtolower($payment['payment_method']);
                                    if ($method_class === 'espèces') $method_class = 'cash';
                                    if ($method_class === 'carte') $method_class = 'card';
                                    if ($method_class === 'virement') $method_class = 'transfer';
                                    if ($method_class === 'chèque') $method_class = 'check';
                                    ?>
                                    <span class="payment-method <?php echo $method_class; ?>">
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
                                <td style="color: var(--gray); font-size: 14px;">
                                    <?php echo formatDate($payment['payment_date']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <p>Aucun paiement enregistré</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Étudiants avec solde -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Étudiants avec solde impayé</h2>
                <a href="students.php?filter=balance" class="card-link">
                    Voir tout <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <?php if (!empty($students_balance)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Total</th>
                            <th>Payé</th>
                            <th>Reste</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_balance as $student): 
                            $balance_class = 'low';
                            if ($student['balance'] > 1000) $balance_class = 'high';
                            elseif ($student['balance'] > 500) $balance_class = 'medium';
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--gray);">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="color: var(--gray);">
                                <?php echo htmlspecialchars($student['formation_type']); ?>
                            </td>
                            <td style="font-weight: 600;">
                                <?php echo formatAmount($student['total_amount']); ?>
                            </td>
                            <td style="color: var(--success);">
                                <?php echo formatAmount($student['paid_amount']); ?>
                            </td>
                            <td>
                                <span class="balance-badge <?php echo $balance_class; ?>">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo formatAmount($student['balance']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Tous les étudiants sont à jour dans leurs paiements!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions Rapides -->
        <div class="quick-actions">
            <a href="students.php?action=add" class="action-card">
                <div class="action-icon student">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-content">
                    <h4>Ajouter un étudiant</h4>
                    <p>Enregistrer un nouvel étudiant</p>
                </div>
            </a>

            <a href="add_payment.php" class="action-card">
                <div class="action-icon payment">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="action-content">
                    <h4>Enregistrer un paiement</h4>
                    <p>Créer un nouveau reçu</p>
                </div>
            </a>

            <a href="reports.php" class="action-card">
                <div class="action-icon report">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-content">
                    <h4>Générer un rapport</h4>
                    <p>Export Excel et PDF</p>
                </div>
            </a>

            <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="settings.php" class="action-card">
                <div class="action-icon settings">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="action-content">
                    <h4>Paramètres</h4>
                    <p>Configurer le système</p>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="dashboard-footer">
            <div class="last-update">
                <i class="fas fa-sync-alt"></i>
                <span>Dernière mise à jour : <?php echo date('d/m/Y H:i'); ?></span>
            </div>
        </div>
    </div>

    <script>
    // Graphique des revenus
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Revenus',
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderColor: '#4361ee',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4361ee',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
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
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            // Modifier pour afficher FCFA
                            return 'Revenu : ' + context.parsed.y.toLocaleString('fr-FR') + ' FCFA';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            // Modifier pour afficher FCFA
                            return value.toLocaleString('fr-FR') + ' FCFA';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'nearest'
            }
        }
    });

    // Fonction de formatage pour FCFA
    function formatFCFA(amount) {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    }

    // Animation des cartes de statistiques
    document.addEventListener('DOMContentLoaded', function() {
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('animate__animated', 'animate__fadeInUp');
        });

        // Animation des barres de progression
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0';
            setTimeout(() => {
                bar.style.width = width;
            }, 500);
        });
    });

    // Rafraîchissement automatique toutes les 60 secondes
    setTimeout(() => {
        window.location.reload();
    }, 60000);
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
</body>

</html>