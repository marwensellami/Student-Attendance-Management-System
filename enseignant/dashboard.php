<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un enseignant
requireAuth(ROLE_ENSEIGNANT);

$enseignant_id = $_SESSION['user_id'];
$error = '';

// Récupérer les statistiques de l'enseignant
$stats = [];
try {
    global $pdo;
    
    // Nombre de cours de l'enseignant
    $sql = "SELECT COUNT(*) as total_courses FROM courses WHERE enseignant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $stats['total_courses'] = $stmt->fetchColumn();
    
    // Nombre total d'étudiants dans les cours de l'enseignant
    $sql = "SELECT COUNT(DISTINCT e.student_id) as total_students
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE c.enseignant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $stats['total_students'] = $stmt->fetchColumn();
    
    // Nombre de séances planifiées
    $sql = "SELECT COUNT(*) as total_sessions 
            FROM sessions s
            JOIN courses c ON s.course_id = c.id
            WHERE c.enseignant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $stats['total_sessions'] = $stmt->fetchColumn();
    
    // Taux de présence global pour les cours de l'enseignant
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN a.statut = 'present' THEN 1 ELSE 0 END) as presents
            FROM attendance a
            JOIN sessions s ON a.session_id = s.id
            JOIN courses c ON s.course_id = c.id
            WHERE c.enseignant_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $attendance_data = $stmt->fetch();
    $stats['attendance_rate'] = calculatePercentage($attendance_data['presents'], $attendance_data['total']);
    
    // Cours récents de l'enseignant
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as nb_etudiants,
            (SELECT COUNT(*) FROM sessions WHERE course_id = c.id) as nb_seances
            FROM courses c 
            WHERE c.enseignant_id = ?
            ORDER BY c.created_at DESC 
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $stats['recent_courses'] = $stmt->fetchAll();
    
    // Séances à venir (prochaines 7 jours)
    $sql = "SELECT s.*, c.code, c.titre as course_titre
            FROM sessions s
            JOIN courses c ON s.course_id = c.id
            WHERE c.enseignant_id = ? 
            AND s.date_session BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            ORDER BY s.date_session ASC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $stats['upcoming_sessions'] = $stmt->fetchAll();
    
    // Dernières présences enregistrées
    $sql = "SELECT a.*, s.titre as session_titre, c.code as course_code,
            u.nom as student_nom, u.prenom as student_prenom
            FROM attendance a
            JOIN sessions s ON a.session_id = s.id
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON a.student_id = u.id
            WHERE c.enseignant_id = ?
            ORDER BY a.date_marquage DESC
            LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $stats['recent_attendance'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur lors du chargement du dashboard enseignant: " . $e->getMessage());
    $error = "Erreur lors du chargement des données.";
}

// Journaliser l'accès
logAction($_SESSION['user_id'], 'teacher_dashboard_access', 'Accès au tableau de bord enseignant');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Enseignant - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Tableau de Bord Enseignant</h1>
            <p>Bienvenue, <?php echo $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']; ?></p>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques -->
        <div class="row">
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #3498db;">
                        📚
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_courses']; ?></h3>
                        <p>Mes Cours</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #27ae60;">
                        👥
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_students']; ?></h3>
                        <p>Étudiants</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e74c3c;">
                        📅
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_sessions']; ?></h3>
                        <p>Séances Planifiées</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        📊
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['attendance_rate']; ?>%</h3>
                        <p>Taux de Présence</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Mes cours récents -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Mes Cours Récents</h3>
                        <a href="gestion_cours.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['recent_courses'])): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Titre</th>
                                            <th>Étudiants</th>
                                            <th>Séances</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_courses'] as $course): ?>
                                            <tr>
                                                <td><strong><?php echo $course['code']; ?></strong></td>
                                                <td><?php echo $course['titre']; ?></td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $course['nb_etudiants']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary"><?php echo $course['nb_seances']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucun cours créé.</p>
                            <div class="text-center">
                                <a href="gestion_cours.php?action=add" class="btn btn-primary">
                                    ➕ Créer mon premier cours
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Séances à venir -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Séances à Venir (7 jours)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['upcoming_sessions'])): ?>
                            <div class="sessions-list">
                                <?php foreach ($stats['upcoming_sessions'] as $session): ?>
                                    <div class="session-item">
                                        <div class="session-date">
                                            <strong><?php echo formatDate($session['date_session'], 'd/m H:i'); ?></strong>
                                        </div>
                                        <div class="session-info">
                                            <div class="session-title"><?php echo $session['titre']; ?></div>
                                            <div class="session-course"><?php echo $session['code']; ?></div>
                                        </div>
                                        <div class="session-actions">
                                            <a href="suivi_etudiants.php?session_id=<?php echo $session['id']; ?>" 
                                               class="btn btn-sm btn-outline" title="Marquer les présences">
                                                ✅
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucune séance planifiée cette semaine.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Dernières présences -->
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Dernières Présences Enregistrées</h3>
                        <a href="suivi_etudiants.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['recent_attendance'])): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Étudiant</th>
                                            <th>Cours</th>
                                            <th>Séance</th>
                                            <th>Statut</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_attendance'] as $attendance): ?>
                                            <tr>
                                                <td><?php echo $attendance['student_prenom'] . ' ' . $attendance['student_nom']; ?></td>
                                                <td><?php echo $attendance['course_code']; ?></td>
                                                <td><?php echo $attendance['session_titre']; ?></td>
                                                <td><?php echo getAttendanceStatusBadge($attendance['statut']); ?></td>
                                                <td><?php echo formatDate($attendance['date_marquage']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucune présence enregistrée récemment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Actions Rapides</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="gestion_cours.php?action=add" class="quick-action">
                                <div class="quick-icon">📚</div>
                                <span>Créer un Cours</span>
                            </a>
                            <a href="gestion_cours.php" class="quick-action">
                                <div class="quick-icon">✏️</div>
                                <span>Gérer mes Cours</span>
                            </a>
                            <a href="suivi_etudiants.php" class="quick-action">
                                <div class="quick-icon">✅</div>
                                <span>Marquer Présences</span>
                            </a>
                            <a href="rapports.php" class="quick-action">
                                <div class="quick-icon">📊</div>
                                <span>Générer Rapports</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Prochaines échéances -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Prochaines Échéances</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Récupérer les séances des prochains jours avec nombre d'étudiants
                        $sql = "SELECT s.*, c.code, c.titre as course_titre,
                                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as nb_etudiants
                                FROM sessions s
                                JOIN courses c ON s.course_id = c.id
                                WHERE c.enseignant_id = ? 
                                AND s.date_session >= CURDATE()
                                ORDER BY s.date_session ASC
                                LIMIT 3";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$enseignant_id]);
                        $deadlines = $stmt->fetchAll();
                        ?>
                        
                        <?php if (!empty($deadlines)): ?>
                            <div class="deadlines-list">
                                <?php foreach ($deadlines as $deadline): ?>
                                    <div class="deadline-item">
                                        <div class="deadline-date">
                                            <?php echo formatDate($deadline['date_session'], 'd/m'); ?>
                                        </div>
                                        <div class="deadline-info">
                                            <div class="deadline-title"><?php echo $deadline['titre']; ?></div>
                                            <div class="deadline-meta">
                                                <?php echo $deadline['code']; ?> • 
                                                <?php echo $deadline['nb_etudiants']; ?> étudiants
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucune échéance prochaine.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <style>
        .sessions-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .session-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: var(--gray-light);
            border-radius: var(--border-radius);
            gap: 1rem;
        }
        
        .session-date {
            min-width: 80px;
            text-align: center;
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-title {
            font-weight: 500;
        }
        
        .session-course {
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .deadlines-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .deadline-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            background: var(--white);
            border: 1px solid var(--light-color);
            border-radius: 4px;
            gap: 0.75rem;
        }
        
        .deadline-date {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: bold;
        }
        
        .deadline-info {
            flex: 1;
        }
        
        .deadline-title {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .deadline-meta {
            font-size: 0.75rem;
            color: var(--gray);
        }
    </style>
</body>
</html>