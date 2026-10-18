<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un enseignant
requireAuth(ROLE_ENSEIGNANT);

$enseignant_id = $_SESSION['user_id'];
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
        $date_debut = sanitize($_POST['date_debut'] ?? '');
        $date_fin = sanitize($_POST['date_fin'] ?? '');
        
        if (empty($code) || empty($titre)) {
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
                logAction($_SESSION['user_id'], 'teacher_course_created', "Création du cours ID: $course_id");
                
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
        $date_debut = sanitize($_POST['date_debut'] ?? '');
        $date_fin = sanitize($_POST['date_fin'] ?? '');
        
        if (empty($course_id)) {
            $error = "ID cours manquant.";
        } else {
            // Vérifier que le cours appartient à l'enseignant
            $sql = "SELECT id FROM courses WHERE id = ? AND enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $enseignant_id]);
            
            if (!$stmt->fetch()) {
                $error = "Vous n'êtes pas autorisé à modifier ce cours.";
            } else {
                // Vérifier les conflits de code
                $sql = "SELECT id FROM courses WHERE code = ? AND id != ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code, $course_id]);
                
                if ($stmt->fetch()) {
                    $error = "Un autre cours utilise déjà ce code.";
                } else {
                    $sql = "UPDATE courses SET code = ?, titre = ?, description = ?, 
                            date_debut = ?, date_fin = ?, updated_at = NOW() 
                            WHERE id = ? AND enseignant_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$code, $titre, $description, $date_debut, $date_fin, $course_id, $enseignant_id]);
                    
                    logAction($_SESSION['user_id'], 'teacher_course_updated', "Modification du cours ID: $course_id");
                    $success = "Cours modifié avec succès !";
                    $action = 'list';
                }
            }
        }
    }
    
    // AJOUT de séance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_session') {
        $course_id = $_POST['course_id'] ?? '';
        $titre = sanitize($_POST['titre'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $date_session = sanitize($_POST['date_session'] ?? '');
        $duree_minutes = $_POST['duree_minutes'] ?? 60;
        
        if (empty($course_id) || empty($titre) || empty($date_session)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } else {
            // Vérifier que le cours appartient à l'enseignant
            $sql = "SELECT id FROM courses WHERE id = ? AND enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $enseignant_id]);
            
            if (!$stmt->fetch()) {
                $error = "Vous n'êtes pas autorisé à ajouter une séance à ce cours.";
            } else {
                $sql = "INSERT INTO sessions (course_id, titre, description, date_session, duree_minutes) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$course_id, $titre, $description, $date_session, $duree_minutes]);
                
                $session_id = $pdo->lastInsertId();
                logAction($_SESSION['user_id'], 'session_created', "Création de la séance ID: $session_id pour le cours ID: $course_id");
                
                $success = "Séance planifiée avec succès !";
                $action = 'view';
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur gestion cours enseignant: " . $e->getMessage());
    $error = "Une erreur est survenue lors de l'opération.";
}

// Récupérer les données
$courses = [];
$course = null;
$sessions = [];

if ($action === 'list') {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as nb_etudiants,
            (SELECT COUNT(*) FROM sessions WHERE course_id = c.id) as nb_seances
            FROM courses c 
            WHERE c.enseignant_id = ?
            ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $courses = $stmt->fetchAll();
} elseif (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
    $sql = "SELECT c.* 
            FROM courses c 
            WHERE c.id = ? AND c.enseignant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_GET['id'], $enseignant_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $error = "Cours non trouvé ou accès non autorisé.";
        $action = 'list';
    } else {
        // Récupérer les séances du cours
        $sql = "SELECT s.*, 
                (SELECT COUNT(*) FROM attendance WHERE session_id = s.id) as nb_presences
                FROM sessions s 
                WHERE s.course_id = ?
                ORDER BY s.date_session DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course['id']]);
        $sessions = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de mes Cours - <?php echo APP_NAME; ?></title>
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
                    case 'add': echo 'Créer un Nouveau Cours'; break;
                    case 'edit': echo 'Modifier le Cours'; break;
                    case 'view': echo 'Détails du Cours'; break;
                    case 'add_session': echo 'Planifier une Séance'; break;
                    default: echo 'Mes Cours';
                }
                ?>
            </h1>
            <div class="header-actions">
                <?php if ($action !== 'list'): ?>
                    <a href="gestion_cours.php" class="btn btn-outline">← Retour à mes cours</a>
                <?php endif; ?>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">📚 Créer un Cours</a>
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
                    <h3>Mes Cours (<?php echo count($courses); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($courses)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Titre</th>
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
                                                    <a href="suivi_etudiants.php?course_id=<?php echo $c['id']; ?>" 
                                                       class="btn btn-sm btn-success" title="Gérer les présences">
                                                        ✅
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                            <h3>Vous n'avez pas encore de cours</h3>
                            <p class="text-muted">Créez votre premier cours pour commencer à gérer vos séances et présences.</p>
                            <a href="?action=add" class="btn btn-primary btn-lg">➕ Créer mon premier cours</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- FORMULAIRE Ajout/Modification de cours -->
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
                                    <small>Code unique identifiant le cours</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Enseignant</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']; ?>" 
                                           disabled>
                                    <small>Vous êtes l'enseignant responsable de ce cours</small>
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
                                      placeholder="Description du cours, objectifs, prérequis..."><?php echo $course['description'] ?? ''; ?></textarea>
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
        
        <!-- VUE détaillée du cours avec ses séances -->
        <?php if ($action === 'view' && $course): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Détails du Cours</h3>
                    <div class="header-actions">
                        <a href="?action=add_session&course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                            ➕ Planifier une séance
                        </a>
                        <a href="?action=edit&id=<?php echo $course['id']; ?>" class="btn btn-outline btn-sm">
                            ✏️ Modifier
                        </a>
                    </div>
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
                    
                    <!-- Liste des séances -->
                    <div class="mt-4">
                        <h4>Séances Planifiées (<?php echo count($sessions); ?>)</h4>
                        <?php if (!empty($sessions)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date et Heure</th>
                                            <th>Titre</th>
                                            <th>Durée</th>
                                            <th>Présences</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo formatDate($session['date_session']); ?></strong>
                                                    <?php if (isToday($session['date_session'])): ?>
                                                        <span class="badge badge-success">Aujourd'hui</span>
                                                    <?php elseif (isFutureDate($session['date_session'])): ?>
                                                        <span class="badge badge-info">À venir</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Passée</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $session['titre']; ?></td>
                                                <td><?php echo $session['duree_minutes']; ?> min</td>
                                                <td>
                                                    <span class="badge badge-<?php echo $session['nb_presences'] > 0 ? 'success' : 'secondary'; ?>">
                                                        <?php echo $session['nb_presences']; ?> présences
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="suivi_etudiants.php?session_id=<?php echo $session['id']; ?>" 
                                                           class="btn btn-sm btn-success" title="Marquer les présences">
                                                            ✅
                                                        </a>
                                                        <a href="rapports.php?session_id=<?php echo $session['id']; ?>" 
                                                           class="btn btn-sm btn-outline" title="Voir le rapport">
                                                            📊
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                                <h5>Aucune séance planifiée</h5>
                                <p class="text-muted">Planifiez votre première séance pour commencer à suivre les présences.</p>
                                <a href="?action=add_session&course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                    ➕ Planifier une séance
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- FORMULAIRE Ajout de séance -->
        <?php if ($action === 'add_session' && isset($_GET['course_id'])): ?>
            <?php
            // Vérifier que le cours appartient à l'enseignant
            $sql = "SELECT * FROM courses WHERE id = ? AND enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['course_id'], $enseignant_id]);
            $course_for_session = $stmt->fetch();
            
            if (!$course_for_session) {
                $error = "Cours non trouvé ou accès non autorisé.";
                $action = 'list';
            }
            ?>
            
            <?php if ($course_for_session): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Planifier une Nouvelle Séance</h3>
                        <span class="badge badge-info"><?php echo $course_for_session['code']; ?> - <?php echo $course_for_session['titre']; ?></span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=add_session&course_id=<?php echo $course_for_session['id']; ?>">
                            <input type="hidden" name="course_id" value="<?php echo $course_for_session['id']; ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Titre de la séance *</label>
                                <input type="text" name="titre" class="form-control" required
                                       placeholder="ex: Introduction à PHP, TP Base de données...">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"
                                          placeholder="Description de la séance, objectifs, activités..."></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Date et heure *</label>
                                        <input type="datetime-local" name="date_session" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Durée (minutes)</label>
                                        <input type="number" name="duree_minutes" class="form-control" 
                                               value="60" min="15" max="240" step="15">
                                        <small>Durée estimée de la séance</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    📅 Planifier la séance
                                </button>
                                <a href="?action=view&id=<?php echo $course_for_session['id']; ?>" class="btn btn-outline">
                                    Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>