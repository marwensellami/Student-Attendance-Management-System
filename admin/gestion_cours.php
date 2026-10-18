<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un administrateur
requireAuth(ROLE_ADMIN);

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

try {
    global $pdo;
    
    // AJOUT de cours
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
        $code = sanitize($_POST['code'] ?? '');
        $titre = sanitize($_POST['titre'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $enseignant_id = $_POST['enseignant_id'] ?? '';
        $date_debut = sanitize($_POST['date_debut'] ?? '');
        $date_fin = sanitize($_POST['date_fin'] ?? '');
        
        if (empty($code) || empty($titre) || empty($enseignant_id)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } else {
            // Vérifier si le code existe déjà
            $sql = "SELECT id FROM courses WHERE code = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code]);
            
            if ($stmt->fetch()) {
                $error = "Un cours avec ce code existe déjà.";
            } else {
                $sql = "INSERT INTO courses (code, titre, description, enseignant_id, date_debut, date_fin) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code, $titre, $description, $enseignant_id, $date_debut, $date_fin]);
                
                $course_id = $pdo->lastInsertId();
                logAction($_SESSION['user_id'], 'course_created', "Création du cours ID: $course_id");
                
                $success = "Cours créé avec succès !";
                $action = 'list';
            }
        }
    }
    
    // MODIFICATION de cours
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
        $course_id = $_POST['course_id'] ?? '';
        $code = sanitize($_POST['code'] ?? '');
        $titre = sanitize($_POST['titre'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $enseignant_id = $_POST['enseignant_id'] ?? '';
        $date_debut = sanitize($_POST['date_debut'] ?? '');
        $date_fin = sanitize($_POST['date_fin'] ?? '');
        
        if (empty($course_id)) {
            $error = "ID cours manquant.";
        } else {
            // Vérifier les conflits de code
            $sql = "SELECT id FROM courses WHERE code = ? AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code, $course_id]);
            
            if ($stmt->fetch()) {
                $error = "Un autre cours utilise déjà ce code.";
            } else {
                $sql = "UPDATE courses SET code = ?, titre = ?, description = ?, enseignant_id = ?, 
                        date_debut = ?, date_fin = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code, $titre, $description, $enseignant_id, $date_debut, $date_fin, $course_id]);
                
                logAction($_SESSION['user_id'], 'course_updated', "Modification du cours ID: $course_id");
                $success = "Cours modifié avec succès !";
                $action = 'list';
            }
        }
    }
    
    // SUPPRESSION de cours
    if ($action === 'delete' && isset($_GET['id'])) {
        $course_id = $_GET['id'];
        
        $sql = "DELETE FROM courses WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id]);
        
        logAction($_SESSION['user_id'], 'course_deleted', "Suppression du cours ID: $course_id");
        $success = "Cours supprimé avec succès !";
    }
    
} catch (PDOException $e) {
    error_log("Erreur gestion cours: " . $e->getMessage());
    $error = "Une erreur est survenue lors de l'opération.";
}

// Récupérer les données
$courses = [];
$course = null;
$enseignants = [];

// Récupérer la liste des enseignants
$sql = "SELECT id, nom, prenom FROM users WHERE role = 'enseignant' AND is_active = 1 ORDER BY nom, prenom";
$stmt = $pdo->query($sql);
$enseignants = $stmt->fetchAll();

if ($action === 'list') {
    $sql = "SELECT c.*, u.nom as enseignant_nom, u.prenom as enseignant_prenom,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as nb_etudiants,
            (SELECT COUNT(*) FROM sessions WHERE course_id = c.id) as nb_seances
            FROM courses c 
            JOIN users u ON c.enseignant_id = u.id 
            ORDER BY c.created_at DESC";
    $stmt = $pdo->query($sql);
    $courses = $stmt->fetchAll();
} elseif (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
    $sql = "SELECT c.*, u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM courses c 
            JOIN users u ON c.enseignant_id = u.id 
            WHERE c.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_GET['id']]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $error = "Cours non trouvé.";
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Cours - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>
                <?php 
                switch ($action) {
                    case 'add': echo 'Ajouter un Cours'; break;
                    case 'edit': echo 'Modifier le Cours'; break;
                    case 'view': echo 'Détails du Cours'; break;
                    default: echo 'Gestion des Cours';
                }
                ?>
            </h1>
            <div class="header-actions">
                <?php if ($action !== 'list'): ?>
                    <a href="gestion_cours.php" class="btn btn-outline">← Retour à la liste</a>
                <?php endif; ?>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">📚 Ajouter Cours</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- LISTE des cours -->
        <?php if ($action === 'list'): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Liste des Cours (<?php echo count($courses); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($courses)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Titre</th>
                                        <th>Enseignant</th>
                                        <th>Étudiants</th>
                                        <th>Séances</th>
                                        <th>Date Début</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $c): ?>
                                        <tr>
                                            <td><strong><?php echo $c['code']; ?></strong></td>
                                            <td><?php echo $c['titre']; ?></td>
                                            <td><?php echo $c['enseignant_prenom'] . ' ' . $c['enseignant_nom']; ?></td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $c['nb_etudiants']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-secondary"><?php echo $c['nb_seances']; ?></span>
                                            </td>
                                            <td><?php echo formatDate($c['date_debut'], 'd/m/Y'); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline" title="Voir">
                                                        👁️
                                                    </a>
                                                    <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline" title="Modifier">
                                                        ✏️
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $c['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       title="Supprimer"
                                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce cours ?')">
                                                        🗑️
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">Aucun cours trouvé.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- FORMULAIRE Ajout/Modification -->
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $course['id'] : ''; ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Code du cours *</label>
                                    <input type="text" name="code" class="form-control" 
                                           value="<?php echo $course['code'] ?? ''; ?>" required
                                           placeholder="ex: DEV101">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Enseignant *</label>
                                    <select name="enseignant_id" class="form-select" required>
                                        <option value="">Sélectionner un enseignant</option>
                                        <?php foreach ($enseignants as $enseignant): ?>
                                            <option value="<?php echo $enseignant['id']; ?>"
                                                <?php echo ($course['enseignant_id'] ?? '') == $enseignant['id'] ? 'selected' : ''; ?>>
                                                <?php echo $enseignant['prenom'] . ' ' . $enseignant['nom']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Titre du cours *</label>
                            <input type="text" name="titre" class="form-control" 
                                   value="<?php echo $course['titre'] ?? ''; ?>" required
                                   placeholder="ex: Développement Web Dynamique">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="Description du cours..."><?php echo $course['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Date de début</label>
                                    <input type="date" name="date_debut" class="form-control" 
                                           value="<?php echo $course['date_debut'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Date de fin</label>
                                    <input type="date" name="date_fin" class="form-control" 
                                           value="<?php echo $course['date_fin'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'Créer le cours' : 'Modifier le cours'; ?>
                            </button>
                            <a href="gestion_cours.php" class="btn btn-outline">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- VUE détaillée -->
        <?php if ($action === 'view' && $course): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Détails du Cours</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h4>Informations du Cours</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Code</th>
                                    <td><strong><?php echo $course['code']; ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Titre</th>
                                    <td><?php echo $course['titre']; ?></td>
                                </tr>
                                <tr>
                                    <th>Description</th>
                                    <td><?php echo nl2br($course['description'] ?: 'Aucune description'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-6">
                            <h4>Informations Complémentaires</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Enseignant</th>
                                    <td><?php echo $course['enseignant_prenom'] . ' ' . $course['enseignant_nom']; ?></td>
                                </tr>
                                <tr>
                                    <th>Date de début</th>
                                    <td><?php echo $course['date_debut'] ? formatDate($course['date_debut'], 'd/m/Y') : 'Non définie'; ?></td>
                                </tr>
                                <tr>
                                    <th>Date de fin</th>
                                    <td><?php echo $course['date_fin'] ? formatDate($course['date_fin'], 'd/m/Y') : 'Non définie'; ?></td>
                                </tr>
                                <tr>
                                    <th>Date de création</th>
                                    <td><?php echo formatDate($course['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Statistiques du cours -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h4>Statistiques du Cours</h4>
                            <?php
                            // Récupérer les statistiques du cours
                            $sql = "SELECT 
                                    (SELECT COUNT(*) FROM enrollments WHERE course_id = ?) as nb_etudiants,
                                    (SELECT COUNT(*) FROM sessions WHERE course_id = ?) as nb_seances,
                                    (SELECT COUNT(*) FROM attendance a 
                                     JOIN sessions s ON a.session_id = s.id 
                                     WHERE s.course_id = ?) as nb_presences";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$course['id'], $course['id'], $course['id']]);
                            $stats = $stmt->fetch();
                            ?>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="stat-card mini">
                                        <div class="stat-info">
                                            <h3><?php echo $stats['nb_etudiants']; ?></h3>
                                            <p>Étudiants inscrits</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-card mini">
                                        <div class="stat-info">
                                            <h3><?php echo $stats['nb_seances']; ?></h3>
                                            <p>Séances planifiées</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-card mini">
                                        <div class="stat-info">
                                            <h3><?php echo $stats['nb_presences']; ?></h3>
                                            <p>Présences enregistrées</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="?action=edit&id=<?php echo $course['id']; ?>" class="btn btn-primary">Modifier</a>
                        <a href="gestion_cours.php" class="btn btn-outline">Retour à la liste</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>