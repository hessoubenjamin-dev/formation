<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

function hasColumn(PDO $pdo, $table, $column) {
    $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$has_payment_month = hasColumn($pdo, 'payments', 'payment_month');
$payment_id = (int)($_GET['id'] ?? $_POST['payment_id'] ?? 0);
if ($payment_id <= 0) {
    header('Location: payments.php');
    exit();
}

$selectSql = "SELECT p.*, s.first_name, s.last_name FROM payments p JOIN students s ON s.id = p.student_id WHERE p.id = ?";
$stmt = $pdo->prepare($selectSql);
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    header('Location: payments.php');
    exit();
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'Espèces';
    $notes = trim($_POST['notes'] ?? '');
    $payment_month = $has_payment_month ? ($_POST['payment_month'] ?? null) : null;

    $old_amount = (float)$payment['amount'];
    $delta = $amount - $old_amount;

    $pdo->beginTransaction();
    try {
        if ($has_payment_month) {
            $updatePayment = $pdo->prepare("UPDATE payments SET amount=?, payment_date=?, payment_month=?, payment_method=?, notes=? WHERE id=?");
            $updatePayment->execute([$amount, $payment_date, $payment_month, $payment_method, $notes, $payment_id]);
        } else {
            $updatePayment = $pdo->prepare("UPDATE payments SET amount=?, payment_date=?, payment_method=?, notes=? WHERE id=?");
            $updatePayment->execute([$amount, $payment_date, $payment_method, $notes, $payment_id]);
        }

        $updateStudent = $pdo->prepare("UPDATE students SET paid_amount = paid_amount + ?, balance = total_amount - (paid_amount + ?) WHERE id = ?");
        $updateStudent->execute([$delta, $delta, $payment['student_id']]);

        $pdo->commit();
        $_SESSION['success_message'] = 'Paiement modifié avec succès.';

        if (!empty($_GET['student_id'])) {
            header('Location: student_details.php?id=' . (int)$_GET['student_id'] . '&success=1');
        } else {
            header('Location: payments.php');
        }
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Erreur lors de la modification : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier paiement - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container" style="max-width:800px;margin:30px auto;background:#fff;padding:24px;border-radius:10px;">
    <h1>Modifier le paiement</h1>
    <p>Étudiant : <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong></p>

    <?php if ($error_message): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
        <div class="form-group"><label>Montant</label><input class="form-control" type="number" step="0.01" min="0" name="amount" required value="<?php echo htmlspecialchars((string)$payment['amount']); ?>"></div>
        <div class="form-group"><label>Date</label><input class="form-control" type="date" name="payment_date" required value="<?php echo htmlspecialchars($payment['payment_date']); ?>"></div>
        <?php if ($has_payment_month): ?>
        <div class="form-group"><label>Mois payé</label><input class="form-control" type="month" name="payment_month" value="<?php echo !empty($payment['payment_month']) ? htmlspecialchars(substr($payment['payment_month'],0,7)) : ''; ?>"></div>
        <?php endif; ?>
        <div class="form-group"><label>Méthode</label>
            <select class="form-control" name="payment_method" required>
                <?php foreach (['Espèces','Carte','Virement','Chèque'] as $method): ?>
                    <option value="<?php echo $method; ?>" <?php echo $payment['payment_method'] === $method ? 'selected' : ''; ?>><?php echo $method; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Notes</label><textarea class="form-control" name="notes"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea></div>

        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary" type="submit" name="save_payment">Enregistrer</button>
            <a class="btn" href="<?php echo !empty($_GET['student_id']) ? 'student_details.php?id=' . (int)$_GET['student_id'] : 'payments.php'; ?>">Annuler</a>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
