<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un étudiant
requireAuth(ROLE_ETUDIANT);

$etudiant_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    global $pdo;
    
    // Récupérer les cours de l'étudiant
    $sql = "SELECT c.*, u.nom as enseignant_nom, u.prenom as enseignant_prenom,
            e.date_inscription,
            (SELECT COUNT(*) FROM sessions s WHERE s.course_id = c.id) as nb_seances,
            (SELECT COUNT(*) FROM attendance a 
             JOIN sessions s2 ON a.session_id = s2.id 
             WHERE s2.course_id = c.id AND a.student_id = ? AND a.statut IN ('present', 'retard', 'excusé')) as nb_presences
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            JOIN users u ON c.enseignant_id = u.id
            WHERE e.student_id = ? AND e.statut = 'actif'
            ORDER BY e.date_inscription DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id, $etudiant_id]);
    $courses = $stmt->fetchAll();
    
    // Calculer les taux de présence pour chaque cours
    foreach ($courses as &$course) {
        $course['taux_presence'] = $course['nb_seances'] > 0 
            ? calculatePercentage($course['nb_presences'], $course['nb_seances'])
            : 0;
    }
    
    // Récupérer les séances à venir
    $sql = "SELECT s.*, c.code, c.titre as course_titre,
            u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM sessions s
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON c.enseignant_id = u.id
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = ? 
            AND s.date_session >= NOW()
            ORDER BY s.date_session ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $upcoming_sessions = $stmt->fetchAll();
    
    // Récupérer les séances passées récentes
    $sql = "SELECT s.*, c.code, c.titre as course_titre,
            u.nom as enseignant_nom, u.prenom as enseignant_prenom,
            a.statut as presence_statut
            FROM sessions s
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON c.enseignant_id = u.id
            JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN attendance a ON s.id = a.session_id AND a.student_id = ?
            WHERE e.student_id = ? 
            AND s.date_session < NOW()
            ORDER BY s.date_session DESC
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id, $etudiant_id]);
    $recent_sessions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur cours étudiant: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Cours - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Mes Cours</h1>
            <p>Consultez vos cours et le planning des séances</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Liste des cours -->
        <div class="card">
            <div class="card-header">
                <h3>Mes Cours Inscrits </h3>
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
                                    <th>Séances</th>
                                    <th>Présences</th>
                                
                                    <th>Date Inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><strong><?php echo $course['code']; ?></strong></td>
                                        <td>
                                            <div class="course-title"><?php echo $course['titre']; ?></div>
                                            <?php if ($course['description']): ?>
                                                <small class="text-muted"><?php echo substr($course['description'], 0, 100); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $course['enseignant_prenom'] . ' ' . $course['enseignant_nom']; ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $course['nb_seances']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $course['nb_presences'] > 0 ? 'success' : 'secondary'; ?>">
                                                <?php echo $course['nb_presences']; ?>
                                            </span>
                                        </td>
                                        
                                        <td><?php echo formatDate($course['date_inscription'], 'd/m/Y'); ?></td>
                                        <td>
                                            <a href="presence.php?course_id=<?php echo $course['id']; ?>" 
                                               class="btn btn-sm btn-outline">
                                                📊 Présences
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                        <h3>Vous n'êtes inscrit à aucun cours</h3>
                        <p class="text-muted">Contactez votre administrateur pour être inscrit à des cours.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <!-- Séances à venir -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Séances à Venir</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($upcoming_sessions)): ?>
                            <div class="sessions-timeline">
                                <?php foreach ($upcoming_sessions as $session): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?php echo formatDate($session['date_session'], 'd/m H:i'); ?>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="session-title"><?php echo $session['titre']; ?></div>
                                            <div class="session-info">
                                                <span class="course-badge"><?php echo $session['code']; ?></span>
                                                <span class="teacher-name"><?php echo $session['enseignant_prenom'] . ' ' . $session['enseignant_nom']; ?></span>
                                            </div>
                                            <?php if ($session['description']): ?>
                                                <div class="session-description">
                                                    <?php echo substr($session['description'], 0, 100); ?>...
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-status">
                                            <?php if (isToday($session['date_session'])): ?>
                                                <span class="badge badge-success">Aujourd'hui</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">À venir</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                                <h5>Aucune séance à venir</h5>
                                <p class="text-muted">Aucune séance n'est planifiée pour le moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Séances récentes -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Séances Récentes</h3>
                        <a href="presence.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_sessions)): ?>
                            <div class="recent-sessions">
                                <?php foreach ($recent_sessions as $session): ?>
                                    <div class="recent-session-item">
                                        <div class="session-main">
                                            <div class="session-title"><?php echo $session['titre']; ?></div>
                                            <div class="session-meta">
                                                <span class="course-code"><?php echo $session['code']; ?></span>
                                                <span class="session-date"><?php echo formatDate($session['date_session'], 'd/m'); ?></span>
                                            </div>
                                        </div>
                                        <div class="session-status">
                                            <?php if ($session['presence_statut']): ?>
                                                <?php echo getAttendanceStatusBadge($session['presence_statut']); ?>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Non marqué</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">⏰</div>
                                <h5>Aucune séance récente</h5>
                                <p class="text-muted">Aucune séance n'a eu lieu récemment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <style>
        .course-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .sessions-timeline {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .timeline-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            background: var(--gray-light);
            border-radius: var(--border-radius);
            gap: 1rem;
        }
        
        .timeline-date {
            min-width: 100px;
            text-align: center;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .session-title {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .session-info {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .course-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .session-description {
            font-size: 0.875rem;
            color: var(--dark-color);
        }
        
        .recent-sessions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .recent-session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .session-main {
            flex: 1;
        }
        
        .session-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .timeline-item {
                flex-direction: column;
                text-align: center;
            }
            
            .timeline-date {
                min-width: auto;
            }
        }
    </style>
</body>
</html>