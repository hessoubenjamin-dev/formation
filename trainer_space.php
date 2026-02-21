<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/trainer_space_helpers.php';
requireRoles(['admin', 'manager', 'formateur']);

syncTrainingsFromStudents($pdo);
$stats = getTrainerStats($pdo);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace formateur - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f1f5f9; }
        .dashboard-layout { max-width: 1400px; margin: 20px auto; padding: 0 20px 20px; display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 20px; }
        .sidebar { background: #0f172a; color: #e2e8f0; border-radius: 16px; padding: 22px; position: sticky; top: 20px; height: fit-content; }
        .sidebar h2 { color: #fff; margin-bottom: 6px; }
        .sidebar p { margin-bottom: 18px; color: #94a3b8; font-size: 14px; }
        .menu-list { list-style: none; margin: 0; padding: 0; }
        .menu-list li + li { margin-top: 8px; }
        .menu-list a { display: block; color: #cbd5e1; text-decoration: none; padding: 10px 12px; border-radius: 10px; transition: .2s; }
        .menu-list a:hover, .menu-list a.active { background: #1e293b; color: #fff; }
        .content { min-width: 0; }
        .card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 6px 20px rgba(15,23,42,.08); }
        .hero { margin-bottom: 16px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin: 20px 0; }
        .stat-card { background: #fff; border-radius: 12px; padding: 14px; box-shadow: 0 6px 20px rgba(15,23,42,.08); }
        .stat-card span { display: block; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: .04em; }
        .stat-card strong { font-size: 26px; color: #0f172a; }
        .links-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; }
        .menu-card { display:block; text-decoration:none; color:#0f172a; background:#fff; border:1px solid #e2e8f0; padding:14px; border-radius:10px; }
        .menu-card:hover { border-color:#4361ee; }
        @media (max-width: 980px) { .dashboard-layout { grid-template-columns: 1fr; } .sidebar { position: static; } }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="dashboard-layout">
    <?php renderTrainerSidebar($currentPage); ?>

    <div class="content">
        <div class="hero card">
            <h1>Espace formateur</h1>
            <p>Accès rapide à chaque module du menu, avec une page dédiée et bien structurée.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><span>Formations</span><strong><?php echo $stats['formations']; ?></strong></div>
            <div class="stat-card"><span>Modules</span><strong><?php echo $stats['modules']; ?></strong></div>
            <div class="stat-card"><span>Ressources</span><strong><?php echo $stats['ressources']; ?></strong></div>
            <div class="stat-card"><span>Apprenants</span><strong><?php echo $stats['apprenants']; ?></strong></div>
        </div>

        <div class="links-grid">
            <a class="menu-card" href="trainer_create_training.php">Créer une formation</a>
            <a class="menu-card" href="trainer_add_module.php">Ajouter un module</a>
            <a class="menu-card" href="trainer_add_resource.php">Ajouter une ressource</a>
            <a class="menu-card" href="trainer_assign_learner.php">Affecter un apprenant</a>
            <a class="menu-card" href="trainer_validate_progress.php">Valider la progression</a>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
