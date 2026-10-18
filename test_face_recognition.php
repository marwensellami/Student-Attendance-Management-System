<?php
// Fichier de test pour la reconnaissance faciale
$test_photo = "C:\\xampp\\htdocs\\presence_cours\\assets\\photo_etudiant\\etudiant_test.jpg";

if (!file_exists($test_photo)) {
    die("Photo de test non trouvée: $test_photo");
}

$pythonPath = "C:\\Users\\msi\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";
$scriptPath = "C:\\xampp\\htdocs\\presence_cours\\check_face.py";
$command = "\"$pythonPath\" \"$scriptPath\" \"$test_photo\" 2>&1";

echo "Commande exécutée: $command<br><br>";

$output = shell_exec($command);

echo "<h3>Résultat:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Test direct en ligne de commande
echo "<h3>Test ligne de commande:</h3>";
echo "<pre>";
system($command);
echo "</pre>";
?>