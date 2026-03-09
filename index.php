<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - Gestion des Paiements Formation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    .welcome-container {
        max-width: 760px;
        margin: 90px auto;
        padding: 40px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        text-align: center;
    }

    .welcome-container h1 {
        margin-bottom: 10px;
    }

    .welcome-container p {
        color: #555;
        line-height: 1.6;
        margin-bottom: 20px;
    }

    .welcome-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .welcome-note {
        margin-top: 25px;
        font-size: 14px;
        color: #666;
        background: #f4f8ff;
        padding: 12px;
        border-radius: 8px;
    }
    </style>
</head>

<body>
    <div class="welcome-container">
        <h1>Bienvenue sur votre plateforme</h1>
        <p>
            Cette page d'accueil est maintenant modifiable et visible sans connexion.
            Connectez-vous pour accéder au tableau de bord et à la gestion complète.
        </p>

        <div class="welcome-actions">
            <a class="btn btn-primary" href="login.php">Se connecter</a>
            <a class="btn" href="default.php">Voir la page par défaut</a>
        </div>

        <div class="welcome-note">
            Conseil : pour modifier ce contenu, éditez le fichier <strong>index.php</strong>.
        </div>
    </div>
</body>

</html>
