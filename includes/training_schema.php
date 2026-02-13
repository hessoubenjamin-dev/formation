<?php

function initializeTrainingSchema(PDO $pdo): void {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $queries = [
        "CREATE TABLE IF NOT EXISTS trainings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            duration_months INT NOT NULL,
            technology_watch TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_trainings_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS training_modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            training_id INT NOT NULL,
            module_title VARCHAR(255) NOT NULL,
            module_order INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_training_modules_training FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
            INDEX idx_training_modules_training (training_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS training_resources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            training_id INT NOT NULL,
            module_id INT NULL,
            resource_type ENUM('exercice', 'devoir', 'video', 'session_note') NOT NULL,
            title VARCHAR(255) NOT NULL,
            resource_url VARCHAR(500) NULL,
            description TEXT NULL,
            due_date DATE NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_training_resources_training FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
            CONSTRAINT fk_training_resources_module FOREIGN KEY (module_id) REFERENCES training_modules(id) ON DELETE SET NULL,
            INDEX idx_training_resources_training (training_id),
            INDEX idx_training_resources_module (module_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS student_trainings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            training_id INT NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student_training (student_id, training_id),
            CONSTRAINT fk_student_trainings_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_student_trainings_training FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS student_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            training_id INT NOT NULL,
            module_id INT NULL,
            resource_id INT NULL,
            status ENUM('non_commence', 'en_cours', 'valide') NOT NULL DEFAULT 'non_commence',
            validated_by INT NULL,
            validated_at TIMESTAMP NULL,
            comment TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_progress (student_id, training_id, module_id, resource_id),
            CONSTRAINT fk_student_progress_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_student_progress_training FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
            CONSTRAINT fk_student_progress_module FOREIGN KEY (module_id) REFERENCES training_modules(id) ON DELETE CASCADE,
            CONSTRAINT fk_student_progress_resource FOREIGN KEY (resource_id) REFERENCES training_resources(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id INT NULL",
        "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'formateur', 'apprenant') DEFAULT 'manager'",
    ];

    foreach ($queries as $query) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            // Ne pas bloquer l'application si un schéma existe déjà différemment.
        }
    }

    $initialized = true;
}
