<?php
session_start();

// Inclure la configuration
require_once 'config/database.php';

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if ($username && $new_password) {
        // Hacher le nouveau mot de passe
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Mettre à jour le mot de passe
        $sql = "UPDATE users SET password = ? WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$hashed_password, $username])) {
            $message = "Mot de passe mis à jour avec succès pour l'utilisateur: $username";
            $message .= "<br>Nouveau mot de passe: $new_password";
        } else {
            $error = "Erreur lors de la mise à jour du mot de passe";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Réinitialiser le mot de passe</title>
    <style>
    body {
        font-family: Arial;
        padding: 20px;
    }

    .container {
        max-width: 500px;
        margin: 0 auto;
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 5px;
    }

    input {
        width: 100%;
        padding: 8px;
    }

    button {
        padding: 10px 20px;
        background: #007bff;
        color: white;
        border: none;
    }

    .alert {
        padding: 10px;
        margin-bottom: 15px;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>Réinitialiser le mot de passe</h1>

        <?php if (isset($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="username" value="admin" required>
            </div>

            <div class="form-group">
                <label>Nouveau mot de passe</label>
                <input type="text" name="new_password" value="admin123" required>
            </div>

            <button type="submit">Réinitialiser le mot de passe</button>
        </form>

        <p style="margin-top: 20px;">
            <a href="login.php">Retour à la connexion</a>
        </p>
    </div>
</body>

</html>