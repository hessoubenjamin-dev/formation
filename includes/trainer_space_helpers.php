<?php

function syncTrainingsFromStudents(PDO $pdo): void
{
    $studentFormations = $pdo->query("SELECT DISTINCT TRIM(formation_type) AS formation_type FROM students WHERE formation_type IS NOT NULL AND TRIM(formation_type) != ''")->fetchAll(PDO::FETCH_COLUMN);

    if (!$studentFormations) {
        return;
    }

    $existingTrainings = $pdo->query('SELECT id, title FROM trainings')->fetchAll(PDO::FETCH_ASSOC);
    $existingTitles = [];

    foreach ($existingTrainings as $training) {
        $normalizedTitle = mb_strtolower(trim((string) ($training['title'] ?? '')));
        if ($normalizedTitle !== '') {
            $existingTitles[$normalizedTitle] = true;
        }
    }

    $insertStmt = $pdo->prepare('INSERT INTO trainings (title, duration_months, technology_watch, created_by) VALUES (?, ?, ?, ?)');

    foreach ($studentFormations as $formationTitle) {
        $formationTitle = trim((string) $formationTitle);
        if ($formationTitle === '') {
            continue;
        }

        $normalizedTitle = mb_strtolower($formationTitle);
        if (!isset($existingTitles[$normalizedTitle])) {
            $insertStmt->execute([$formationTitle, 1, null, $_SESSION['user_id'] ?? null]);
            $existingTitles[$normalizedTitle] = true;
        }
    }
}

function getTrainerSidebarItems(): array
{
    return [
        'trainer_space.php' => 'Dashboard',
        'trainer_create_training.php' => 'Créer une formation',
        'trainer_add_module.php' => 'Ajouter un module',
        'trainer_add_resource.php' => 'Ajouter une ressource',
        'trainer_assign_learner.php' => 'Affecter un apprenant',
        'trainer_validate_progress.php' => 'Valider la progression',
    ];
}

function renderTrainerSidebar(string $currentPage): void
{
    $items = getTrainerSidebarItems();
    ?>
    <aside class="sidebar">
        <h2>Dashboard</h2>
        <p>Espace formateur</p>
        <ul class="menu-list">
            <li><a href="trainer_space.php" class="<?php echo $currentPage === 'trainer_space.php' ? 'active' : ''; ?>">Page /trainer_space.php</a></li>
            <?php foreach ($items as $file => $label): ?>
                <?php if ($file === 'trainer_space.php') {
                    continue;
                } ?>
                <li><a href="<?php echo $file; ?>" class="<?php echo $currentPage === $file ? 'active' : ''; ?>"><?php echo $label; ?></a></li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <?php
}

function getTrainerStats(PDO $pdo): array
{
    $trainings = $pdo->query('SELECT COUNT(*) FROM trainings')->fetchColumn();
    $modules = $pdo->query('SELECT COUNT(*) FROM training_modules')->fetchColumn();
    $resources = $pdo->query('SELECT COUNT(*) FROM training_resources')->fetchColumn();
    $students = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

    return [
        'formations' => (int) $trainings,
        'modules' => (int) $modules,
        'ressources' => (int) $resources,
        'apprenants' => (int) $students,
    ];
}
