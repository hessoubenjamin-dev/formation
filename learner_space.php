<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireLogin();

$student_id = 0;
if (($_SESSION['role'] ?? '') === 'apprenant' && !empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT student_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $student_id = (int) $stmt->fetchColumn();
}

if ($student_id < 1) {
    $student_id = (int) ($_GET['student_id'] ?? 0);
}

$students = [];
if (in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'formateur'], true)) {
    $students = $pdo->query('SELECT id, first_name, last_name FROM students ORDER BY first_name, last_name')->fetchAll(PDO::FETCH_ASSOC);
}

$student = null;
$program = [];
if ($student_id > 0) {
    $studentStmt = $pdo->prepare('SELECT id, first_name, last_name, formation_type FROM students WHERE id = ?');
    $studentStmt->execute([$student_id]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    $programStmt = $pdo->prepare(
        "SELECT t.id as training_id, t.title, t.duration_months, t.technology_watch,
                tm.id as module_id, tm.module_title, tm.module_order,
                tr.id as resource_id, tr.resource_type, tr.title as resource_title, tr.resource_url, tr.due_date,
                COALESCE(sp.status, 'non_commence') as status
         FROM student_trainings st
         JOIN trainings t ON t.id = st.training_id
         LEFT JOIN training_modules tm ON tm.training_id = t.id
         LEFT JOIN training_resources tr ON tr.training_id = t.id AND (tr.module_id = tm.id OR tr.module_id IS NULL)
         LEFT JOIN student_progress sp ON sp.student_id = st.student_id AND sp.resource_id = tr.id
         WHERE st.student_id = ?
         ORDER BY t.created_at DESC, tm.module_order ASC, tr.created_at ASC"
    );
    $programStmt->execute([$student_id]);
    $program = $programStmt->fetchAll(PDO::FETCH_ASSOC);
}

$stats = ['total' => 0, 'validated' => 0, 'progress' => 0];
if (!empty($program)) {
    foreach ($program as $row) {
        if (!empty($row['resource_id'])) {
            $stats['total']++;
            if ($row['status'] === 'valide') {
                $stats['validated']++;
            }
        }
    }
    $stats['progress'] = $stats['total'] > 0 ? (int) round(($stats['validated'] / $stats['total']) * 100) : 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace apprenant - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background:#fff; border-radius: 12px; box-shadow:0 6px 20px rgba(0,0,0,.08); padding: 18px; margin-bottom: 15px; }
        .chip { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .valide { background:#dcfce7; color:#166534; }
        .en_cours { background:#fef9c3; color:#854d0e; }
        .non_commence { background:#fee2e2; color:#991b1b; }
        .progress { height: 10px; background:#e5e7eb; border-radius:99px; overflow:hidden; }
        .fill { height:100%; background:#4f46e5; }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <h1>Espace apprenant</h1>
    <p>Consultez votre programme, modules et progression validée par le formateur.</p>

    <?php if (!empty($students)): ?>
    <div class="card">
        <form method="get">
            <label for="student_id">Choisir un apprenant</label>
            <select id="student_id" name="student_id" onchange="this.form.submit()">
                <option value="">-- Sélectionner --</option>
                <?php foreach ($students as $s): ?>
                <option value="<?php echo (int) $s['id']; ?>" <?php echo $student_id === (int) $s['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($student): ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
        <p>Formation actuelle: <?php echo htmlspecialchars($student['formation_type'] ?: 'Non renseignée'); ?></p>
        <p>Progression globale: <?php echo $stats['progress']; ?>%</p>
        <div class="progress"><div class="fill" style="width: <?php echo $stats['progress']; ?>%"></div></div>
    </div>

    <?php if (empty($program)): ?>
    <div class="card">Aucun programme assigné pour cet apprenant.</div>
    <?php else: ?>
    <?php foreach ($program as $row): ?>
    <div class="card">
        <strong><?php echo htmlspecialchars($row['title']); ?></strong>
        <p>Durée: <?php echo (int) $row['duration_months']; ?> mois</p>
        <?php if (!empty($row['technology_watch'])): ?><p>Veille technologique: <?php echo nl2br(htmlspecialchars($row['technology_watch'])); ?></p><?php endif; ?>
        <?php if (!empty($row['module_title'])): ?><p>Module <?php echo (int) $row['module_order']; ?>: <?php echo htmlspecialchars($row['module_title']); ?></p><?php endif; ?>
        <?php if (!empty($row['resource_title'])): ?>
            <p><?php echo htmlspecialchars($row['resource_type']); ?> - <?php echo htmlspecialchars($row['resource_title']); ?></p>
            <?php if (!empty($row['resource_url'])): ?><p><a href="<?php echo htmlspecialchars($row['resource_url']); ?>" target="_blank">Voir la ressource</a></p><?php endif; ?>
            <span class="chip <?php echo htmlspecialchars($row['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $row['status'])); ?></span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php else: ?>
    <div class="card">Sélectionnez un apprenant pour voir son programme et sa progression.</div>
    <?php endif; ?>
</div>
</body>
</html>
