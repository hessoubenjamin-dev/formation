<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

// Paramètres par défaut
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'monthly';
$export_format = $_GET['export'] ?? '';

// Récupérer les étudiants pour le filtre
$students_sql = "SELECT id, first_name, last_name FROM students ORDER BY last_name, first_name";
$students = $pdo->query($students_sql)->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les formations pour le filtre
$formations_sql = "SELECT DISTINCT formation_type FROM students WHERE formation_type IS NOT NULL AND formation_type != '' ORDER BY formation_type";
$formations = $pdo->query($formations_sql)->fetchAll(PDO::FETCH_COLUMN);

// Initialiser les résultats
$report_data = [];
$summary_stats = [];
$chart_data = [];

// Générer le rapport selon le type
switch ($report_type) {
    case 'monthly':
        // Statistiques mensuelles
        $sql = "
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                DATE_FORMAT(payment_date, '%M %Y') as month_name,
                COUNT(*) as payment_count,
                COUNT(DISTINCT student_id) as student_count,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount
            FROM payments
            WHERE payment_date BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), DATE_FORMAT(payment_date, '%M %Y')
            ORDER BY month DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Préparer les données pour le graphique
        $chart_labels = [];
        $chart_totals = [];
        $chart_counts = [];
        
        foreach (array_reverse($report_data) as $row) {
            $chart_labels[] = $row['month_name'];
            $chart_totals[] = $row['total_amount'];
            $chart_counts[] = $row['payment_count'];
        }
        
        $chart_data = [
            'labels' => $chart_labels,
            'totals' => $chart_totals,
            'counts' => $chart_counts
        ];
        break;
        
    case 'students':
        // Rapport par étudiant
        $sql = "
            SELECT 
                s.id,
                s.first_name,
                s.last_name,
                s.email,
                s.formation_type,
                s.total_amount,
                s.paid_amount,
                (s.total_amount - s.paid_amount) as balance,
                COUNT(p.id) as payment_count,
                SUM(p.amount) as total_paid,
                MAX(p.payment_date) as last_payment_date
            FROM students s
            LEFT JOIN payments p ON s.id = p.student_id
            WHERE (p.payment_date BETWEEN ? AND ? OR p.id IS NULL)
            GROUP BY s.id, s.first_name, s.last_name, s.email, s.formation_type, s.total_amount, s.paid_amount
            ORDER BY s.last_name, s.first_name
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'payments':
        // Rapport détaillé des paiements
        $sql = "
            SELECT 
                p.*,
                s.first_name,
                s.last_name,
                s.email,
                s.formation_type
            FROM payments p
            JOIN students s ON p.student_id = s.id
            WHERE p.payment_date BETWEEN ? AND ?
            ORDER BY p.payment_date DESC, p.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'formation':
        // Rapport par formation
        $sql = "
            SELECT 
                s.formation_type,
                COUNT(DISTINCT s.id) as student_count,
                SUM(s.total_amount) as total_revenue,
                SUM(s.paid_amount) as total_paid,
                SUM(s.total_amount - s.paid_amount) as total_balance,
                COUNT(p.id) as payment_count,
                SUM(p.amount) as period_paid
            FROM students s
            LEFT JOIN payments p ON s.id = p.student_id AND p.payment_date BETWEEN ? AND ?
            WHERE s.formation_type IS NOT NULL AND s.formation_type != ''
            GROUP BY s.formation_type
            ORDER BY total_revenue DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Calculer les statistiques récapitulatives
$summary_sql = "
    SELECT 
        COUNT(DISTINCT p.id) as total_payments,
        COUNT(DISTINCT p.student_id) as total_students,
        SUM(p.amount) as total_amount,
        AVG(p.amount) as average_payment,
        MIN(p.amount) as min_payment,
        MAX(p.amount) as max_payment
    FROM payments p
    WHERE p.payment_date BETWEEN ? AND ?
";

$stmt = $pdo->prepare($summary_sql);
$stmt->execute([$start_date, $end_date]);
$summary_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistiques par méthode de paiement
$method_sql = "
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(amount) as total
    FROM payments
    WHERE payment_date BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total DESC
";

$method_stmt = $pdo->prepare($method_sql);
$method_stmt->execute([$start_date, $end_date]);
$method_stats = $method_stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion de l'export
if ($export_format) {
    generateExport($export_format, $report_data, $summary_stats, $method_stats, $report_type, $start_date, $end_date);
    exit;
}

// Fonction d'export
function generateExport($format, $data, $summary, $methods, $type, $start_date, $end_date) {
    if ($format === 'excel') {
        exportExcel($data, $summary, $methods, $type, $start_date, $end_date);
    } elseif ($format === 'pdf') {
        exportPDF($data, $summary, $methods, $type, $start_date, $end_date);
    }
}

function exportExcel($data, $summary, $methods, $type, $start_date, $end_date) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="rapport_' . $type . '_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='6'>Rapport " . ucfirst($type) . " - Période du " . formatDate($start_date) . " au " . formatDate($end_date) . "</th></tr>";
    echo "<tr><th colspan='6'>Statistiques récapitulatives</th></tr>";
    echo "<tr>";
    echo "<th>Total paiements</th>";
    echo "<th>Total étudiants</th>";
    echo "<th>Montant total</th>";
    echo "<th>Moyenne paiement</th>";
    echo "<th>Paiement min</th>";
    echo "<th>Paiement max</th>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td>" . ($summary['total_payments'] ?? 0) . "</td>";
    echo "<td>" . ($summary['total_students'] ?? 0) . "</td>";
    echo "<td>" . formatAmount($summary['total_amount'] ?? 0) . "</td>";
    echo "<td>" . formatAmount($summary['average_payment'] ?? 0) . "</td>";
    echo "<td>" . formatAmount($summary['min_payment'] ?? 0) . "</td>";
    echo "<td>" . formatAmount($summary['max_payment'] ?? 0) . "</td>";
    echo "</tr>";
    
    // Données détaillées
    echo "<tr><td colspan='6' style='height: 20px;'></td></tr>";
    echo "<tr><th colspan='6'>Données détaillées</th></tr>";
    
    if (!empty($data)) {
        $headers = array_keys($data[0]);
        echo "<tr>";
        foreach ($headers as $header) {
            echo "<th>" . ucfirst(str_replace('_', ' ', $header)) . "</th>";
        }
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . $cell . "</td>";
            }
            echo "</tr>";
        }
    }
    
    echo "</table>";
}

function exportPDF($data, $summary, $methods, $type, $start_date, $end_date) {
    // Pour une implémentation PDF complète, vous devriez utiliser une bibliothèque comme TCPDF ou Dompdf
    // Voici une version simplifiée pour l'exemple
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="rapport_' . $type . '_' . date('Y-m-d') . '.pdf"');
    
    // Code PDF simplifié - à remplacer par une vraie génération PDF
    echo "<h1>Rapport PDF - Fonctionnalité à implémenter avec TCPDF ou Dompdf</h1>";
    echo "<p>Pour générer des vrais PDF, installez une bibliothèque PDF comme TCPDF.</p>";
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - <?php echo SITE_NAME; ?></title>
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

    .reports-container {
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
        text-align: center;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card.total {
        border-top: 4px solid var(--primary);
    }

    .stat-card.students {
        border-top: 4px solid var(--success);
    }

    .stat-card.average {
        border-top: 4px solid var(--warning);
    }

    .stat-card.range {
        border-top: 4px solid var(--danger);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 20px;
        color: white;
    }

    .stat-card.total .stat-icon {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .stat-card.students .stat-icon {
        background: linear-gradient(135deg, var(--success), #16a34a);
    }

    .stat-card.average .stat-icon {
        background: linear-gradient(135deg, var(--warning), #ea580c);
    }

    .stat-card.range .stat-icon {
        background: linear-gradient(135deg, var(--danger), #dc2626);
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

    /* Report Controls */
    .report-controls {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .controls-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .controls-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .controls-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .control-group {
        margin-bottom: 15px;
    }

    .control-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }

    .control-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }

    .control-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }

    .control-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--light-gray);
        border-radius: 10px;
        font-size: 14px;
        background: white;
        cursor: pointer;
    }

    .control-actions {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .btn-generate {
        padding: 12px 24px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-generate:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }

    /* Report Type Tabs */
    .report-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .report-tab {
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        border: 2px solid transparent;
        text-decoration: none;
        color: var(--dark);
        background: var(--light);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .report-tab:hover {
        background: var(--light-gray);
    }

    .report-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    /* Export Options */
    .export-options {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .btn-export {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .btn-excel {
        background: #21a366;
        color: white;
        border: none;
    }

    .btn-excel:hover {
        background: #198754;
        transform: translateY(-2px);
    }

    .btn-pdf {
        background: #dc3545;
        color: white;
        border: none;
    }

    .btn-pdf:hover {
        background: #bb2d3b;
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
        height: 400px;
        margin-top: 20px;
    }

    /* Method Stats */
    .method-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
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

    /* Report Table */
    .report-table-container {
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

    .table-info {
        font-size: 14px;
        color: var(--gray);
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
    }

    .report-table thead {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .report-table th {
        padding: 18px 16px;
        text-align: left;
        font-weight: 600;
        color: var(--gray);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--light-gray);
    }

    .report-table tbody tr {
        border-bottom: 1px solid var(--light-gray);
        transition: background-color 0.2s;
    }

    .report-table tbody tr:hover {
        background-color: rgba(67, 97, 238, 0.03);
    }

    .report-table td {
        padding: 16px;
        vertical-align: middle;
    }

    /* Status Badges */
    .status-badge {
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

    .status-pending {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .status-partial {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
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
        .reports-container {
            padding: 20px;
        }

        .page-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .controls-grid {
            grid-template-columns: 1fr;
        }

        .report-table {
            display: block;
            overflow-x: auto;
        }
    }

    @media (max-width: 768px) {
        .stats-cards {
            grid-template-columns: 1fr 1fr;
        }

        .method-stats {
            grid-template-columns: 1fr 1fr;
        }

        .table-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .export-options {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .stats-cards {
            grid-template-columns: 1fr;
        }

        .method-stats {
            grid-template-columns: 1fr;
        }

        .report-tabs {
            flex-direction: column;
        }

        .report-tab {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="reports-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-chart-bar"></i>
                    Rapports et Statistiques
                </h1>
                <p style="color: var(--gray); margin-top: 5px;">Analyse détaillée des performances financières</p>
            </div>
        </div>

        <!-- Report Type Tabs -->
        <div class="report-tabs">
            <a href="?report_type=monthly&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                class="report-tab <?php echo $report_type === 'monthly' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                Mensuel
            </a>
            <a href="?report_type=students&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                class="report-tab <?php echo $report_type === 'students' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                Par Étudiant
            </a>
            <a href="?report_type=payments&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                class="report-tab <?php echo $report_type === 'payments' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                Paiements
            </a>
            <a href="?report_type=formation&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                class="report-tab <?php echo $report_type === 'formation' ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i>
                Par Formation
            </a>
        </div>

        <!-- Report Controls -->
        <div class="report-controls">
            <div class="controls-header">
                <h3 class="controls-title">
                    <i class="fas fa-filter"></i>
                    Paramètres du rapport
                </h3>
            </div>
            <form method="GET" class="controls-grid">
                <div class="control-group">
                    <label class="control-label">Période</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <input type="date" name="start_date" class="control-input" value="<?php echo $start_date; ?>">
                        <input type="date" name="end_date" class="control-input" value="<?php echo $end_date; ?>">
                    </div>
                    <div style="display: flex; gap: 5px; margin-top: 10px;">
                        <button type="button" class="btn-small" onclick="setDateRange('today')">Aujourd'hui</button>
                        <button type="button" class="btn-small" onclick="setDateRange('week')">Semaine</button>
                        <button type="button" class="btn-small" onclick="setDateRange('month')">Mois</button>
                        <button type="button" class="btn-small" onclick="setDateRange('year')">Année</button>
                    </div>
                </div>

                <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">

                <div class="control-group">
                    <label class="control-label">Options rapides</label>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="submit" class="btn-generate">
                            <i class="fas fa-sync-alt"></i>
                            Générer le rapport
                        </button>
                        <div class="export-options">
                            <a href="?report_type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=excel"
                                class="btn-export btn-excel">
                                <i class="fas fa-file-excel"></i>
                                Excel
                            </a>
                            <a href="?report_type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=pdf"
                                class="btn-export btn-pdf">
                                <i class="fas fa-file-pdf"></i>
                                PDF
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-cards">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo formatAmount($summary_stats['total_amount'] ?? 0); ?></div>
                <div class="stat-label">Montant Total</div>
            </div>
            <div class="stat-card students">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $summary_stats['total_students'] ?? 0; ?></div>
                <div class="stat-label">Étudiants Actifs</div>
            </div>
            <div class="stat-card average">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo formatAmount($summary_stats['average_payment'] ?? 0); ?></div>
                <div class="stat-label">Moyenne Paiement</div>
            </div>
            <div class="stat-card range">
                <div class="stat-icon">
                    <i class="fas fa-arrows-alt-h"></i>
                </div>
                <div class="stat-value">
                    <?php echo formatAmount($summary_stats['min_payment'] ?? 0); ?> -
                    <?php echo formatAmount($summary_stats['max_payment'] ?? 0); ?>
                </div>
                <div class="stat-label">Fourchette Paiements</div>
            </div>
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
                <div style="font-size: 12px; color: var(--gray); margin-top: 5px;">
                    <?php echo $method['count']; ?> paiement<?php echo $method['count'] > 1 ? 's' : ''; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Chart for Monthly Report -->
        <?php if ($report_type === 'monthly' && !empty($chart_data['labels'])): ?>
        <div class="chart-container">
            <h3 class="controls-title">
                <i class="fas fa-chart-line"></i>
                Évolution des revenus mensuels
            </h3>
            <div class="chart-wrapper">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Report Table -->
        <div class="report-table-container">
            <div class="table-header">
                <h3 class="table-title">
                    <i class="fas fa-table"></i>
                    <?php 
                    $titles = [
                        'monthly' => 'Rapport mensuel des revenus',
                        'students' => 'Liste des étudiants',
                        'payments' => 'Historique des paiements',
                        'formation' => 'Performance par formation'
                    ];
                    echo $titles[$report_type] ?? 'Rapport';
                    ?>
                </h3>
                <div class="table-info">
                    Période : <?php echo formatDate($start_date); ?> au <?php echo formatDate($end_date); ?>
                    | Total : <?php echo count($report_data); ?> enregistrements
                </div>
            </div>

            <?php if (empty($report_data)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <h3>Aucune donnée disponible</h3>
                <p>Aucune donnée trouvée pour la période et les critères sélectionnés.</p>
                <p style="font-size: 14px; margin-top: 10px;">
                    Essayez de modifier la période ou les paramètres de filtrage.
                </p>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="report-table">
                    <thead>
                        <tr>
                            <?php if ($report_type === 'monthly'): ?>
                            <th>Mois</th>
                            <th>Nombre de paiements</th>
                            <th>Étudiants uniques</th>
                            <th>Montant total</th>
                            <th>Moyenne par paiement</th>

                            <?php elseif ($report_type === 'students'): ?>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Montant total</th>
                            <th>Déjà payé</th>
                            <th>Reste à payer</th>
                            <th>Nombre de paiements</th>
                            <th>Dernier paiement</th>
                            <th>Statut</th>

                            <?php elseif ($report_type === 'payments'): ?>
                            <th>Date</th>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>N° Reçu</th>
                            <th>Notes</th>

                            <?php elseif ($report_type === 'formation'): ?>
                            <th>Formation</th>
                            <th>Nombre d'étudiants</th>
                            <th>Revenu total</th>
                            <th>Total payé</th>
                            <th>Solde restant</th>
                            <th>Paiements (période)</th>
                            <th>Montant (période)</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <?php if ($report_type === 'monthly'): ?>
                            <td style="font-weight: 600;"><?php echo $row['month_name']; ?></td>
                            <td><?php echo $row['payment_count']; ?></td>
                            <td><?php echo $row['student_count']; ?></td>
                            <td style="font-weight: 700; color: var(--success);">
                                <?php echo formatAmount($row['total_amount']); ?></td>
                            <td><?php echo formatAmount($row['average_amount']); ?></td>

                            <?php elseif ($report_type === 'students'): 
                                $balance = $row['total_amount'] - $row['paid_amount'];
                                $status = $balance <= 0 ? 'paid' : ($row['paid_amount'] > 0 ? 'partial' : 'pending');
                                $status_class = 'status-' . $status;
                                $status_text = $balance <= 0 ? 'Payé' : ($row['paid_amount'] > 0 ? 'Partiel' : 'En attente');
                            ?>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div
                                        style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                                        <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--gray);">
                                            <?php echo htmlspecialchars($row['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['formation_type']); ?></td>
                            <td style="font-weight: 600;"><?php echo formatAmount($row['total_amount']); ?></td>
                            <td style="color: var(--success);"><?php echo formatAmount($row['paid_amount']); ?></td>
                            <td style="color: var(--danger);"><?php echo formatAmount($balance); ?></td>
                            <td><?php echo $row['payment_count']; ?></td>
                            <td><?php echo $row['last_payment_date'] ? formatDate($row['last_payment_date']) : '—'; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <i
                                        class="fas fa-<?php echo $status === 'paid' ? 'check-circle' : ($status === 'partial' ? 'clock' : 'exclamation-circle'); ?>"></i>
                                    <?php echo $status_text; ?>
                                </span>
                            </td>

                            <?php elseif ($report_type === 'payments'): 
                                $method_class = strtolower($row['payment_method']);
                                if ($method_class === 'espèces') $method_class = 'cash';
                                if ($method_class === 'carte') $method_class = 'card';
                                if ($method_class === 'virement') $method_class = 'transfer';
                                if ($method_class === 'chèque') $method_class = 'check';
                            ?>
                            <td>
                                <div style="font-weight: 600;"><?php echo formatDate($row['payment_date']); ?></div>
                                <div style="font-size: 12px; color: var(--gray);">
                                    <?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['formation_type']); ?></td>
                            <td style="font-weight: 700; color: var(--success);">
                                <?php echo formatAmount($row['amount']); ?></td>
                            <td>
                                <span class="status-badge"
                                    style="background: rgba(67, 97, 238, 0.1); color: var(--primary);">
                                    <?php if ($method_class === 'cash'): ?>
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?php elseif ($method_class === 'card'): ?>
                                    <i class="fas fa-credit-card"></i>
                                    <?php elseif ($method_class === 'transfer'): ?>
                                    <i class="fas fa-university"></i>
                                    <?php elseif ($method_class === 'check'): ?>
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($row['payment_method']); ?>
                                </span>
                            </td>
                            <td style="font-family: 'Courier New', monospace; font-weight: 600;">
                                <?php echo htmlspecialchars($row['receipt_number']); ?></td>
                            <td>
                                <?php if ($row['notes']): ?>
                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                    title="<?php echo htmlspecialchars($row['notes']); ?>">
                                    <?php echo htmlspecialchars($row['notes']); ?>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">—</span>
                                <?php endif; ?>
                            </td>

                            <?php elseif ($report_type === 'formation'): ?>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($row['formation_type']); ?></td>
                            <td><?php echo $row['student_count']; ?></td>
                            <td style="font-weight: 700;"><?php echo formatAmount($row['total_revenue']); ?></td>
                            <td style="color: var(--success);"><?php echo formatAmount($row['total_paid']); ?></td>
                            <td style="color: var(--danger);"><?php echo formatAmount($row['total_balance']); ?></td>
                            <td><?php echo $row['payment_count']; ?></td>
                            <td style="font-weight: 600; color: var(--primary);">
                                <?php echo formatAmount($row['period_paid']); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--light);">
                            <td colspan="<?php echo $report_type === 'monthly' ? 5 : ($report_type === 'students' ? 8 : ($report_type === 'payments' ? 7 : 7)); ?>"
                                style="padding: 20px; text-align: right;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="font-weight: 600; color: var(--dark);">
                                        <?php echo count($report_data); ?>
                                        enregistrement<?php echo count($report_data) > 1 ? 's' : ''; ?>
                                    </div>
                                    <div style="font-size: 18px; font-weight: 700; color: var(--primary);">
                                        <?php if ($report_type === 'monthly'): ?>
                                        Total général :
                                        <?php echo formatAmount(array_sum(array_column($report_data, 'total_amount'))); ?>
                                        <?php elseif ($report_type === 'students'): ?>
                                        Total restant :
                                        <?php echo formatAmount(array_sum(array_map(function($r) { return $r['total_amount'] - $r['paid_amount']; }, $report_data))); ?>
                                        <?php elseif ($report_type === 'payments'): ?>
                                        Total période :
                                        <?php echo formatAmount(array_sum(array_column($report_data, 'amount'))); ?>
                                        <?php elseif ($report_type === 'formation'): ?>
                                        Total formations :
                                        <?php echo formatAmount(array_sum(array_column($report_data, 'total_revenue'))); ?>
                                        <?php endif; ?>
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

    <script>
    // Format currency for FCFA
    function formatFCFA(amount) {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    }

    // Set date range shortcuts
    function setDateRange(range) {
        const today = new Date();
        let start, end;

        switch (range) {
            case 'today':
                start = end = today.toISOString().split('T')[0];
                break;
            case 'week':
                const weekAgo = new Date(today);
                weekAgo.setDate(today.getDate() - 7);
                start = weekAgo.toISOString().split('T')[0];
                end = today.toISOString().split('T')[0];
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

    // Monthly Chart
    <?php if ($report_type === 'monthly' && !empty($chart_data['labels'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_data['labels']); ?>,
                datasets: [{
                        label: 'Montant total',
                        data: <?php echo json_encode($chart_data['totals']); ?>,
                        backgroundColor: 'rgba(67, 97, 238, 0.7)',
                        borderColor: '#4361ee',
                        borderWidth: 2,
                        borderRadius: 5,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Nombre de paiements',
                        data: <?php echo json_encode($chart_data['counts']); ?>,
                        type: 'line',
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 3,
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
                                if (context.datasetIndex === 0) {
                                    label += formatFCFA(context.raw);
                                } else {
                                    label += context.raw + ' paiement' + (context.raw > 1 ? 's' :
                                        '');
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Montant (FCFA)'
                        },
                        ticks: {
                            callback: function(value) {
                                return formatFCFA(value);
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Nombre de paiements'
                        },
                        grid: {
                            drawOnChartArea: false
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
    });
    <?php endif; ?>

    // Add animations to table rows
    document.addEventListener('DOMContentLoaded', function() {
        const tableRows = document.querySelectorAll('.report-table tbody tr');
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
            
            .btn-small {
                padding: 6px 12px;
                background: var(--light);
                border: 1px solid var(--light-gray);
                border-radius: 6px;
                font-size: 12px;
                color: var(--gray);
                cursor: pointer;
                transition: all 0.3s;
            }
            
            .btn-small:hover {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
            }
        `;
        document.head.appendChild(style);
    });

    // Print report
    function printReport() {
        window.print();
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printReport();
        }

        // Ctrl + E for Excel export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('export', 'excel');
            window.location.href = currentUrl.toString();
        }
    });
    </script>
</body>

</html>