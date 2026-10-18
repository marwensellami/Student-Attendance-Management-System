<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un enseignant
requireAuth(ROLE_ENSEIGNANT);

$enseignant_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$export = $_GET['export'] ?? '';
$error = '';

try {
    global $pdo;
    
    // Récupérer les cours de l'enseignant
    $sql = "SELECT c.* 
            FROM courses c 
            WHERE c.enseignant_id = ?
            ORDER BY c.titre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $courses = $stmt->fetchAll();
    
    // Récupérer les séances si un cours est sélectionné
    $sessions = [];
    if ($course_id) {
        $sql = "SELECT s.* 
                FROM sessions s 
                WHERE s.course_id = ? 
                ORDER BY s.date_session DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id]);
        $sessions = $stmt->fetchAll();
    }
    
    // Données pour les rapports
    $report_data = [];
    $course_info = null;
    $session_info = null;
    
    if ($course_id) {
        // Informations du cours
        $sql = "SELECT c.* FROM courses c WHERE c.id = ? AND c.enseignant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id, $enseignant_id]);
        $course_info = $stmt->fetch();
        
        if ($course_info) {
            // Statistiques générales du cours
            $sql = "SELECT 
                    (SELECT COUNT(*) FROM enrollments WHERE course_id = ?) as nb_etudiants,
                    (SELECT COUNT(*) FROM sessions WHERE course_id = ?) as nb_seances,
                    (SELECT COUNT(*) FROM attendance a 
                     JOIN sessions s ON a.session_id = s.id 
                     WHERE s.course_id = ?) as nb_presences_total";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $course_id, $course_id]);
            $report_data['course_stats'] = $stmt->fetch();
            
            // Taux de présence par séance
            $sql = "SELECT s.id, s.titre, s.date_session,
                    COUNT(a.id) as nb_etudiants_presents,
                    (SELECT COUNT(*) FROM enrollments WHERE course_id = ?) as nb_etudiants_total,
                    ROUND((COUNT(a.id) * 100.0 / (SELECT COUNT(*) FROM enrollments WHERE course_id = ?)), 2) as taux_presence
                    FROM sessions s
                    LEFT JOIN attendance a ON s.id = a.session_id AND a.statut IN ('present', 'retard', 'excusé')
                    WHERE s.course_id = ?
                    GROUP BY s.id
                    ORDER BY s.date_session DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $course_id, $course_id]);
            $report_data['session_rates'] = $stmt->fetchAll();
            
            // Statistiques par étudiant
            $sql = "SELECT u.id, u.nom, u.prenom, u.email,
                    COUNT(a.id) as nb_presences,
                    (SELECT COUNT(*) FROM sessions WHERE course_id = ?) as total_seances,
                    ROUND((COUNT(a.id) * 100.0 / (SELECT COUNT(*) FROM sessions WHERE course_id = ?)), 2) as taux_presence
                    FROM users u
                    JOIN enrollments e ON u.id = e.student_id
                    LEFT JOIN attendance a ON u.id = a.student_id AND a.statut IN ('present', 'retard', 'excusé')
                    LEFT JOIN sessions s ON a.session_id = s.id AND s.course_id = ?
                    WHERE e.course_id = ?
                    GROUP BY u.id
                    ORDER BY taux_presence DESC, u.nom, u.prenom";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$course_id, $course_id, $course_id, $course_id]);
            $report_data['student_stats'] = $stmt->fetchAll();
        }
    }
    
    // Données spécifiques à une séance
    if ($session_id) {
        $sql = "SELECT s.*, c.titre as course_titre, c.code as course_code
                FROM sessions s 
                JOIN courses c ON s.course_id = c.id 
                WHERE s.id = ? AND c.enseignant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$session_id, $enseignant_id]);
        $session_info = $stmt->fetch();
        
        if ($session_info) {
            // Présences détaillées pour la séance
            $sql = "SELECT a.*, u.nom, u.prenom, u.email
                    FROM attendance a
                    JOIN users u ON a.student_id = u.id
                    WHERE a.session_id = ?
                    ORDER BY u.nom, u.prenom";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$session_id]);
            $report_data['session_attendance'] = $stmt->fetchAll();
            
            // Statistiques de la séance
            $stats = [
                'present' => 0,
                'absent' => 0,
                'retard' => 0,
                'excusé' => 0
            ];
            
            foreach ($report_data['session_attendance'] as $att) {
                $stats[$att['statut']]++;
            }
            $report_data['session_stats'] = $stats;
        }
    }
    
    // Export CSV
    if ($export === 'csv' && $course_id && $course_info) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rapport_presences_' . $course_info['code'] . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-tête CSV
        fputcsv($output, ['Rapport de Présences - ' . $course_info['code'] . ' - ' . $course_info['titre']]);
        fputcsv($output, ['Généré le', date('d/m/Y H:i')]);
        fputcsv($output, []); // Ligne vide
        
        // En-tête du tableau
        fputcsv($output, ['Étudiant', 'Email', 'Séances présentes', 'Total séances', 'Taux de présence', 'Statut']);
        
        // Données des étudiants
        foreach ($report_data['student_stats'] as $student) {
            $statut = '';
            if ($student['taux_presence'] >= 80) $statut = 'Assidu';
            elseif ($student['taux_presence'] >= 60) $statut = 'Régulier';
            else $statut = 'Absentéiste';
            
            fputcsv($output, [
                $student['prenom'] . ' ' . $student['nom'],
                $student['email'],
                $student['nb_presences'],
                $student['total_seances'],
                $student['taux_presence'] . '%',
                $statut
            ]);
        }
        
        fclose($output);
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Erreur rapports enseignant: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et Exports - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Rapports et Exports</h1>
            <p>Générez des rapports détaillés sur les présences de vos étudiants</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Sélection du cours -->
        <div class="card">
            <div class="card-header">
                <h3>Sélection du Cours</h3>
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
                                <label class="form-label">Sélectionner une séance (optionnel)</label>
                                <select name="session_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Toutes les séances</option>
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
        
        <!-- Rapport global du cours -->
        <?php if ($course_id && $course_info && empty($session_id)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Rapport Global - <?php echo $course_info['code']; ?></h3>
                    <div class="header-actions">
                        <a href="?course_id=<?php echo $course_id; ?>&export=csv" class="btn btn-success btn-sm">
                            📄 Exporter en CSV
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Statistiques générales -->
                    <div class="row text-center mb-4">
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--primary-color);"><?php echo $report_data['course_stats']['nb_etudiants']; ?></h3>
                                    <p>Étudiants</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--info-color);"><?php echo $report_data['course_stats']['nb_seances']; ?></h3>
                                    <p>Séances</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--success-color);"><?php echo $report_data['course_stats']['nb_presences_total']; ?></h3>
                                    <p>Présences</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <?php
                                    $taux_global_cours = calculatePercentage(
                                        $report_data['course_stats']['nb_presences_total'],
                                        $report_data['course_stats']['nb_etudiants'] * $report_data['course_stats']['nb_seances']
                                    );
                                    ?>
                                    <h3 style="color: var(--warning-color);"><?php echo $taux_global_cours; ?>%</h3>
                                    <p>Taux Global</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Graphique des taux de présence par séance -->
                    <div class="row">
                        <div class="col-8">
                            <h4>Taux de Présence par Séance</h4>
                            <canvas id="sessionRatesChart" width="400" height="200"></canvas>
                        </div>
                        <div class="col-4">
                            <h4>Répartition des Étudiants</h4>
                            <?php
                            $assidus = 0; $reguliers = 0; $absentéistes = 0;
                            foreach ($report_data['student_stats'] as $student) {
                                if ($student['taux_presence'] >= 80) $assidus++;
                                elseif ($student['taux_presence'] >= 60) $reguliers++;
                                else $absentéistes++;
                            }
                            ?>
                            <canvas id="studentDistributionChart" width="200" height="200"></canvas>
                        </div>
                    </div>
                    
                    <!-- Tableau détaillé des étudiants -->
                    <div class="mt-4">
                        <h4>Détail par Étudiant</h4>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Email</th>
                                        <th>Séances Présentes</th>
                                        <th>Taux</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['student_stats'] as $student): ?>
                                        <tr>
                                            <td><?php echo $student['prenom'] . ' ' . $student['nom']; ?></td>
                                            <td><?php echo $student['email']; ?></td>
                                            <td>
                                                <?php echo $student['nb_presences']; ?> / <?php echo $student['total_seances']; ?>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar 
                                                        <?php 
                                                        if ($student['taux_presence'] >= 80) echo 'bg-success';
                                                        elseif ($student['taux_presence'] >= 60) echo 'bg-warning';
                                                        else echo 'bg-danger';
                                                        ?>"
                                                         style="width: <?php echo $student['taux_presence']; ?>%;"
                                                         role="progressbar">
                                                        <?php echo $student['taux_presence']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($student['taux_presence'] >= 80) {
                                                    echo '<span class="badge badge-success">Assidu</span>';
                                                } elseif ($student['taux_presence'] >= 60) {
                                                    echo '<span class="badge badge-warning">Régulier</span>';
                                                } else {
                                                    echo '<span class="badge badge-danger">Absentéiste</span>';
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
            </div>
        <?php endif; ?>
        
        <!-- Rapport détaillé d'une séance -->
        <?php if ($session_id && $session_info): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Rapport de Séance</h3>
                    <div>
                        <span class="badge badge-info"><?php echo $session_info['course_code']; ?></span>
                        <span class="badge badge-secondary"><?php echo formatDate($session_info['date_session']); ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="session-info mb-4">
                        <h4><?php echo $session_info['titre']; ?></h4>
                        <?php if ($session_info['description']): ?>
                            <p class="text-muted"><?php echo $session_info['description']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Statistiques de la séance -->
                    <div class="row text-center mb-4">
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--success-color);"><?php echo $report_data['session_stats']['present']; ?></h3>
                                    <p>Présents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--danger-color);"><?php echo $report_data['session_stats']['absent']; ?></h3>
                                    <p>Absents</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--warning-color);"><?php echo $report_data['session_stats']['retard']; ?></h3>
                                    <p>Retards</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card mini">
                                <div class="stat-info">
                                    <h3 style="color: var(--info-color);"><?php echo $report_data['session_stats']['excusé']; ?></h3>
                                    <p>Excusés</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Graphique de répartition -->
                    <div class="row mb-4">
                        <div class="col-6">
                            <canvas id="sessionAttendanceChart" width="300" height="200"></canvas>
                        </div>
                        <div class="col-6">
                            <?php
                            $total_etudiants = count($report_data['session_attendance']);
                            $taux_presence_seance = calculatePercentage(
                                $report_data['session_stats']['present'] + $report_data['session_stats']['retard'] + $report_data['session_stats']['excusé'],
                                $total_etudiants
                            );
                            ?>
                            <div class="text-center">
                                <h3>Taux de Présence</h3>
                                <div style="font-size: 3rem; color: var(--primary-color); font-weight: bold;">
                                    <?php echo $taux_presence_seance; ?>%
                                </div>
                                <p class="text-muted">
                                    <?php echo $report_data['session_stats']['present'] + $report_data['session_stats']['retard'] + $report_data['session_stats']['excusé']; ?>
                                    / <?php echo $total_etudiants ?> étudiants
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liste détaillée des présences -->
                    <h4>Liste des Présences</h4>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Email</th>
                                    <th>Statut</th>
                                    <th>Remarque</th>
                                    <th>Heure de marquage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['session_attendance'] as $attendance): ?>
                                    <tr>
                                        <td><?php echo $attendance['prenom'] . ' ' . $attendance['nom']; ?></td>
                                        <td><?php echo $attendance['email']; ?></td>
                                        <td><?php echo getAttendanceStatusBadge($attendance['statut']); ?></td>
                                        <td><?php echo $attendance['remarque'] ?: '-'; ?></td>
                                        <td><?php echo formatDate($attendance['date_marquage']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Message si aucun cours sélectionné -->
        <?php if (empty($course_id)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📊</div>
                    <h3>Sélectionnez un cours</h3>
                    <p class="text-muted">Choisissez un cours dans la liste pour générer des rapports détaillés sur les présences.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Graphique des taux de présence par séance
        <?php if (isset($report_data['session_rates'])): ?>
            const sessionRatesCtx = document.getElementById('sessionRatesChart').getContext('2d');
            const sessionRatesChart = new Chart(sessionRatesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($s) { 
                        return formatDate($s['date_session'], 'd/m'); 
                    }, $report_data['session_rates'])); ?>,
                    datasets: [{
                        label: 'Taux de présence (%)',
                        data: <?php echo json_encode(array_column($report_data['session_rates'], 'taux_presence')); ?>,
                        backgroundColor: '#3498db',
                        borderColor: '#2980b9',
                        borderWidth: 1
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
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Séances'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
        
        // Graphique de répartition des étudiants
        <?php if (isset($assidus, $reguliers, $absentéistes)): ?>
            const studentDistributionCtx = document.getElementById('studentDistributionChart').getContext('2d');
            const studentDistributionChart = new Chart(studentDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Assidus', 'Réguliers', 'Absentéistes'],
                    datasets: [{
                        data: [<?php echo $assidus; ?>, <?php echo $reguliers; ?>, <?php echo $absentéistes; ?>],
                        backgroundColor: [
                            '#27ae60',
                            '#f39c12',
                            '#e74c3c'
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
        <?php endif; ?>
        
        // Graphique des présences pour une séance
        <?php if (isset($report_data['session_stats'])): ?>
            const sessionAttendanceCtx = document.getElementById('sessionAttendanceChart').getContext('2d');
            const sessionAttendanceChart = new Chart(sessionAttendanceCtx, {
                type: 'pie',
                data: {
                    labels: ['Présents', 'Absents', 'Retards', 'Excusés'],
                    datasets: [{
                        data: [
                            <?php echo $report_data['session_stats']['present']; ?>,
                            <?php echo $report_data['session_stats']['absent']; ?>,
                            <?php echo $report_data['session_stats']['retard']; ?>,
                            <?php echo $report_data['session_stats']['excusé']; ?>
                        ],
                        backgroundColor: [
                            '#27ae60',
                            '#e74c3c',
                            '#f39c12',
                            '#3498db'
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
        <?php endif; ?>
    </script>
</body>
</html>