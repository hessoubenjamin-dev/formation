<?php
// Connexion simple à la base
$host = 'localhost';
$dbname = 'gestion_formation';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier si l'utilisateur admin existe déjà
    $check_sql = "SELECT COUNT(*) as count FROM users WHERE username = 'admin'";
    $stmt = $pdo->query($check_sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mot de passe : admin123
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    
    if ($result['count'] > 0) {
        // Mettre à jour le mot de passe existant
        $sql = "UPDATE users SET password = ?, email = 'admin@formation.com', 
                full_name = 'Administrateur', role = 'admin' 
                WHERE username = 'admin'";
        $action = "mis à jour";
    } else {
        // Insérer un nouvel admin
        $sql = "INSERT INTO users (username, password, email, full_name, role) 
                VALUES ('admin', ?, 'admin@formation.com', 'Administrateur', 'admin')";
        $action = "créé";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hashed_password]);
    
    echo "Admin $action avec succès!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo '<a href="login.php">Se connecter</a>';
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
    
    // Afficher plus de détails pour le débogage
    echo "<br><br>Détails du débogage:<br>";
    echo "Host: $host<br>";
    echo "Database: $dbname<br>";
    echo "User: $user<br>";
    
    // Vérifier la table users
    echo "<br>Contenu de la table users:<br>";
    try {
        $users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($users);
        echo "</pre>";
    } catch (Exception $e2) {
        echo "Impossible de lire la table users: " . $e2->getMessage();
    }
}
?>