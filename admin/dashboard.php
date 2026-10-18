<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un administrateur
requireAuth(ROLE_ADMIN);

// Récupérer les statistiques globales
$stats = [];
try {
    global $pdo;
    
    // Nombre total d'utilisateurs par rôle
    $sql = "SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role";
    $stmt = $pdo->query($sql);
    $stats['users'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Nombre total de cours
    $sql = "SELECT COUNT(*) as total_courses FROM courses";
    $stmt = $pdo->query($sql);
    $stats['total_courses'] = $stmt->fetchColumn();
    
    // Nombre total de séances
    $sql = "SELECT COUNT(*) as total_sessions FROM sessions";
    $stmt = $pdo->query($sql);
    $stats['total_sessions'] = $stmt->fetchColumn();
    
    // Nombre total de présences
    $sql = "SELECT COUNT(*) as total_attendance FROM attendance";
    $stmt = $pdo->query($sql);
    $stats['total_attendance'] = $stmt->fetchColumn();
    
    // Taux de présence global
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents
            FROM attendance";
    $stmt = $pdo->query($sql);
    $attendance_data = $stmt->fetch();
    $stats['attendance_rate'] = calculatePercentage($attendance_data['presents'], $attendance_data['total']);
    
    // Derniers utilisateurs inscrits
    $sql = "SELECT id, nom, prenom, email, role, created_at 
            FROM users 
            WHERE is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $stats['recent_users'] = $stmt->fetchAll();
    
    // Cours récents
    $sql = "SELECT c.*, u.nom as enseignant_nom, u.prenom as enseignant_prenom
            FROM courses c 
            JOIN users u ON c.enseignant_id = u.id 
            ORDER BY c.created_at DESC 
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $stats['recent_courses'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur lors du chargement des statistiques: " . $e->getMessage());
    $error = "Erreur lors du chargement des données.";
}

// Journaliser l'accès
logAction($_SESSION['user_id'], 'admin_dashboard_access', 'Accès au tableau de bord administrateur');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Tableau de Bord Administrateur</h1>
            <p>Bienvenue, <?php echo $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']; ?></p>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques -->
        <div class="row">
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #3498db;">
                        👥
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['users']['admin'] + $stats['users']['enseignant'] + $stats['users']['etudiant']; ?></h3>
                        <p>Utilisateurs Totaux</p>
                    </div>
                </div>
            </div>
            
            <div class="col-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #27ae60;">
                        🎓
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['users']['etudiant'] ?? 0; ?></h3>
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
                        <h3><?php echo $stats['users']['enseignant'] ?? 0; ?></h3>
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
                        <h3><?php echo $stats['total_courses']; ?></h3>
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
                        <h3><?php echo $stats['total_sessions']; ?></h3>
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
                        <h3><?php echo $stats['total_attendance']; ?></h3>
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
                        <h3><?php echo $stats['attendance_rate']; ?>%</h3>
                        <p>Taux de Présence Global</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Derniers utilisateurs inscrits -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Derniers Utilisateurs Inscrits</h3>
                        <a href="gestion_utilisateurs.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stats['recent_users'])): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Email</th>
                                            <th>Rôle</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_users'] as $user): ?>
                                            <tr>
                                                <td><?php echo $user['prenom'] . ' ' . $user['nom']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $user['role'] === 'admin' ? 'danger' : 
                                                             ($user['role'] === 'enseignant' ? 'warning' : 'success'); 
                                                    ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($user['created_at'], 'd/m/Y'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucun utilisateur inscrit récemment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Cours récents -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Derniers Cours Créés</h3>
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
                                            <th>Enseignant</th>
                                            <th>Date Début</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_courses'] as $course): ?>
                                            <tr>
                                                <td><strong><?php echo $course['code']; ?></strong></td>
                                                <td><?php echo $course['titre']; ?></td>
                                                <td><?php echo $course['enseignant_prenom'] . ' ' . $course['enseignant_nom']; ?></td>
                                                <td><?php echo formatDate($course['date_debut'], 'd/m/Y'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucun cours créé récemment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Actions Rapides</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="gestion_utilisateurs.php?action=add" class="quick-action">
                                <div class="quick-icon">➕</div>
                                <span>Ajouter Utilisateur</span>
                            </a>
                            <a href="gestion_cours.php?action=add" class="quick-action">
                                <div class="quick-icon">📚</div>
                                <span>Créer Cours</span>
                            </a>
                            <a href="suivi_presence.php" class="quick-action">
                                <div class="quick-icon">👀</div>
                                <span>Suivi Présence</span>
                            </a>
                            <a href="statistiques.php" class="quick-action">
                                <div class="quick-icon">📊</div>
                                <span>Statistiques</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>