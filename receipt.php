<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

$payment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($payment_id <= 0) {
    header('Location: payments.php');
    exit();
}

$sql = "SELECT p.*, s.first_name, s.last_name, s.email, s.phone, s.formation_type
        FROM payments p
        JOIN students s ON p.student_id = s.id
        WHERE p.id = ?
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: payments.php');
    exit();
}

$logo_path = defined('COMPANY_LOGO_PATH') ? COMPANY_LOGO_PATH : 'assets/images/logo.png';
$logo_exists = is_file(__DIR__ . '/' . $logo_path);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu <?php echo htmlspecialchars($payment['receipt_number']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body {
        margin: 0;
        padding: 32px;
        font-family: Arial, sans-serif;
        background: #f5f7fb;
        color: #1e293b;
    }

    .receipt-page {
        max-width: 860px;
        margin: 0 auto;
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 12px;
    }

    .btn {
        border: none;
        border-radius: 8px;
        padding: 10px 16px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: #2563eb;
        color: #fff;
    }

    .btn-light {
        background: #e2e8f0;
        color: #0f172a;
    }

    .receipt-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        padding: 36px;
    }

    .receipt-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 20px;
        margin-bottom: 20px;
        gap: 16px;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .brand img {
        max-height: 62px;
        max-width: 220px;
        object-fit: contain;
    }

    .brand-fallback {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: #eff6ff;
        color: #1d4ed8;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 24px;
        font-weight: 700;
    }

    .receipt-title {
        text-align: right;
    }

    .receipt-title h1 {
        margin: 0;
        font-size: 28px;
        color: #0f172a;
    }

    .receipt-number {
        margin-top: 8px;
        font-weight: 700;
        color: #334155;
    }

    .meta-grid,
    .amount-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 12px;
    }

    .item {
        background: #f8fafc;
        border-radius: 10px;
        padding: 14px;
    }

    .label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 6px;
    }

    .value {
        font-weight: 600;
        color: #0f172a;
    }

    .amount-highlight {
        background: #ecfdf5;
    }

    .amount-highlight .value {
        color: #166534;
        font-size: 20px;
    }

    .footer-note {
        margin-top: 26px;
        padding-top: 16px;
        border-top: 1px dashed #cbd5e1;
        font-size: 13px;
        color: #475569;
    }

    @media print {
        body {
            background: #fff;
            padding: 0;
        }

        .toolbar {
            display: none;
        }

        .receipt-card {
            box-shadow: none;
            border-radius: 0;
            padding: 0;
        }
    }
    </style>
</head>

<body>
    <div class="receipt-page">
        <div class="toolbar">
            <a href="javascript:history.back()" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-file-export"></i>
                Exporter / Imprimer</button>
        </div>

        <div class="receipt-card">
            <div class="receipt-header">
                <div>
                    <div class="brand">
                        <?php if ($logo_exists): ?>
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo de l'entreprise">
                        <?php else: ?>
                        <div class="brand-fallback"><?php echo strtoupper(substr(SITE_NAME, 0, 1)); ?></div>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo SITE_NAME; ?></strong><br>
                            <small>Reçu officiel de paiement</small>
                        </div>
                    </div>
                </div>

                <div class="receipt-title">
                    <h1>REÇU</h1>
                    <div class="receipt-number">N° <?php echo htmlspecialchars($payment['receipt_number']); ?></div>
                </div>
            </div>

            <div class="meta-grid">
                <div class="item">
                    <div class="label">Étudiant</div>
                    <div class="value"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Formation</div>
                    <div class="value"><?php echo htmlspecialchars($payment['formation_type']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Date de paiement</div>
                    <div class="value"><?php echo formatDate($payment['payment_date']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Méthode de paiement</div>
                    <div class="value"><?php echo htmlspecialchars($payment['payment_method']); ?></div>
                </div>
                <?php if (!empty($payment['payment_month'])): ?>
                <div class="item">
                    <div class="label">Mois payé</div>
                    <div class="value"><?php echo htmlspecialchars(formatMonthYear($payment['payment_month'])); ?></div>
                </div>
                <?php endif; ?>
                <div class="item">
                    <div class="label">Email</div>
                    <div class="value"><?php echo htmlspecialchars($payment['email'] ?? '-'); ?></div>
                </div>
            </div>

            <div class="amount-grid">
                <div class="item amount-highlight">
                    <div class="label">Montant payé</div>
                    <div class="value"><?php echo formatAmount($payment['amount']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Date d'émission du reçu</div>
                    <div class="value"><?php echo formatDate(date('Y-m-d')); ?></div>
                </div>
            </div>

            <?php if (!empty($payment['notes'])): ?>
            <div class="item" style="margin-top: 14px;">
                <div class="label">Notes</div>
                <div class="value"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></div>
            </div>
            <?php endif; ?>

            <div class="footer-note">
                Merci pour votre paiement. Ce reçu est généré automatiquement et peut être exporté en PDF depuis la
                fenêtre d'impression de votre navigateur.
            </div>
        </div>
    </div>
</body>

</html>
