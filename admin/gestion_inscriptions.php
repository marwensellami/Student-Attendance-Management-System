<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un administrateur
requireAuth(ROLE_ADMIN);

$action = $_GET['action'] ?? 'list';
$course_id = $_GET['course_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$error = '';
$success = '';

try {
    global $pdo;
    
    // INSCRIPTION d'un étudiant
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
        $student_id = $_POST['student_id'] ?? '';
        $course_id = $_POST['course_id'] ?? '';
        
        if (empty($student_id) || empty($course_id)) {
            $error = "Veuillez sélectionner un étudiant et un cours.";
        } else {
            // Vérifier si l'inscription existe déjà
            $sql = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$student_id, $course_id]);
            
            if ($stmt->fetch()) {
                $error = "Cet étudiant est déjà inscrit à ce cours.";
            } else {
                $sql = "INSERT INTO enrollments (student_id, course_id, statut) VALUES (?, ?, 'actif')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$student_id, $course_id]);
                
                logAction($_SESSION['user_id'], 'enrollment_created', "Inscription de l'étudiant $student_id au cours $course_id");
                $success = "Étudiant inscrit au cours avec succès !";
            }
        }
    }
    
    // DÉSINSCRIPTION d'un étudiant
    if ($action === 'remove' && isset($_GET['student_id']) && isset($_GET['course_id'])) {
        $student_id = $_GET['student_id'];
        $course_id = $_GET['course_id'];
        
        $sql = "DELETE FROM enrollments WHERE student_id = ? AND course_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id, $course_id]);
        
        logAction($_SESSION['user_id'], 'enrollment_removed', "Désinscription de l'étudiant $student_id du cours $course_id");
        $success = "Étudiant désinscrit du cours avec succès !";
    }
    
    // Récupérer les données
    $courses = [];
    $students = [];
    $enrollments = [];
    
    // Liste de tous les cours
    $sql = "SELECT c.*, u.nom as enseignant_nom, u.prenom as enseignant_prenom 
            FROM courses c 
            JOIN users u ON c.enseignant_id = u.id 
            ORDER BY c.titre";
    $stmt = $pdo->query($sql);
    $courses = $stmt->fetchAll();
    
    // Liste de tous les étudiants
    $sql = "SELECT id, nom, prenom, email, username 
            FROM users 
            WHERE role = 'etudiant' AND is_active = 1 
            ORDER BY nom, prenom";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll();
    
    // Inscriptions existantes
    $sql = "SELECT e.*, u.nom as student_nom, u.prenom as student_prenom, 
                   c.code as course_code, c.titre as course_titre
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            JOIN courses c ON e.course_id = c.id
            ORDER BY c.code, u.nom, u.prenom";
    $stmt = $pdo->query($sql);
    $enrollments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur gestion inscriptions: " . $e->getMessage());
    $error = "Une erreur est survenue lors de l'opération.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Inscriptions - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Gestion des Inscriptions</h1>
            <p>Inscrivez les étudiants aux cours</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Formulaire d'inscription -->
        <div class="card">
            <div class="card-header">
                <h3>Inscrire un Étudiant à un Cours</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="?action=add">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Sélectionner un étudiant *</label>
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
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Sélectionner un cours *</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">Choisir un cours</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo $course['code'] . ' - ' . $course['titre'] . ' (' . $course['enseignant_prenom'] . ' ' . $course['enseignant_nom'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            📝 Inscrire l'étudiant
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Liste des inscriptions existantes -->
        <div class="card">
            <div class="card-header">
                <h3>Inscriptions Existantes</h3>
                <span class="badge badge-info"><?php echo count($enrollments); ?> inscriptions</span>
            </div>
            <div class="card-body">
                <?php if (!empty($enrollments)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Cours</th>
                                    <th>Date d'inscription</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $enrollment['student_prenom'] . ' ' . $enrollment['student_nom']; ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo $enrollment['course_code']; ?></strong><br>
                                            <small><?php echo $enrollment['course_titre']; ?></small>
                                        </td>
                                        <td><?php echo formatDate($enrollment['date_inscription']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $enrollment['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($enrollment['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=remove&student_id=<?php echo $enrollment['student_id']; ?>&course_id=<?php echo $enrollment['course_id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Êtes-vous sûr de vouloir désinscrire cet étudiant de ce cours ?')">
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
                        <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                        <h3>Aucune inscription</h3>
                        <p class="text-muted">Aucun étudiant n'est inscrit à un cours pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>