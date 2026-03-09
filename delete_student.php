<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

$student_id = (int)($_GET['id'] ?? 0);
if ($student_id <= 0) {
    header('Location: students.php');
    exit();
}

try {
    $stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
    $stmt->execute([$student_id]);
    $_SESSION['success_message'] = "Étudiant supprimé avec succès.";
} catch (Exception $e) {
    $_SESSION['error_message'] = "Erreur lors de la suppression de l'étudiant.";
}

header('Location: students.php');
exit();
