<?php
// Inclure les fonctions si ce n'est pas déjà fait
if (!function_exists('isLoggedIn')) {
    require_once 'functions.php';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary: #4361ee;
        --primary-dark: #3a0ca3;
        --secondary: #4cc9f0;
        --success: #22c55e;
        --warning: #f97316;
        --danger: #ef4444;
        --light: #f8fafc;
        --dark: #1e293b;
        --gray: #64748b;
        --light-gray: #e2e8f0;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background-color: #f8fafc;
        color: var(--dark);
        min-height: 100vh;
    }

    /* Header Principal */
    .main-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 15px 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .header-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Logo */
    .logo {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .logo-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .logo-text h1 {
        font-size: 24px;
        font-weight: 700;
        line-height: 1.2;
    }

    .logo-text p {
        font-size: 13px;
        opacity: 0.9;
        margin-top: 3px;
    }

    /* User Info */
    .user-info-container {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 15px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
    }

    .user-profile:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--secondary), var(--primary));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
        color: white;
    }

    .user-details {
        text-align: right;
    }

    .user-name {
        font-weight: 600;
        font-size: 15px;
        display: block;
    }

    .user-role {
        font-size: 12px;
        opacity: 0.8;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 2px;
    }

    .user-role.admin {
        color: #ffd700;
    }

    .user-role.manager {
        color: #4cc9f0;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .logout-btn:hover {
        background: var(--danger);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
    }

    /* Navigation */
    .main-nav {
        background: white;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        position: sticky;
        top: 80px;
        z-index: 999;
    }

    .nav-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 30px;
    }

    .nav-menu {
        display: flex;
        list-style: none;
        gap: 5px;
    }

    .nav-item {
        position: relative;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 18px 25px;
        color: var(--gray);
        text-decoration: none;
        font-weight: 500;
        font-size: 15px;
        transition: all 0.3s;
        border-bottom: 3px solid transparent;
    }

    .nav-link:hover {
        color: var(--primary);
        background: rgba(67, 97, 238, 0.05);
    }

    .nav-link.active {
        color: var(--primary);
        border-bottom: 3px solid var(--primary);
        background: rgba(67, 97, 238, 0.08);
    }

    .nav-icon {
        font-size: 16px;
        width: 20px;
        text-align: center;
    }

    .nav-badge {
        margin-left: 8px;
        background: var(--danger);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
        min-width: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* User Dropdown */
    .user-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 280px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        margin-top: 10px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s;
        z-index: 1001;
        overflow: hidden;
    }

    .user-profile:hover .user-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        text-align: center;
    }

    .dropdown-avatar {
        width: 60px;
        height: 60px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 20px;
        font-weight: 600;
        color: var(--primary);
    }

    .dropdown-email {
        font-size: 13px;
        opacity: 0.9;
    }

    .dropdown-menu {
        padding: 10px 0;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: var(--dark);
        text-decoration: none;
        transition: all 0.2s;
    }

    .dropdown-item:hover {
        background: rgba(67, 97, 238, 0.08);
        color: var(--primary);
        padding-left: 25px;
    }

    .dropdown-item i {
        width: 20px;
        text-align: center;
        color: var(--gray);
    }

    .dropdown-item:hover i {
        color: var(--primary);
    }

    .dropdown-divider {
        height: 1px;
        background: var(--light-gray);
        margin: 8px 0;
    }

    /* Notifications */
    .notifications {
        position: relative;
    }

    .notification-btn {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }

    .notification-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: var(--danger);
        color: white;
        font-size: 11px;
        font-weight: 600;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Main Content */
    main {
        max-width: 1400px;
        margin: 30px auto;
        padding: 0 30px;
        min-height: calc(100vh - 180px);
    }

    /* Breadcrumbs */
    .breadcrumbs {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 25px;
        font-size: 14px;
        color: var(--gray);
        padding: 10px 15px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .breadcrumbs a {
        color: var(--gray);
        text-decoration: none;
        transition: color 0.3s;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .breadcrumbs a:hover {
        color: var(--primary);
    }

    .breadcrumbs .separator {
        opacity: 0.5;
    }

    .breadcrumbs .current {
        color: var(--dark);
        font-weight: 500;
    }

    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .action-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        text-decoration: none;
        color: var(--dark);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border: 1px solid var(--light-gray);
    }

    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: var(--primary);
    }

    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
    }

    .action-card.student .action-icon {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .action-card.payment .action-icon {
        background: linear-gradient(135deg, var(--success), #16a34a);
    }

    .action-card.report .action-icon {
        background: linear-gradient(135deg, var(--warning), #ea580c);
    }

    .action-card.settings .action-icon {
        background: linear-gradient(135deg, var(--danger), #dc2626);
    }

    .action-content h4 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .action-content p {
        font-size: 13px;
        color: var(--gray);
    }

    /* Footer */
    .main-footer {
        background: white;
        border-top: 1px solid var(--light-gray);
        padding: 20px 0;
        margin-top: 40px;
    }

    .footer-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--gray);
        font-size: 14px;
    }

    .footer-status {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .status-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-item.online {
        color: var(--success);
    }

    .status-item.connected {
        color: var(--primary);
    }

    /* Responsive */
    @media (max-width: 1024px) {

        .header-container,
        .nav-container,
        main,
        .footer-container {
            padding: 0 20px;
        }

        .nav-menu {
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .nav-link {
            padding: 15px 20px;
            white-space: nowrap;
        }

        .user-dropdown {
            position: fixed;
            top: 80px;
            right: 20px;
            width: calc(100% - 40px);
            max-width: 320px;
        }
    }

    @media (max-width: 768px) {
        .header-container {
            flex-direction: column;
            gap: 15px;
            padding: 15px 20px;
        }

        .user-info-container {
            width: 100%;
            justify-content: space-between;
        }

        .nav-menu {
            justify-content: space-between;
        }

        .nav-link span:not(.nav-icon) {
            display: none;
        }

        .nav-link {
            padding: 15px;
            border-radius: 8px;
        }

        .nav-link.active {
            border-bottom: 3px solid var(--primary);
        }

        .breadcrumbs {
            display: none;
        }

        .quick-actions {
            grid-template-columns: 1fr;
        }

        .footer-container {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .footer-status {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .logo-text h1 {
            font-size: 20px;
        }

        .logo-text p {
            font-size: 12px;
        }

        .user-name {
            font-size: 14px;
        }

        .logout-btn span {
            display: none;
        }

        .logout-btn {
            padding: 10px;
            width: 45px;
            justify-content: center;
        }
    }
    </style>
</head>

<body>
    <?php if (isLoggedIn()): ?>
    <!-- Header Principal -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <h1><?php echo SITE_NAME; ?></h1>
                    <p>Système de gestion des paiements</p>
                </div>
            </div>

            <div class="user-info-container">
                <!-- Notifications -->
                <div class="notifications">
                    <button class="notification-btn" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                </div>

                <!-- User Profile -->
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                        $initials = '';
                        $name_parts = explode(' ', $_SESSION['full_name']);
                        if (count($name_parts) >= 2) {
                            $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                        } else {
                            $initials = strtoupper(substr($_SESSION['full_name'], 0, 2));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                        <span class="user-role <?php echo $_SESSION['role']; ?>">
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                            <i class="fas fa-crown"></i> Administrateur
                            <?php else: ?>
                            <i class="fas fa-user-tie"></i> Gestionnaire
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- User Dropdown -->
                    <div class="user-dropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <?php echo $initials; ?>
                            </div>
                            <h4><?php echo $_SESSION['full_name']; ?></h4>
                            <p class="dropdown-email">
                                <?php 
                                // On pourrait récupérer l'email depuis la session ou la base
                                echo $_SESSION['role'] . '@formation.com';
                                ?>
                            </p>
                        </div>
                        <div class="dropdown-menu">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>Mon profil</span>
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                <span>Paramètres</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Déconnexion</span>
                            </a>
                        </div>
                    </div>
                </div>

                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="main-nav">
        <div class="nav-container">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i style="color:black" ; class="fas fa-tachometer-alt nav-icon"></i>
                        <span style="color:black" ;>Tableau de bord</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="students.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
                        <i style="color:black" ; class="fas fa-users nav-icon"></i>
                        <span style="color:black" ;>Étudiants</span>
                        <?php 
                        // Compter les étudiants (exemple)
                        try {
                            if (isset($pdo)) {
                                $count_sql = "SELECT COUNT(*) as count FROM students";
                                $stmt = $pdo->query($count_sql);
                                $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                if ($student_count > 0) {
                                    echo '<span class="nav-badge">' . $student_count . '</span>';
                                }
                            }
                        } catch (Exception $e) {
                            // Ignorer l'erreur
                        }
                        ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payments.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                        <i style="color:black" ; class="fas fa-credit-card nav-icon"></i>
                        <span style="color:black" ;>Paiements</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="trainer_space.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'trainer_space.php' ? 'active' : ''; ?>">
                        <i style="color:black" ; class="fas fa-chalkboard-teacher nav-icon"></i>
                        <span style="color:black" ;>Espace formateur</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="learner_space.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'learner_space.php' ? 'active' : ''; ?>">
                        <i style="color:black" ; class="fas fa-user-graduate nav-icon"></i>
                        <span style="color:black" ;>Espace apprenant</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="raports.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i style="color:black" ; class="fas fa-chart-bar nav-icon"></i>
                        <span style="color:black" ;>Rapports</span>
                    </a>
                </li>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <!-- <li class="nav-item">
                    <a href="users.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i style="color:black" ; class="fas fa-user-cog nav-icon"></i>
                        <span style="color:black" ;>Utilisateurs</span>
                    </a>
                </li> -->
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <?php else: ?>
    <!-- Header pour les pages non connectées (login, etc.) -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <h1><?php echo SITE_NAME; ?></h1>
                    <p>Système de gestion des paiements</p>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <main>
        <?php if (isLoggedIn()): ?>
        <!-- Breadcrumbs -->
        <div class="breadcrumbs" id="breadcrumbs">
            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Accueil</span>
            </a>
            <span class="separator">/</span>
            <?php
            $current_page = basename($_SERVER['PHP_SELF']);
            $page_names = [
                'dashboard.php' => 'Tableau de bord',
                'students.php' => 'Étudiants',
                'payments.php' => 'Paiements',
                'add_payment.php' => 'Nouveau paiement',
                'reports.php' => 'Rapports',
                'users.php' => 'Utilisateurs',
                'settings.php' => 'Paramètres',
                'profile.php' => 'Mon profil',
                'trainer_space.php' => 'Espace formateur',
                'learner_space.php' => 'Espace apprenant'
            ];
            
            if (isset($page_names[$current_page])) {
                echo '<span class="current">' . $page_names[$current_page] . '</span>';
            } else {
                echo '<span class="current">Page actuelle</span>';
            }
            ?>
        </div>

        <!-- Quick Actions (seulement sur dashboard) -->
        <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
        <div class="quick-actions">
            <a href="students.php?action=add" class="action-card student">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-content">
                    <h4>Ajouter un étudiant</h4>
                    <p>Enregistrer un nouvel étudiant</p>
                </div>
            </a>

            <a href="add_payment.php" class="action-card payment">
                <div class="action-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="action-content">
                    <h4>Enregistrer un paiement</h4>
                    <p>Créer un nouveau reçu</p>
                </div>
            </a>

            <a href="reports.php" class="action-card report">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-content">
                    <h4>Générer un rapport</h4>
                    <p>Export PDF et Excel</p>
                </div>
            </a>

            <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="settings.php" class="action-card settings">
                <div class="action-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="action-content">
                    <h4>Paramètres système</h4>
                    <p>Configuration avancée</p>
                </div>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Le contenu de chaque page sera inséré ici -->