<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

$student_id = (int)($_GET['id'] ?? $_POST['student_id'] ?? 0);
if ($student_id <= 0) {
    header('Location: students.php');
    exit();
}

$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    header('Location: students.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $formation_type = trim($_POST['formation_type'] ?? '');
    $total_amount = (float)($_POST['total_amount'] ?? 0);

    try {
        $sql = "UPDATE students
                SET first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    formation_type = ?,
                    total_amount = ?,
                    balance = ? - paid_amount
                WHERE id = ?";
        $update = $pdo->prepare($sql);
        $update->execute([
            $first_name,
            $last_name,
            $email,
            $phone,
            $formation_type,
            $total_amount,
            $total_amount,
            $student_id
        ]);

        $_SESSION['success_message'] = "Étudiant modifié avec succès.";
        header('Location: student_details.php?id=' . $student_id . '&success=1');
        exit();
    } catch (PDOException $e) {
        $error_message = "Impossible de modifier l'étudiant : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier étudiant - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container" style="max-width:800px;margin:30px auto;background:#fff;padding:24px;border-radius:10px;">
    <h1>Modifier l'étudiant</h1>
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <div class="form-group"><label>Prénom</label><input class="form-control" name="first_name" required value="<?php echo htmlspecialchars($student['first_name']); ?>"></div>
        <div class="form-group"><label>Nom</label><input class="form-control" name="last_name" required value="<?php echo htmlspecialchars($student['last_name']); ?>"></div>
        <div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" required value="<?php echo htmlspecialchars($student['email']); ?>"></div>
        <div class="form-group"><label>Téléphone</label><input class="form-control" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"></div>
        <div class="form-group"><label>Formation</label><input class="form-control" name="formation_type" value="<?php echo htmlspecialchars($student['formation_type'] ?? ''); ?>"></div>
        <div class="form-group"><label>Montant total</label><input type="number" step="0.01" min="0" class="form-control" name="total_amount" required value="<?php echo htmlspecialchars((string)$student['total_amount']); ?>"></div>

        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary" type="submit" name="save_student">Enregistrer</button>
            <a class="btn" href="student_details.php?id=<?php echo $student_id; ?>">Annuler</a>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
