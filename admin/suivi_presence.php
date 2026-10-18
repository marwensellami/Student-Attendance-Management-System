<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un administrateur
requireAuth(ROLE_ADMIN);

$course_id = $_GET['course_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$error = '';
$success = '';

try {
    global $pdo;
    
    // Récupérer la liste des cours
    $sql = "SELECT c.*, u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM courses c 
            JOIN users u ON c.enseignant_id = u.id 
            ORDER BY c.titre";
    $stmt = $pdo->query($sql);
    $courses = $stmt->fetchAll();
    
    // Récupérer les séances si un cours est sélectionné
    $sessions = [];
    if ($course_id) {
        $sql = "SELECT * FROM sessions WHERE course_id = ? ORDER BY date_session DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id]);
        $sessions = $stmt->fetchAll();
    }
    
    // Récupérer les présences si une séance est sélectionnée
    $attendances = [];
    $session_info = null;
    if ($session_id) {
        // Informations de la séance
        $sql = "SELECT s.*, c.titre as course_titre, c.code as course_code
                FROM sessions s 
                JOIN courses c ON s.course_id = c.id 
                WHERE s.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$session_id]);
        $session_info = $stmt->fetch();
        
        // Présences pour cette séance
        $sql = "SELECT a.*, u.nom, u.prenom, u.email
                FROM attendance a
                JOIN users u ON a.student_id = u.id
                WHERE a.session_id = ?
                ORDER BY u.nom, u.prenom";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$session_id]);
        $attendances = $stmt->fetchAll();
        
        // Marquer les présences
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
            foreach ($_POST['attendance'] as $student_id => $statut) {
                // Vérifier si l'enregistrement existe déjà
                $sql = "SELECT id FROM attendance WHERE session_id = ? AND student_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$session_id, $student_id]);
                
                if ($stmt->fetch()) {
                    // Mise à jour
                    $sql = "UPDATE attendance SET statut = ?, date_marquage = NOW(), marked_by = ? 
                            WHERE session_id = ? AND student_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$statut, $_SESSION['user_id'], $session_id, $student_id]);
                } else {
                    // Insertion
                    $sql = "INSERT INTO attendance (session_id, student_id, statut, marked_by) 
                            VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$session_id, $student_id, $statut, $_SESSION['user_id']]);
                }
            }
            
            logAction($_SESSION['user_id'], 'attendance_updated', "Mise à jour des présences pour la séance ID: $session_id");
            $success = "Présences mises à jour avec succès !";
            
            // Recharger les présences
            $sql = "SELECT a.*, u.nom, u.prenom, u.email
                    FROM attendance a
                    JOIN users u ON a.student_id = u.id
                    WHERE a.session_id = ?
                    ORDER BY u.nom, u.prenom";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$session_id]);
            $attendances = $stmt->fetchAll();
        }
    }
    
    // Récupérer les étudiants inscrits au cours
    $students = [];
    if ($course_id) {
        $sql = "SELECT u.id, u.nom, u.prenom, u.email
                FROM users u
                JOIN enrollments e ON u.id = e.student_id
                WHERE e.course_id = ? AND u.is_active = 1
                ORDER BY u.nom, u.prenom";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id]);
        $students = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Erreur suivi présence: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi Présence - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Suivi des Présences</h1>
            <p>Consultez et gérez les présences des étudiants</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Sélection du cours et de la séance -->
        <div class="card">
            <div class="card-header">
                <h3>Sélection</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Sélectionner un cours</label>
                            <select name="course_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Choisir un cours</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                        <?php echo $course_id == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo $c['code'] . ' - ' . $c['titre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <?php if ($course_id): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Sélectionner une séance</label>
                                <select name="session_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Choisir une séance</option>
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" 
                                            <?php echo $session_id == $s['id'] ? 'selected' : ''; ?>>
                                            <?php echo formatDate($s['date_session'], 'd/m/Y H:i') . ' - ' . $s['titre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Informations de la séance -->
        <?php if ($session_info): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Informations de la Séance</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <strong>Cours:</strong> <?php echo $session_info['course_code'] . ' - ' . $session_info['course_titre']; ?>
                        </div>
                        <div class="col-4">
                            <strong>Séance:</strong> <?php echo $session_info['titre']; ?>
                        </div>
                        <div class="col-4">
                            <strong>Date:</strong> <?php echo formatDate($session_info['date_session']); ?>
                        </div>
                    </div>
                    <?php if ($session_info['description']): ?>
                        <div class="mt-2">
                            <strong>Description:</strong> <?php echo $session_info['description']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire de gestion des présences -->
        <?php if ($session_id && !empty($students)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Gestion des Présences</h3>
                    <span class="badge badge-info"><?php echo count($students); ?> étudiants inscrits</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Email</th>
                                        <th>Statut</th>
                                        <th>Remarque</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): 
                                        // Trouver l'enregistrement de présence existant
                                        $attendance = null;
                                        foreach ($attendances as $att) {
                                            if ($att['student_id'] == $student['id']) {
                                                $attendance = $att;
                                                break;
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $student['prenom'] . ' ' . $student['nom']; ?></strong>
                                            </td>
                                            <td><?php echo $student['email']; ?></td>
                                            <td>
                                                <select name="attendance[<?php echo $student['id']; ?>]" class="form-select">
                                                    <option value="absent" <?php echo (!$attendance || $attendance['statut'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                                                    <option value="present" <?php echo ($attendance && $attendance['statut'] === 'present') ? 'selected' : ''; ?>>Présent</option>
                                                    <option value="retard" <?php echo ($attendance && $attendance['statut'] === 'retard') ? 'selected' : ''; ?>>Retard</option>
                                                    <option value="excusé" <?php echo ($attendance && $attendance['statut'] === 'excusé') ? 'selected' : ''; ?>>Excusé</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="remarque[<?php echo $student['id']; ?>]" 
                                                       class="form-control" placeholder="Remarque..."
                                                       value="<?php echo $attendance['remarque'] ?? ''; ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="form-group text-center mt-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                💾 Enregistrer les Présences
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($session_id && empty($students)): ?>
            <div class="alert alert-warning">
                Aucun étudiant n'est inscrit à ce cours.
            </div>
        <?php endif; ?>
        
        <!-- Aperçu des présences existantes -->
        <?php if ($session_id && !empty($attendances)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Résumé des Présences</h3>
                </div>
                <div class="card-body">
                    <?php
                    $stats = [
                        'present' => 0,
                        'absent' => 0,
                        'retard' => 0,
                        'excusé' => 0
                    ];
                    
                    foreach ($attendances as $att) {
                        $stats[$att['statut']]++;
                    }
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--success-color);"><?php echo $stats['present']; ?></h3>
                                    <p>Présents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--danger-color);"><?php echo $stats['absent']; ?></h3>
                                    <p>Absents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--warning-color);"><?php echo $stats['retard']; ?></h3>
                                    <p>Retards</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--info-color);"><?php echo $stats['excusé']; ?></h3>
                                    <p>Excusés</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <strong>Taux de présence: 
                            <?php echo calculatePercentage($stats['present'] + $stats['retard'] + $stats['excusé'], count($attendances)); ?>%
                        </strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>