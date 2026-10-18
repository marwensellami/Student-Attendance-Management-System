<?php
require_once 'includes/config.php';

try {
    $sql = "SELECT id, nom, prenom, photo_path FROM users WHERE role = 'etudiant' AND photo_path IS NOT NULL";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll();
    
    echo "<h2>Étudiants avec photos dans la base :</h2>";
    foreach ($students as $student) {
        echo "<p>{$student['prenom']} {$student['nom']} - Photo: {$student['photo_path']}</p>";
        
        // Vérifier si le fichier existe
        $full_path = __DIR__ . '/' . $student['photo_path'];
        if (file_exists($full_path)) {
            echo "<p style='color: green'>✅ Fichier existe: $full_path</p>";
        } else {
            echo "<p style='color: red'>❌ Fichier introuvable: $full_path</p>";
        }
    }
    
    if (empty($students)) {
        echo "<p style='color: red'>Aucun étudiant avec photo trouvé dans la base !</p>";
    }
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}
?>