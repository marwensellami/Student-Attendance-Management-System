<?php
// Configuration de la base de données
define('DB_HOST', 'localhost:4306');  // Ajout du port 4306
define('DB_NAME', 'presence_cours');
define('DB_USER', 'root');
define('DB_PASS', '');  // Laissez vide si pas de mot de passe

// Configuration de l'application
define('APP_NAME', 'Système de Suivi de Présence');
define('APP_VERSION', '1.0');
define('BASE_URL', 'http://localhost/presence_cours');

// Configuration des sessions
define('SESSION_TIMEOUT', 3600); // 1 heure en secondes

// Niveaux d'accès
define('ROLE_ADMIN', 'admin');
define('ROLE_ENSEIGNANT', 'enseignant');
define('ROLE_ETUDIANT', 'etudiant');

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=localhost;port=4306;dbname=presence_cours;charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}

// Fonction de débuggage (à désactiver en production)
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}
?>