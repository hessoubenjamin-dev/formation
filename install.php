<?php
// Démarrer la session
session_start();

// Page d'installation automatique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? 'localhost';
    $dbname = $_POST['dbname'] ?? 'gestion_formation';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    $admin_pass = $_POST['admin_password'] ?? 'admin123';
    
    try {
        // Connexion à MySQL
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Créer la base de données
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
        $pdo->exec("USE $dbname");
        
        // Créer les tables
        $sql_tables = "
        -- Table des étudiants
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            phone VARCHAR(20),
            formation_type VARCHAR(100),
            total_amount DECIMAL(10,2) DEFAULT 0,
            paid_amount DECIMAL(10,2) DEFAULT 0,
            balance DECIMAL(10,2) DEFAULT 0,
            registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        -- Table des paiements
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_method ENUM('Espèces', 'Carte', 'Virement', 'Chèque') NOT NULL,
            receipt_number VARCHAR(50) UNIQUE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        -- Table des utilisateurs (administrateurs)
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            full_name VARCHAR(100),
            role ENUM('admin', 'manager') DEFAULT 'manager',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql_tables);
        
        // Hacher le mot de passe admin
        $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
        
        // Insérer l'administrateur
        $sql_admin = "INSERT INTO users (username, password, email, full_name, role) 
                     VALUES (?, ?, ?, ?, 'admin')";
        $stmt = $pdo->prepare($sql_admin);
        $stmt->execute(['admin', $hashed_password, 'admin@formation.com', 'Administrateur Principal']);
        
        // Créer aussi un utilisateur manager
        $sql_manager = "INSERT INTO users (username, password, email, full_name, role) 
                       VALUES (?, ?, ?, ?, 'manager')";
        $stmt = $pdo->prepare($sql_manager);
        $stmt->execute(['manager', password_hash('manager123', PASSWORD_DEFAULT), 'manager@formation.com', 'Gestionnaire']);
        
        // Créer le fichier de configuration
        $config_content = "<?php
session_start();

// Configuration de la base de données
define('DB_HOST', '$host');
define('DB_NAME', '$dbname');
define('DB_USER', '$username');
define('DB_PASS', '$password');

// Connexion à la base de données
try {
    \$pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException \$e) {
    die('Erreur de connexion à la base de données: ' . \$e->getMessage());
}

// Configuration du site
define('SITE_NAME', 'Gestion des Paiements Formation');
define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['PHP_SELF']) . '/');
?>";

// Créer le dossier config s'il n'existe pas
if (!file_exists('config')) {
mkdir('config', 0777, true);
}

file_put_contents('config/database.php', $config_content);

// Message de succès
$_SESSION['install_success'] = true;
header('Location: install.php?success=1');
exit();

} catch (PDOException $e) {
$error = "Erreur: " . $e->getMessage();
}
}

// Vérifier si l'installation a réussi
if (isset($_GET['success'])) {
$success_message = "Installation réussie! Identifiants par défaut:<br>
<strong>Admin:</strong> admin / admin123<br>
<strong>Manager:</strong> manager / manager123<br>
<a href='login.php' class='btn btn-success'>Se connecter</a>";
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Système de Gestion</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .install-container {
        background: white;
        border-radius: 15px;
        padding: 40px;
        width: 100%;
        max-width: 500px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    h1 {
        color: #333;
        text-align: center;
        margin-bottom: 30px;
        font-size: 28px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
    }

    .btn {
        display: block;
        width: 100%;
        padding: 14px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s;
    }

    .btn:hover {
        background: #5a67d8;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .success-message {
        text-align: center;
        padding: 20px;
    }

    .btn-success {
        background: #48bb78;
    }

    .btn-success:hover {
        background: #38a169;
    }

    .login-link {
        text-align: center;
        margin-top: 20px;
    }

    .login-link a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }

    .login-link a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <div class="install-container">
        <h1>🔧 Installation du Système</h1>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
        <div class="login-link">
            <a href="login.php">Cliquez ici si la redirection ne se fait pas automatiquement</a>
        </div>
        <script>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
        </script>
        <?php else: ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Serveur MySQL</label>
                <input type="text" name="host" class="form-control" value="localhost" required>
            </div>

            <div class="form-group">
                <label>Nom de la base de données</label>
                <input type="text" name="dbname" class="form-control" value="gestion_formation" required>
            </div>

            <div class="form-group">
                <label>Nom d'utilisateur MySQL</label>
                <input type="text" name="username" class="form-control" value="root" required>
            </div>

            <div class="form-group">
                <label>Mot de passe MySQL</label>
                <input type="password" name="password" class="form-control">
            </div>

            <div class="form-group">
                <label>Mot de passe administrateur</label>
                <input type="password" name="admin_password" class="form-control" value="admin123" required>
                <small style="color: #666; font-size: 14px;">Ce sera le mot de passe du compte admin</small>
            </div>

            <button type="submit" class="btn">Installer le système</button>
        </form>

        <?php endif; ?>
    </div>
</body>

</html>