<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/trainer_space_helpers.php';
requireRoles(['admin', 'manager', 'formateur']);

syncTrainingsFromStudents($pdo);
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_module'])) {
    try {
        $training_id = (int) ($_POST['training_id'] ?? 0);
        $module_title = trim($_POST['module_title'] ?? '');
        $module_order = max(1, (int) ($_POST['module_order'] ?? 1));
        if ($training_id < 1 || $module_title === '') {
            throw new RuntimeException('Veuillez sélectionner une formation et saisir le nom du module.');
        }
        $stmt = $pdo->prepare('INSERT INTO training_modules (training_id, module_title, module_order) VALUES (?, ?, ?)');
        $stmt->execute([$training_id, $module_title, $module_order]);
        $success_message = 'Module ajouté avec succès.';
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}
$trainings = $pdo->query('SELECT id, title FROM trainings ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Ajouter un module - <?php echo SITE_NAME; ?></title><link rel="stylesheet" href="assets/css/style.css"><style>body{background:#f1f5f9}.dashboard-layout{max-width:1400px;margin:20px auto;padding:0 20px 20px;display:grid;grid-template-columns:260px minmax(0,1fr);gap:20px}.sidebar{background:#0f172a;color:#e2e8f0;border-radius:16px;padding:22px;position:sticky;top:20px;height:fit-content}.sidebar h2{color:#fff}.sidebar p{margin:8px 0 18px;color:#94a3b8}.menu-list{list-style:none;padding:0}.menu-list li+li{margin-top:8px}.menu-list a{display:block;color:#cbd5e1;text-decoration:none;padding:10px 12px;border-radius:10px}.menu-list a:hover,.menu-list a.active{background:#1e293b;color:#fff}.card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(15,23,42,.08)}input,select{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:8px}button{background:#4361ee;color:#fff;border:0;padding:10px 14px;border-radius:8px;cursor:pointer}.alert{padding:12px;border-radius:8px;margin-bottom:15px}.ok{background:#dcfce7;color:#166534}.ko{background:#fee2e2;color:#991b1b}@media(max-width:980px){.dashboard-layout{grid-template-columns:1fr}.sidebar{position:static}}</style></head><body>
<?php include 'includes/header.php'; ?>
<div class="dashboard-layout"><?php renderTrainerSidebar($currentPage); ?><div class="card"><h1>Ajouter un module</h1><?php if($success_message):?><div class="alert ok"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?><?php if($error_message):?><div class="alert ko"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?><form method="post"><select name="training_id" required><option value="">Formation</option><?php foreach($trainings as $training): ?><option value="<?php echo (int)$training['id']; ?>"><?php echo htmlspecialchars($training['title']); ?></option><?php endforeach; ?></select><input type="text" name="module_title" placeholder="Nom du module" required><input type="number" name="module_order" min="1" value="1" required><button type="submit" name="add_module">Ajouter</button></form></div></div>
<?php include 'includes/footer.php'; ?>
