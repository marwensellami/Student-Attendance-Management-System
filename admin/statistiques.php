<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un administrateur
requireAuth(ROLE_ADMIN);

try {
    global $pdo;
    
    // Statistiques générales
    $sql = "SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'etudiant' AND is_active = 1) as total_etudiants,
            (SELECT COUNT(*) FROM users WHERE role = 'enseignant' AND is_active = 1) as total_enseignants,
            (SELECT COUNT(*) FROM courses) as total_cours,
            (SELECT COUNT(*) FROM sessions) as total_seances,
            (SELECT COUNT(*) FROM attendance) as total_presences";
    $stmt = $pdo->query($sql);
    $stats_general = $stmt->fetch();
    
    // Taux de présence global
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
            SUM(CASE WHEN statut = 'retard' THEN 1 ELSE 0 END) as retards,
            SUM(CASE WHEN statut = 'excusé' THEN 1 ELSE 0 END) as excuses,
            SUM(CASE WHEN statut = 'absent' THEN 1 ELSE 0 END) as absents
            FROM attendance";
    $stmt = $pdo->query($sql);
    $attendance_stats = $stmt->fetch();
    $attendance_stats['taux_presence'] = calculatePercentage(
        $attendance_stats['presents'] + $attendance_stats['retards'] + $attendance_stats['excuses'],
        $attendance_stats['total']
    );
    
    // Évolution des inscriptions par mois
    $sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mois,
            COUNT(*) as inscriptions
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mois";
    $stmt = $pdo->query($sql);
    $evolution_inscriptions = $stmt->fetchAll();
    
    // Top 5 des cours avec le plus d'étudiants
    $sql = "SELECT c.code, c.titre, COUNT(e.student_id) as nb_etudiants
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id
            GROUP BY c.id
            ORDER BY nb_etudiants DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $top_cours = $stmt->fetchAll();
    
    // Répartition des présences par statut
    $sql = "SELECT statut, COUNT(*) as count
            FROM attendance 
            GROUP BY statut
            ORDER BY count DESC";
    $stmt = $pdo->query($sql);
    $repartition_presences = $stmt->fetchAll();
    
    // Activité récente (dernières 30 jours)
    $sql = "SELECT 
            DATE(date_marquage) as date,
            COUNT(*) as nb_presences
            FROM attendance 
            WHERE date_marquage >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(date_marquage)
            ORDER BY date";
    $stmt = $pdo->query($sql);
    $activite_recente = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur statistiques: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des statistiques.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Statistiques Globales</h1>
            <p>Vue d'ensemble de l'activité sur la plateforme</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques principales -->
        <div class="row">
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #3498db;">
                        👥
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_general['total_etudiants'] + $stats_general['total_enseignants']; ?></h3>
                        <p>Utilisateurs Actifs</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #27ae60;">
                        🎓
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_general['total_etudiants']; ?></h3>
                        <p>Étudiants</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e74c3c;">
                        👨‍🏫
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_general['total_enseignants']; ?></h3>
                        <p>Enseignants</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        📚
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_general['total_cours']; ?></h3>
                        <p>Cours Actifs</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #9b59b6;">
                        📅
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_general['total_seances']; ?></h3>
                        <p>Séances Planifiées</p>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #1abc9c;">
                        ✅
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats_general['total_presences']; ?></h3>
                        <p>Présences Enregistrées</p>
                    </div>
                </div>
            </div>
            
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        📊
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $attendance_stats['taux_presence']; ?>%</h3>
                        <p>Taux de Présence Global</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Graphiques -->
        <div class="row">
            <!-- Répartition des présences -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Répartition des Présences</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Top 5 des cours -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Top 5 des Cours les Plus Populaires</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="coursesChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Évolution des inscriptions -->
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Évolution des Inscriptions (12 derniers mois)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="inscriptionsChart" width="600" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Détails des statistiques de présence -->
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h3>Détails des Présences</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <td>Présents</td>
                                <td class="text-right">
                                    <span class="badge badge-success"><?php echo $attendance_stats['presents']; ?></span>
                                </td>
                                <td class="text-right">
                                    <?php echo calculatePercentage($attendance_stats['presents'], $attendance_stats['total']); ?>%
                                </td>
                            </tr>
                            <tr>
                                <td>Retards</td>
                                <td class="text-right">
                                    <span class="badge badge-warning"><?php echo $attendance_stats['retards']; ?></span>
                                </td>
                                <td class="text-right">
                                    <?php echo calculatePercentage($attendance_stats['retards'], $attendance_stats['total']); ?>%
                                </td>
                            </tr>
                            <tr>
                                <td>Excusés</td>
                                <td class="text-right">
                                    <span class="badge badge-info"><?php echo $attendance_stats['excuses']; ?></span>
                                </td>
                                <td class="text-right">
                                    <?php echo calculatePercentage($attendance_stats['excuses'], $attendance_stats['total']); ?>%
                                </td>
                            </tr>
                            <tr>
                                <td>Absents</td>
                                <td class="text-right">
                                    <span class="badge badge-danger"><?php echo $attendance_stats['absents']; ?></span>
                                </td>
                                <td class="text-right">
                                    <?php echo calculatePercentage($attendance_stats['absents'], $attendance_stats['total']); ?>%
                                </td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>Total</strong></td>
                                <td class="text-right">
                                    <strong><?php echo $attendance_stats['total']; ?></strong>
                                </td>
                                <td class="text-right">
                                    <strong>100%</strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tableau des cours populaires -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Top des Cours par Nombre d'Étudiants</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_cours)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Position</th>
                                            <th>Code</th>
                                            <th>Titre</th>
                                            <th>Nombre d'Étudiants</th>
                                            <th>Pourcentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_etudiants_cours = array_sum(array_column($top_cours, 'nb_etudiants'));
                                        $position = 1;
                                        foreach ($top_cours as $cours): 
                                            $pourcentage = calculatePercentage($cours['nb_etudiants'], $total_etudiants_cours);
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $position === 1 ? 'warning' : 
                                                             ($position === 2 ? 'secondary' : 
                                                             ($position === 3 ? 'info' : 'light')); 
                                                    ?>">
                                                        #<?php echo $position; ?>
                                                    </span>
                                                </td>
                                                <td><strong><?php echo $cours['code']; ?></strong></td>
                                                <td><?php echo $cours['titre']; ?></td>
                                                <td>
                                                    <span class="badge badge-primary"><?php echo $cours['nb_etudiants']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 30px ; background-color: #3498db;" >
                                                        <div class="progress-bar"
                                                        
                                                             style="width: <?php echo $pourcentage; ?>%;"
                                                             role="progressbar">
                                                            <?php echo $pourcentage; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $position++; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucun cours avec des étudiants inscrits.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Graphique de répartition des présences
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Présents', 'Retards', 'Excusés', 'Absents'],
                datasets: [{
                    data: [
                        <?php echo $attendance_stats['presents']; ?>,
                        <?php echo $attendance_stats['retards']; ?>,
                        <?php echo $attendance_stats['excuses']; ?>,
                        <?php echo $attendance_stats['absents']; ?>
                    ],
                    backgroundColor: [
                        '#27ae60', // Vert pour présents
                        '#f39c12', // Orange pour retards
                        '#3498db', // Bleu pour excusés
                        '#e74c3c'  // Rouge pour absents
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
        
        // Graphique des cours populaires
        const coursesCtx = document.getElementById('coursesChart').getContext('2d');
        const coursesChart = new Chart(coursesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($top_cours, 'code')); ?>,
                datasets: [{
                    label: 'Nombre d\'étudiants',
                    data: <?php echo json_encode(array_column($top_cours, 'nb_etudiants')); ?>,
                    backgroundColor: '#3498db'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Graphique d'évolution des inscriptions
        const inscriptionsCtx = document.getElementById('inscriptionsChart').getContext('2d');
        const inscriptionsChart = new Chart(inscriptionsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($evolution_inscriptions, 'mois')); ?>,
                datasets: [{
                    label: 'Inscriptions',
                    data: <?php echo json_encode(array_column($evolution_inscriptions, 'inscriptions')); ?>,
                    borderColor: '#9b59b6',
                    backgroundColor: 'rgba(155, 89, 182, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>