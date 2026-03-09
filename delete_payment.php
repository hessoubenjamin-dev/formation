<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

$payment_id = (int)($_GET['id'] ?? 0);
if ($payment_id <= 0) {
    header('Location: payments.php');
    exit();
}

$stmt = $pdo->prepare('SELECT id, student_id, amount FROM payments WHERE id = ?');
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    $_SESSION['error_message'] = 'Paiement introuvable.';
    header('Location: payments.php');
    exit();
}

$amount = (float)$payment['amount'];
$studentId = (int)$payment['student_id'];

$pdo->beginTransaction();
try {
    $del = $pdo->prepare('DELETE FROM payments WHERE id = ?');
    $del->execute([$payment_id]);

    $upd = $pdo->prepare('UPDATE students SET paid_amount = GREATEST(0, paid_amount - ?), balance = total_amount - GREATEST(0, paid_amount - ?) WHERE id = ?');
    $upd->execute([$amount, $amount, $studentId]);

    $pdo->commit();
    $_SESSION['success_message'] = 'Paiement supprimé avec succès.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Erreur lors de la suppression du paiement.';
}

if (!empty($_GET['student_id'])) {
    header('Location: student_details.php?id=' . (int)$_GET['student_id']);
    exit();
}

header('Location: payments.php');
exit();
