<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un étudiant
requireAuth(ROLE_ETUDIANT);

$etudiant_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? '';
$error = '';

try {
    global $pdo;
    
    // Récupérer les cours de l'étudiant pour le filtre
    $sql = "SELECT c.* 
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = ? AND e.statut = 'actif'
            ORDER BY c.titre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $courses = $stmt->fetchAll();
    
    // Récupérer l'historique des présences
    $where_conditions = ["a.student_id = ?"];
    $params = [$etudiant_id];
    
    if ($course_id) {
        $where_conditions[] = "c.id = ?";
        $params[] = $course_id;
    }
    
    $sql = "SELECT a.*, s.titre as session_titre, s.date_session,
            c.code as course_code, c.titre as course_titre,
            u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM attendance a
            JOIN sessions s ON a.session_id = s.id
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON c.enseignant_id = u.id
            WHERE " . implode(" AND ", $where_conditions) . "
            ORDER BY s.date_session DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance_history = $stmt->fetchAll();
    
    // Statistiques globales
    $sql = "SELECT 
            COUNT(*) as total_seances,
            SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
            SUM(CASE WHEN statut = 'absent' THEN 1 ELSE 0 END) as absents,
            SUM(CASE WHEN statut = 'retard' THEN 1 ELSE 0 END) as retards,
            SUM(CASE WHEN statut = 'excusé' THEN 1 ELSE 0 END) as excuses
            FROM attendance 
            WHERE student_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $global_stats = $stmt->fetch();
    
    // Statistiques par cours
    $sql = "SELECT c.id, c.code, c.titre,
            COUNT(a.id) as total_seances,
            SUM(CASE WHEN a.statut = 'present' THEN 1 ELSE 0 END) as presents,
            SUM(CASE WHEN a.statut = 'absent' THEN 1 ELSE 0 END) as absents,
            SUM(CASE WHEN a.statut = 'retard' THEN 1 ELSE 0 END) as retards,
            SUM(CASE WHEN a.statut = 'excusé' THEN 1 ELSE 0 END) as excuses,
            ROUND((SUM(CASE WHEN a.statut IN ('present', 'retard', 'excusé') THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)), 2) as taux_presence
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            LEFT JOIN sessions s ON c.id = s.course_id
            LEFT JOIN attendance a ON s.id = a.session_id AND a.student_id = ?
            WHERE e.student_id = ? AND e.statut = 'actif'
            GROUP BY c.id
            ORDER BY taux_presence DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id, $etudiant_id]);
    $course_stats = $stmt->fetchAll();
    
    // Évolution mensuelle des présences
    $sql = "SELECT 
            DATE_FORMAT(s.date_session, '%Y-%m') as mois,
            COUNT(*) as total_seances,
            SUM(CASE WHEN a.statut IN ('present', 'retard', 'excusé') THEN 1 ELSE 0 END) as presences,
            ROUND((SUM(CASE WHEN a.statut IN ('present', 'retard', 'excusé') THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as taux
            FROM sessions s
            JOIN attendance a ON s.id = a.session_id
            WHERE a.student_id = ? AND s.date_session >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(s.date_session, '%Y-%m')
            ORDER BY mois ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $monthly_evolution = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur présence étudiant: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Présences - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Mes Présences</h1>
            <p>Consultez votre historique de présence et vos statistiques</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Filtres -->
        <div class="card">
            <div class="card-header">
                <h3>Filtres</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Filtrer par cours</label>
                            <select name="course_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Tous les cours</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                        <?php echo $course_id == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo $c['code'] . ' - ' . $c['titre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistiques globales -->
        <div class="card">
            <div class="card-header">
                <h3>Statistiques Globales</h3>
            </div>
            <div class="card-body">
                <?php if ($global_stats['total_seances'] > 0): ?>
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--success-color);"><?php echo $global_stats['presents']; ?></h3>
                                    <p>Présences</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--danger-color);"><?php echo $global_stats['absents']; ?></h3>
                                    <p>Absences</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--warning-color);"><?php echo $global_stats['retards'] + $global_stats['excuses']; ?></h3>
                                    <p>Retards/Excusés</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <?php
                                    $taux_global = calculatePercentage(
                                        $global_stats['presents'] + $global_stats['retards'] + $global_stats['excuses'],
                                        $global_stats['total_seances']
                                    );
                                    ?>
                                    <h3 style="color: var(--primary-color);"><?php echo $taux_global; ?>%</h3>
                                    <p>Taux Global</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Graphique de répartition -->
                    <div class="row mt-4">
                        <div class="col-6">
                            <h4>Répartition des Présences</h4>
                            <canvas id="attendanceChart" width="400" height="200"></canvas>
                        </div>
                        <div class="col-6">
                            <h4>Évolution Mensuelle</h4>
                            <canvas id="evolutionChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">📊</div>
                        <h3>Aucune donnée de présence</h3>
                        <p class="text-muted">Vos présences n'ont pas encore été enregistrées.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques par cours -->
        <?php if (!empty($course_stats)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Statistiques par Cours</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Cours</th>
                                    <th>Total Séances</th>
                                    <th>Présences</th>
                                    <th>Absences</th>
                                    <th>Retards/Excusés</th>
                                    
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $stat['code']; ?></strong><br>
                                            <small><?php echo $stat['titre']; ?></small>
                                        </td>
                                        <td><?php echo $stat['total_seances']; ?></td>
                                        <td>
                                            <span class="badge badge-success"><?php echo $stat['presents']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-danger"><?php echo $stat['absents']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning"><?php echo $stat['retards'] + $stat['excuses']; ?></span>
                                        </td>

                                        <td>
                                            <?php 
                                            if ($stat['taux_presence'] >= 80) {
                                                echo '<span class="badge badge-success">Excellent</span>';
                                            } elseif ($stat['taux_presence'] >= 70) {
                                                echo '<span class="badge badge-success">Bon</span>';
                                            } elseif ($stat['taux_presence'] >= 60) {
                                                echo '<span class="badge badge-warning">Moyen</span>';
                                            } else {
                                                echo '<span class="badge badge-danger">Faible</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Historique détaillé -->
        <div class="card">
            <div class="card-header">
                <h3>Historique Détaillé des Présences</h3>
                <span class="badge badge-info"><?php echo count($attendance_history); ?> enregistrements</span>
            </div>
            <div class="card-body">
                <?php if (!empty($attendance_history)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Cours</th>
                                    <th>Séance</th>
                                    <th>Enseignant</th>
                                    <th>Statut</th>
                                    <th>Remarque</th>
                                    <th>Heure</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_history as $attendance): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo formatDate($attendance['date_session'], 'd/m/Y'); ?></strong><br>
                                            <small><?php echo formatDate($attendance['date_session'], 'H:i'); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo $attendance['course_code']; ?></strong><br>
                                            <small><?php echo $attendance['course_titre']; ?></small>
                                        </td>
                                        <td><?php echo $attendance['session_titre']; ?></td>
                                        <td><?php echo $attendance['enseignant_prenom'] . ' ' . $attendance['enseignant_nom']; ?></td>
                                        <td><?php echo getAttendanceStatusBadge($attendance['statut']); ?></td>
                                        <td><?php echo $attendance['remarque'] ?: '-'; ?></td>
                                        <td><?php echo formatDate($attendance['date_marquage'], 'H:i'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">📝</div>
                        <h3>Aucun historique de présence</h3>
                        <p class="text-muted">
                            <?php if ($course_id): ?>
                                Aucune présence enregistrée pour ce cours.
                            <?php else: ?>
                                Aucune présence enregistrée pour le moment.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Graphique de répartition des présences
        <?php if ($global_stats['total_seances'] > 0): ?>
            const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
            const attendanceChart = new Chart(attendanceCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Présents', 'Absents', 'Retards', 'Excusés'],
                    datasets: [{
                        data: [
                            <?php echo $global_stats['presents']; ?>,
                            <?php echo $global_stats['absents']; ?>,
                            <?php echo $global_stats['retards']; ?>,
                            <?php echo $global_stats['excuses']; ?>
                        ],
                        backgroundColor: [
                            '#27ae60', // Vert pour présents
                            '#e74c3c', // Rouge pour absents
                            '#f39c12', // Orange pour retards
                            '#3498db'  // Bleu pour excusés
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Graphique d'évolution mensuelle
            const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
            const evolutionChart = new Chart(evolutionCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_evolution, 'mois')); ?>,
                    datasets: [{
                        label: 'Taux de présence (%)',
                        data: <?php echo json_encode(array_column($monthly_evolution, 'taux')); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Taux de présence (%)'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>