<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un enseignant
requireAuth(ROLE_ENSEIGNANT);

$enseignant_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? '';
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

try {
    global $pdo;
    
    // Récupérer les cours de l'enseignant
    $sql = "SELECT c.* FROM courses c WHERE c.enseignant_id = ? ORDER BY c.titre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $courses = $stmt->fetchAll();
    
    // Gérer les inscriptions si un cours est sélectionné
    $students = [];
    $enrolled_students = [];
    
    if ($course_id) {
        // Vérifier que le cours appartient à l'enseignant
        $sql = "SELECT id FROM courses WHERE id = ? AND enseignant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id, $enseignant_id]);
        
        if (!$stmt->fetch()) {
            $error = "Vous n'êtes pas autorisé à gérer ce cours.";
            $course_id = '';
        } else {
            // Étudiants inscrits au cours
            $sql = "SELECT u.id, u.nom, u.prenom, u.email, e.date_inscription, e.statut
                    FROM enrollments e
                    JOIN users u ON e.student_id = u.id
                    WHERE e.course_id = ?
                    ORDER BY u.nom, u.prenom";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id]);
            $enrolled_students = $stmt->fetchAll();
            
            // Tous les étudiants (pour l'ajout)
            $sql = "SELECT u.id, u.nom, u.prenom, u.email
                    FROM users u
                    WHERE u.role = 'etudiant' AND u.is_active = 1
                    AND u.id NOT IN (SELECT student_id FROM enrollments WHERE course_id = ?)
                    ORDER BY u.nom, u.prenom";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id]);
            $students = $stmt->fetchAll();
        }
    }
    
    // AJOUT d'un étudiant
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
        $student_id = $_POST['student_id'] ?? '';
        $course_id = $_POST['course_id'] ?? '';
        
        if (empty($student_id) || empty($course_id)) {
            $error = "Veuillez sélectionner un étudiant.";
        } else {
            // Vérifier les permissions
            $sql = "SELECT id FROM courses WHERE id = ? AND enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $enseignant_id]);
            
            if (!$stmt->fetch()) {
                $error = "Vous n'êtes pas autorisé à gérer ce cours.";
            } else {
                $sql = "INSERT INTO enrollments (student_id, course_id, statut) VALUES (?, ?, 'actif')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$student_id, $course_id]);
                
                $success = "Étudiant inscrit au cours avec succès !";
                // Recharger les données
                header("Location: gestion_etudiants.php?course_id=$course_id&success=1");
                exit;
            }
        }
    }
    
    // SUPPRESSION d'une inscription
    if ($action === 'remove' && isset($_GET['student_id']) && $course_id) {
        $student_id = $_GET['student_id'];
        
        $sql = "DELETE FROM enrollments WHERE student_id = ? AND course_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $course_id]);
        
        $success = "Étudiant désinscrit du cours avec succès !";
        header("Location: gestion_etudiants.php?course_id=$course_id&success=1");
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Erreur gestion étudiants: " . $e->getMessage());
    $error = "Une erreur est survenue lors de l'opération.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Étudiants - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Gestion des Étudiants</h1>
            <p>Gérez les inscriptions à vos cours</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Sélection du cours -->
        <div class="card">
            <div class="card-header">
                <h3>Sélection du Cours</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="">
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
                </form>
            </div>
        </div>
        
        <?php if ($course_id): ?>
            <!-- Ajouter un étudiant -->
            <div class="card">
                <div class="card-header">
                    <h3>Ajouter un Étudiant au Cours</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($students)): ?>
                        <form method="POST" action="?action=add&course_id=<?php echo $course_id; ?>">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            
                            <div class="row">
                                <div class="col-8">
                                    <div class="form-group">
                                        <label class="form-label">Sélectionner un étudiant</label>
                                        <select name="student_id" class="form-select" required>
                                            <option value="">Choisir un étudiant</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo $student['prenom'] . ' ' . $student['nom'] . ' (' . $student['email'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100">
                                            ➕ Ajouter l'étudiant
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-center">Tous les étudiants sont déjà inscrits à ce cours.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Étudiants inscrits -->
            <div class="card">
                <div class="card-header">
                    <h3>Étudiants Inscrits</h3>
                    <span class="badge badge-info"><?php echo count($enrolled_students); ?> étudiants</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($enrolled_students)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Email</th>
                                        <th>Date d'inscription</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrolled_students as $student): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $student['prenom'] . ' ' . $student['nom']; ?></strong>
                                            </td>
                                            <td><?php echo $student['email']; ?></td>
                                            <td><?php echo formatDate($student['date_inscription']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $student['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($student['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?action=remove&course_id=<?php echo $course_id; ?>&student_id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir désinscrire cet étudiant ?')">
                                                    🗑️ Désinscrire
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">👥</div>
                            <h3>Aucun étudiant inscrit</h3>
                            <p class="text-muted">Aucun étudiant n'est inscrit à ce cours pour le moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>