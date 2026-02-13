<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
requireRoles(['admin', 'manager', 'formateur']);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_training'])) {
            $title = trim($_POST['title'] ?? '');
            $duration_months = max(1, (int) ($_POST['duration_months'] ?? 1));
            $technology_watch = trim($_POST['technology_watch'] ?? '');

            if ($title === '') {
                throw new RuntimeException('Le titre de la formation est obligatoire.');
            }

            $stmt = $pdo->prepare('INSERT INTO trainings (title, duration_months, technology_watch, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$title, $duration_months, $technology_watch ?: null, $_SESSION['user_id'] ?? null]);
            $success_message = 'Formation créée avec succès.';
        }

        if (isset($_POST['add_module'])) {
            $training_id = (int) ($_POST['training_id'] ?? 0);
            $module_title = trim($_POST['module_title'] ?? '');
            $module_order = max(1, (int) ($_POST['module_order'] ?? 1));

            if ($training_id < 1 || $module_title === '') {
                throw new RuntimeException('Veuillez sélectionner une formation et saisir le nom du module.');
            }

            $stmt = $pdo->prepare('INSERT INTO training_modules (training_id, module_title, module_order) VALUES (?, ?, ?)');
            $stmt->execute([$training_id, $module_title, $module_order]);
            $success_message = 'Module ajouté avec succès.';
        }

        if (isset($_POST['add_resource'])) {
            $training_id = (int) ($_POST['training_id'] ?? 0);
            $module_id = (int) ($_POST['module_id'] ?? 0);
            $resource_type = $_POST['resource_type'] ?? '';
            $title = trim($_POST['resource_title'] ?? '');
            $resource_url = trim($_POST['resource_url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $due_date = $_POST['due_date'] ?? null;

            $allowed_types = ['exercice', 'devoir', 'video', 'session_note'];
            if ($training_id < 1 || $title === '' || !in_array($resource_type, $allowed_types, true)) {
                throw new RuntimeException('Veuillez renseigner les champs obligatoires de la ressource.');
            }

            $stmt = $pdo->prepare('INSERT INTO training_resources (training_id, module_id, resource_type, title, resource_url, description, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$training_id, $module_id ?: null, $resource_type, $title, $resource_url ?: null, $description ?: null, $due_date ?: null, $_SESSION['user_id'] ?? null]);
            $success_message = 'Ressource pédagogique ajoutée.';
        }

        if (isset($_POST['assign_student'])) {
            $student_id = (int) ($_POST['student_id'] ?? 0);
            $training_id = (int) ($_POST['training_id'] ?? 0);

            if ($student_id < 1 || $training_id < 1) {
                throw new RuntimeException('Veuillez choisir un apprenant et une formation.');
            }

            $stmt = $pdo->prepare('INSERT IGNORE INTO student_trainings (student_id, training_id) VALUES (?, ?)');
            $stmt->execute([$student_id, $training_id]);
            $success_message = 'Apprenant affecté à la formation.';
        }

        if (isset($_POST['validate_progress'])) {
            $student_id = (int) ($_POST['student_id'] ?? 0);
            $training_id = (int) ($_POST['training_id'] ?? 0);
            $resource_id = (int) ($_POST['resource_id'] ?? 0);
            $status = $_POST['status'] ?? 'non_commence';
            $comment = trim($_POST['comment'] ?? '');
            $allowed_status = ['non_commence', 'en_cours', 'valide'];

            if (!in_array($status, $allowed_status, true) || $student_id < 1 || $training_id < 1 || $resource_id < 1) {
                throw new RuntimeException('Données de progression invalides.');
            }

            $moduleStmt = $pdo->prepare('SELECT module_id FROM training_resources WHERE id = ?');
            $moduleStmt->execute([$resource_id]);
            $module_id = $moduleStmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO student_progress (student_id, training_id, module_id, resource_id, status, validated_by, validated_at, comment)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), validated_by = VALUES(validated_by), validated_at = VALUES(validated_at), comment = VALUES(comment)'
            );
            $stmt->execute([
                $student_id,
                $training_id,
                $module_id ?: null,
                $resource_id,
                $status,
                $_SESSION['user_id'] ?? null,
                $status === 'valide' ? date('Y-m-d H:i:s') : null,
                $comment ?: null,
            ]);
            $success_message = 'Progression mise à jour.';
        }
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$trainings = $pdo->query('SELECT * FROM trainings ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$modules = $pdo->query('SELECT tm.*, t.title as training_title FROM training_modules tm JOIN trainings t ON t.id = tm.training_id ORDER BY t.title, tm.module_order')->fetchAll(PDO::FETCH_ASSOC);
$students = $pdo->query('SELECT id, first_name, last_name FROM students ORDER BY first_name, last_name')->fetchAll(PDO::FETCH_ASSOC);
$resources = $pdo->query('SELECT tr.*, t.title AS training_title FROM training_resources tr JOIN trainings t ON t.id = tr.training_id ORDER BY tr.created_at DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace formateur - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container { max-width: 1300px; margin: 20px auto; padding: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 6px 20px rgba(0,0,0,.08); }
        .card h3 { margin-bottom: 12px; }
        input, select, textarea { width: 100%; padding: 10px; margin-bottom: 10px; border:1px solid #ddd; border-radius: 8px; }
        button { background:#4361ee; color:#fff; border:0; padding:10px 14px; border-radius:8px; cursor:pointer; }
        .alert { padding:12px; border-radius:8px; margin-bottom:15px; }
        .ok { background:#dcfce7; color:#166534; }
        .ko { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
    <h1>Espace formateur</h1>
    <p>Ajoutez les exercices, devoirs, liens vidéos, modules, durée de formation et suivi de veille technologique.</p>

    <?php if ($success_message): ?><div class="alert ok"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert ko"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3>1) Créer une formation</h3>
            <form method="post">
                <input type="text" name="title" placeholder="Nom de la formation" required>
                <input type="number" name="duration_months" min="1" placeholder="Durée (en mois)" required>
                <textarea name="technology_watch" placeholder="Veille technologique (IA, cybersécurité, cloud, etc.)"></textarea>
                <button type="submit" name="create_training">Créer</button>
            </form>
        </div>

        <div class="card">
            <h3>2) Ajouter un module</h3>
            <form method="post">
                <select name="training_id" required>
                    <option value="">Formation</option>
                    <?php foreach ($trainings as $training): ?>
                    <option value="<?php echo (int) $training['id']; ?>"><?php echo htmlspecialchars($training['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="module_title" placeholder="Nom du module" required>
                <input type="number" name="module_order" min="1" value="1" required>
                <button type="submit" name="add_module">Ajouter</button>
            </form>
        </div>

        <div class="card">
            <h3>3) Ajouter une ressource (exo/devoir/vidéo/session)</h3>
            <form method="post">
                <select name="training_id" required>
                    <option value="">Formation</option>
                    <?php foreach ($trainings as $training): ?>
                    <option value="<?php echo (int) $training['id']; ?>"><?php echo htmlspecialchars($training['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="module_id">
                    <option value="">Module (optionnel)</option>
                    <?php foreach ($modules as $module): ?>
                    <option value="<?php echo (int) $module['id']; ?>"><?php echo htmlspecialchars($module['training_title'] . ' - ' . $module['module_title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="resource_type" required>
                    <option value="exercice">Exercice</option>
                    <option value="devoir">Devoir</option>
                    <option value="video">Lien vidéo</option>
                    <option value="session_note">Session passée</option>
                </select>
                <input type="text" name="resource_title" placeholder="Titre" required>
                <input type="url" name="resource_url" placeholder="Lien (si vidéo/support)">
                <textarea name="description" placeholder="Description"></textarea>
                <input type="date" name="due_date">
                <button type="submit" name="add_resource">Ajouter</button>
            </form>
        </div>

        <div class="card">
            <h3>4) Affecter un apprenant à une formation</h3>
            <form method="post">
                <select name="student_id" required>
                    <option value="">Apprenant</option>
                    <?php foreach ($students as $student): ?>
                    <option value="<?php echo (int) $student['id']; ?>"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="training_id" required>
                    <option value="">Formation</option>
                    <?php foreach ($trainings as $training): ?>
                    <option value="<?php echo (int) $training['id']; ?>"><?php echo htmlspecialchars($training['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="assign_student">Affecter</button>
            </form>
        </div>

        <div class="card">
            <h3>5) Valider la progression (exo/devoir/projet)</h3>
            <form method="post">
                <select name="student_id" required>
                    <option value="">Apprenant</option>
                    <?php foreach ($students as $student): ?>
                    <option value="<?php echo (int) $student['id']; ?>"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="training_id" required>
                    <option value="">Formation</option>
                    <?php foreach ($trainings as $training): ?>
                    <option value="<?php echo (int) $training['id']; ?>"><?php echo htmlspecialchars($training['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="resource_id" required>
                    <option value="">Ressource</option>
                    <?php foreach ($resources as $resource): ?>
                    <option value="<?php echo (int) $resource['id']; ?>"><?php echo htmlspecialchars($resource['training_title'] . ' - ' . $resource['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" required>
                    <option value="non_commence">Non commencé</option>
                    <option value="en_cours">En cours</option>
                    <option value="valide">Validé</option>
                </select>
                <textarea name="comment" placeholder="Commentaire"></textarea>
                <button type="submit" name="validate_progress">Mettre à jour</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
