<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un étudiant
requireAuth(ROLE_ETUDIANT);

$etudiant_id = $_SESSION['user_id'];
$error = '';

// Récupérer les statistiques de l'étudiant
$stats = [];
try {
    global $pdo;
    
    // Nombre de cours de l'étudiant
    $sql = "SELECT COUNT(*) as total_courses 
            FROM enrollments 
            WHERE student_id = ? AND statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $stats['total_courses'] = $stmt->fetchColumn();
    
    // Nombre total de séances dans les cours de l'étudiant
    $sql = "SELECT COUNT(*) as total_sessions
            FROM sessions s
            JOIN enrollments e ON s.course_id = e.course_id
            WHERE e.student_id = ? AND e.statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $stats['total_sessions'] = $stmt->fetchColumn();
    
    // Nombre de présences de l'étudiant
    $sql = "SELECT COUNT(*) as total_presences
            FROM attendance 
            WHERE student_id = ? AND statut IN ('present', 'retard', 'excusé')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $stats['total_presences'] = $stmt->fetchColumn();
    
    // Taux de présence global
    $sql = "SELECT 
            COUNT(*) as total_seances,
            SUM(CASE WHEN statut IN ('present', 'retard', 'excusé') THEN 1 ELSE 0 END) as presences
            FROM attendance 
            WHERE student_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $attendance_data = $stmt->fetch();
    $stats['attendance_rate'] = calculatePercentage($attendance_data['presences'], $attendance_data['total_seances']);
    
    // Cours récents de l'étudiant
    $sql = "SELECT c.*, u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            JOIN users u ON c.enseignant_id = u.id
            WHERE e.student_id = ? AND e.statut = 'actif'
            ORDER BY e.date_inscription DESC 
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $stats['recent_courses'] = $stmt->fetchAll();
    
    // Séances à venir (prochaines 7 jours)
    $sql = "SELECT s.*, c.code, c.titre as course_titre,
            u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM sessions s
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON c.enseignant_id = u.id
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = ? 
            AND s.date_session BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            ORDER BY s.date_session ASC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $stats['upcoming_sessions'] = $stmt->fetchAll();
    
    // Dernières présences enregistrées
    $sql = "SELECT a.*, s.titre as session_titre, c.code as course_code,
            c.titre as course_titre, u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM attendance a
            JOIN sessions s ON a.session_id = s.id
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON c.enseignant_id = u.id
            WHERE a.student_id = ?
            ORDER BY a.date_marquage DESC
            LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $stats['recent_attendance'] = $stmt->fetchAll();
    
    // Statistiques par cours
    $sql = "SELECT c.id, c.code, c.titre,
            COUNT(s.id) as total_seances,
            COUNT(a.id) as presences,
            ROUND((COUNT(a.id) * 100.0 / COUNT(s.id)), 2) as taux_presence
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN sessions s ON c.id = s.course_id
            LEFT JOIN attendance a ON s.id = a.session_id AND a.student_id = ? AND a.statut IN ('present', 'retard', 'excusé')
            WHERE e.student_id = ? AND e.statut = 'actif'
            GROUP BY c.id
            ORDER BY taux_presence DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id, $etudiant_id]);
    $stats['course_stats'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur lors du chargement du dashboard étudiant: " . $e->getMessage());
    $error = "Erreur lors du chargement des données.";
}

// Journaliser l'accès
logAction($_SESSION['user_id'], 'student_dashboard_access', 'Accès au tableau de bord étudiant');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Étudiant - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Tableau de Bord Étudiant</h1>
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
                        📅
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_sessions']; ?></h3>
                        <p>Séances</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e74c3c;">
                        ✅
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_presences']; ?></h3>
                        <p>Présences</p>
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
                        <h3>Mes Cours</h3>
                        <a href="cours.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['recent_courses'])): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Titre</th>
                                            <th>Enseignant</th>
                                         
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        foreach ($stats['recent_courses'] as $course): 
                                            // Trouver les statistiques du cours
                                            $course_stat = null;
                                            foreach ($stats['course_stats'] as $stat) {
                                                if ($stat['id'] == $course['id']) {
                                                    $course_stat = $stat;
                                                    break;
                                                }
                                            }
                                            $taux = $course_stat ? $course_stat['taux_presence'] : 0;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $course['code']; ?></strong></td>
                                                <td><?php echo $course['titre']; ?></td>
                                                <td><?php echo $course['enseignant_prenom'] . ' ' . $course['enseignant_nom']; ?></td>
                                                
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                                <h3>Vous n'êtes inscrit à aucun cours</h3>
                                <p class="text-muted">Contactez votre administrateur pour être inscrit à des cours.</p>
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
                                            <div class="session-teacher"><?php echo $session['enseignant_prenom'] . ' ' . $session['enseignant_nom']; ?></div>
                                        </div>
                                        <div class="session-status">
                                            <?php if (isToday($session['date_session'])): ?>
                                                <span class="badge badge-success">Aujourd'hui</span>
                                            <?php elseif (isFutureDate($session['date_session'])): ?>
                                                <span class="badge badge-info">À venir</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucune séance planifiée cette semaine.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistiques de présence par cours -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Mes Statistiques par Cours</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['course_stats'])): ?>
                            <div class="course-stats">
                                <?php foreach ($stats['course_stats'] as $course_stat): ?>
                                    <div class="course-stat-item">
                                        <div class="course-info">
                                            <div class="course-code"><?php echo $course_stat['code']; ?></div>
                                            <div class="course-title"><?php echo $course_stat['titre']; ?></div>
                                        </div>
                                        <div class="course-stats">
                                            <div class="attendance-rate">
                                                <?php echo $course_stat['presences']; ?>/<?php echo $course_stat['total_seances']; ?>
                                            </div>
                                            <div class="progress" style="height: 8px; width: 100px;">
                                                <div class="progress-bar 
                                                    <?php 
                                                    if ($course_stat['taux_presence'] >= 80) echo 'bg-success';
                                                    elseif ($course_stat['taux_presence'] >= 60) echo 'bg-warning';
                                                    else echo 'bg-danger';
                                                    ?>"
                                                     style="width: <?php echo $course_stat['taux_presence']; ?>%;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucune statistique disponible.</p>
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
                        <h3>Mes Dernières Présences</h3>
                        <a href="presence.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['recent_attendance'])): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Cours</th>
                                            <th>Séance</th>
                                            <th>Statut</th>
                                            <th>Enseignant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_attendance'] as $attendance): ?>
                                            <tr>
                                                <td><?php echo formatDate($attendance['date_marquage']); ?></td>
                                                <td><?php echo $attendance['course_code']; ?></td>
                                                <td><?php echo $attendance['session_titre']; ?></td>
                                                <td><?php echo getAttendanceStatusBadge($attendance['statut']); ?></td>
                                                <td><?php echo $attendance['enseignant_prenom'] . ' ' . $attendance['enseignant_nom']; ?></td>
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
                            <a href="cours.php" class="quick-action">
                                <div class="quick-icon">📚</div>
                                <span>Mes Cours</span>
                            </a>
                            <a href="presence.php" class="quick-action">
                                <div class="quick-icon">✅</div>
                                <span>Mes Présences</span>
                            </a>
                            <a href="profile.php" class="quick-action">
                                <div class="quick-icon">👤</div>
                                <span>Mon Profil</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Prochaines échéances -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Prochaines Séances</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Récupérer les 3 prochaines séances
                        $sql = "SELECT s.*, c.code, c.titre as course_titre
                                FROM sessions s
                                JOIN courses c ON s.course_id = c.id
                                JOIN enrollments e ON c.id = e.course_id
                                WHERE e.student_id = ? 
                                AND s.date_session >= NOW()
                                ORDER BY s.date_session ASC
                                LIMIT 3";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$etudiant_id]);
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
                                                <?php echo $deadline['code']; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucune séance à venir.</p>
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
        
        .session-course, .session-teacher {
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
        
        .course-stats {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .course-stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-color);
        }
        
        .course-info {
            flex: 1;
        }
        
        .course-code {
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .course-title {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .course-stats {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }
        
        .attendance-rate {
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</body>
</html>