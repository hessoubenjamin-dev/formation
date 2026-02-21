<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/trainer_space_helpers.php';
requireRoles(['admin', 'manager', 'formateur']);

syncTrainingsFromStudents($pdo);
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_student'])) {
    try {
        $student_id = (int) ($_POST['student_id'] ?? 0);
        $training_id = (int) ($_POST['training_id'] ?? 0);
        if ($student_id < 1 || $training_id < 1) {
            throw new RuntimeException('Veuillez choisir un apprenant et une formation.');
        }
        $stmt = $pdo->prepare('INSERT IGNORE INTO student_trainings (student_id, training_id) VALUES (?, ?)');
        $stmt->execute([$student_id, $training_id]);
        $success_message = 'Apprenant affecté à la formation.';
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}
$trainings = $pdo->query('SELECT id, title FROM trainings ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
$students = $pdo->query('SELECT id, first_name, last_name FROM students ORDER BY first_name, last_name')->fetchAll(PDO::FETCH_ASSOC);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Affecter un apprenant - <?php echo SITE_NAME; ?></title><link rel="stylesheet" href="assets/css/style.css"><style>body{background:#f1f5f9}.dashboard-layout{max-width:1400px;margin:20px auto;padding:0 20px 20px;display:grid;grid-template-columns:260px minmax(0,1fr);gap:20px}.sidebar{background:#0f172a;color:#e2e8f0;border-radius:16px;padding:22px;position:sticky;top:20px;height:fit-content}.sidebar h2{color:#fff}.sidebar p{margin:8px 0 18px;color:#94a3b8}.menu-list{list-style:none;padding:0}.menu-list li+li{margin-top:8px}.menu-list a{display:block;color:#cbd5e1;text-decoration:none;padding:10px 12px;border-radius:10px}.menu-list a:hover,.menu-list a.active{background:#1e293b;color:#fff}.card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(15,23,42,.08)}select{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:8px}button{background:#4361ee;color:#fff;border:0;padding:10px 14px;border-radius:8px;cursor:pointer}.alert{padding:12px;border-radius:8px;margin-bottom:15px}.ok{background:#dcfce7;color:#166534}.ko{background:#fee2e2;color:#991b1b}@media(max-width:980px){.dashboard-layout{grid-template-columns:1fr}.sidebar{position:static}}</style></head><body>
<?php include 'includes/header.php'; ?>
<div class="dashboard-layout"><?php renderTrainerSidebar($currentPage); ?><div class="card"><h1>Affecter un apprenant</h1><?php if($success_message):?><div class="alert ok"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?><?php if($error_message):?><div class="alert ko"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?><form method="post"><select name="student_id" required><option value="">Apprenant</option><?php foreach($students as $student): ?><option value="<?php echo (int)$student['id']; ?>"><?php echo htmlspecialchars($student['first_name'].' '.$student['last_name']); ?></option><?php endforeach; ?></select><select name="training_id" required><option value="">Formation</option><?php foreach($trainings as $training): ?><option value="<?php echo (int)$training['id']; ?>"><?php echo htmlspecialchars($training['title']); ?></option><?php endforeach; ?></select><button type="submit" name="assign_student">Affecter</button></form></div></div>
<?php include 'includes/footer.php'; ?>
