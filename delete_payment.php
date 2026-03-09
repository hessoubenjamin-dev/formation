<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

// Vérifier si l'utilisateur est admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Vous n'avez pas les droits pour supprimer des paiements";
    header('Location: payments.php');
    exit;
}

// Vérifier si l'ID est présent
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de paiement invalide";
    header('Location: payments.php');
    exit;
}

$payment_id = $_GET['id'];

try {
    // Commencer une transaction
    $pdo->beginTransaction();

    // Récupérer les informations du paiement pour le message de confirmation
    $stmt = $pdo->prepare("SELECT p.*, s.first_name, s.last_name 
        FROM payments p 
        JOIN students s ON p.student_id = s.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("Paiement non trouvé");
    }

    // Supprimer le paiement
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$payment_id]);

    // Valider la transaction
    $pdo->commit();

    $_SESSION['success'] = "Le paiement " . htmlspecialchars($payment['receipt_number']) . " de " . 
                           htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) . 
                           " a été supprimé avec succès";

} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $pdo->rollBack();
    $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
}

// Rediriger vers la page des paiements
header('Location: payments.php');
exit;
?>