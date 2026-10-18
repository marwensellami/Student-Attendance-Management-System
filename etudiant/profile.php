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
    
    // Récupérer les informations de l'étudiant
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id]);
    $etudiant = $stmt->fetch();
    
    if (!$etudiant) {
        $error = "Utilisateur non trouvé.";
    }
    
    // Mise à jour du profil
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $nom = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $date_naissance = sanitize($_POST['date_naissance'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        
        if (empty($nom) || empty($prenom) || empty($email) || empty($username)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!isValidEmail($email)) {
            $error = "Adresse email invalide.";
        } else {
            // Vérifier les conflits d'email/username
            $sql = "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $username, $etudiant_id]);
            
            if ($stmt->fetch()) {
                $error = "Un autre utilisateur utilise déjà cet email ou nom d'utilisateur.";
            } else {
                // Mise à jour
                $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, username = ?, 
                        date_naissance = ?, telephone = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prenom, $email, $username, $date_naissance, $telephone, $etudiant_id]);
                
                // Mettre à jour la session
                $_SESSION['user_nom'] = $nom;
                $_SESSION['user_prenom'] = $prenom;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_username'] = $username;
                
                logAction($_SESSION['user_id'], 'student_profile_updated', "Mise à jour du profil étudiant");
                $success = "Profil mis à jour avec succès !";
                
                // Recharger les données
                $sql = "SELECT * FROM users WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$etudiant_id]);
                $etudiant = $stmt->fetch();
            }
        }
    }
    
    // Changement de mot de passe
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Veuillez remplir tous les champs du mot de passe.";
        } elseif (!password_verify($current_password, $etudiant['password'])) {
            $error = "Le mot de passe actuel est incorrect.";
        } elseif (!isValidPassword($new_password)) {
            $error = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Les nouveaux mots de passe ne correspondent pas.";
        } else {
            // Mise à jour du mot de passe
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hashedPassword, $etudiant_id]);
            
            logAction($_SESSION['user_id'], 'student_password_changed', "Changement de mot de passe étudiant");
            $success = "Mot de passe modifié avec succès !";
        }
    }
    
    // Récupérer les statistiques de l'étudiant
    $sql = "SELECT 
            (SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND statut = 'actif') as nb_cours,
            (SELECT COUNT(*) FROM attendance WHERE student_id = ?) as nb_presences_total,
            (SELECT COUNT(*) FROM attendance WHERE student_id = ? AND statut IN ('present', 'retard', 'excusé')) as nb_presences_valides";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id, $etudiant_id, $etudiant_id]);
    $stats = $stmt->fetch();
    
    // Récupérer les cours de l'étudiant avec statistiques
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM sessions WHERE course_id = c.id) as total_seances,
            (SELECT COUNT(*) FROM attendance a 
             JOIN sessions s ON a.session_id = s.id 
             WHERE s.course_id = c.id AND a.student_id = ? AND a.statut IN ('present', 'retard', 'excusé')) as presences
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = ? AND e.statut = 'actif'
            ORDER BY c.titre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$etudiant_id, $etudiant_id]);
    $student_courses = $stmt->fetchAll();
    
    // Calculer les taux de présence pour chaque cours
    foreach ($student_courses as &$course) {
        $course['taux_presence'] = calculatePercentage($course['presences'], $course['total_seances']);
    }
    
} catch (PDOException $e) {
    error_log("Erreur profil étudiant: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la mise à jour.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil Étudiant - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Mon Profil Étudiant</h1>
            <p>Gérez vos informations personnelles et consultez vos statistiques</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Informations du profil -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Informations Personnelles</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Nom *</label>
                                        <input type="text" name="nom" class="form-control" 
                                               value="<?php echo $etudiant['nom'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Prénom *</label>
                                        <input type="text" name="prenom" class="form-control" 
                                               value="<?php echo $etudiant['prenom'] ?? ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo $etudiant['email'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nom d'utilisateur *</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo $etudiant['username'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Date de Naissance</label>
                                        <input type="date" name="date_naissance" class="form-control" 
                                               value="<?php echo $etudiant['date_naissance'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Téléphone</label>
                                        <input type="tel" name="telephone" class="form-control" 
                                               value="<?php echo $etudiant['telephone'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    💾 Mettre à jour le profil
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Changement de mot de passe et informations -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Changement de Mot de Passe</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="form-group">
                                <label class="form-label">Mot de passe actuel *</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nouveau mot de passe *</label>
                                <input type="password" name="new_password" class="form-control" 
                                       minlength="8" required>
                                <small>Le mot de passe doit contenir au moins 8 caractères.</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirmer le nouveau mot de passe *</label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       minlength="8" required>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-warning">
                                    🔒 Changer le mot de passe
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Statistiques de l'étudiant -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Mes Statistiques</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-card mini">
                                    <div class="stat-info">
                                        <h3 style="color: var(--primary-color);"><?php echo $stats['nb_cours']; ?></h3>
                                        <p>Cours</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-card mini">
                                    <div class="stat-info">
                                        <h3 style="color: var(--success-color);"><?php echo $stats['nb_presences_valides']; ?></h3>
                                        <p>Présences</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-card mini">
                                    <div class="stat-info">
                                        <?php
                                        $taux_global = calculatePercentage($stats['nb_presences_valides'], $stats['nb_presences_total']);
                                        ?>
                                        <h3 style="color: var(--info-color);"><?php echo $taux_global; ?>%</h3>
                                        <p>Taux</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Informations de compte -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Informations du Compte</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th>Rôle</th>
                                <td>
                                    <span class="badge badge-success">Étudiant</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Date de création</th>
                                <td><?php echo formatDate($etudiant['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th>Dernière mise à jour</th>
                                <td><?php echo formatDate($etudiant['updated_at']); ?></td>
                            </tr>
                            <tr>
                                <th>Statut</th>
                                <td>
                                    <span class="badge badge-success">Actif</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mes cours avec statistiques détaillées -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Mes Cours et Statistiques de Présence</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($student_courses)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Cours</th>
                                            <th>Code</th>
                                            <th>Séances Total</th>
                                            <th>Présences</th>
                                            <th>Taux</th>
                                            <th>Performance</th>
                                            <th>Dernière Mise à Jour</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_courses as $course): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $course['titre']; ?></strong>
                                                    <?php if ($course['description']): ?>
                                                        <br><small class="text-muted"><?php echo substr($course['description'], 0, 100); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo $course['code']; ?></strong></td>
                                                <td><?php echo $course['total_seances']; ?></td>
                                                <td><?php echo $course['presences']; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px; width: 120px;">
                                                        <div class="progress-bar 
                                                            <?php 
                                                            if ($course['taux_presence'] >= 80) echo 'bg-success';
                                                            elseif ($course['taux_presence'] >= 60) echo 'bg-warning';
                                                            else echo 'bg-danger';
                                                            ?>"
                                                             style="width: <?php echo $course['taux_presence']; ?>%;"
                                                             role="progressbar">
                                                            <?php echo $course['taux_presence']; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($course['taux_presence'] >= 90) {
                                                        echo '<span class="badge badge-success">Excellent</span>';
                                                    } elseif ($course['taux_presence'] >= 80) {
                                                        echo '<span class="badge badge-success">Très bon</span>';
                                                    } elseif ($course['taux_presence'] >= 70) {
                                                        echo '<span class="badge badge-warning">Bon</span>';
                                                    } elseif ($course['taux_presence'] >= 60) {
                                                        echo '<span class="badge badge-warning">Satisfaisant</span>';
                                                    } else {
                                                        echo '<span class="badge badge-danger">À améliorer</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo formatDate($course['updated_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Résumé des performances -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h4>Résumé de mes Performances</h4>
                                    <?php
                                    $excellent = 0; $tres_bon = 0; $bon = 0; $satisfaisant = 0; $a_ameliorer = 0;
                                    foreach ($student_courses as $course) {
                                        if ($course['taux_presence'] >= 90) $excellent++;
                                        elseif ($course['taux_presence'] >= 80) $tres_bon++;
                                        elseif ($course['taux_presence'] >= 70) $bon++;
                                        elseif ($course['taux_presence'] >= 60) $satisfaisant++;
                                        else $a_ameliorer++;
                                    }
                                    ?>
                                    
                                    <div class="performance-summary">
                                        <div class="performance-item">
                                            <span class="performance-label">Excellent (≥90%)</span>
                                            <span class="performance-count"><?php echo $excellent; ?> cours</span>
                                        </div>
                                        <div class="performance-item">
                                            <span class="performance-label">Très bon (80-89%)</span>
                                            <span class="performance-count"><?php echo $tres_bon; ?> cours</span>
                                        </div>
                                        <div class="performance-item">
                                            <span class="performance-label">Bon (70-79%)</span>
                                            <span class="performance-count"><?php echo $bon; ?> cours</span>
                                        </div>
                                        <div class="performance-item">
                                            <span class="performance-label">Satisfaisant (60-69%)</span>
                                            <span class="performance-count"><?php echo $satisfaisant; ?> cours</span>
                                        </div>
                                        <div class="performance-item">
                                            <span class="performance-label">À améliorer (<60%)</span>
                                            <span class="performance-count"><?php echo $a_ameliorer; ?> cours</span>
                                        </div>
                                    </div>
                                </div>
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
        </div>
        
        <!-- Activité récente -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Mon Activité Récente</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Récupérer les dernières activités de l'étudiant
                        $sql = "SELECT a.*, s.titre as session_titre, c.code as course_code
                                FROM attendance a
                                JOIN sessions s ON a.session_id = s.id
                                JOIN courses c ON s.course_id = c.id
                                WHERE a.student_id = ?
                                ORDER BY a.date_marquage DESC
                                LIMIT 10";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$etudiant_id]);
                        $recent_activity = $stmt->fetchAll();
                        ?>
                        
                        <?php if (!empty($recent_activity)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Cours</th>
                                            <th>Séance</th>
                                            <th>Statut</th>
                                            <th>Remarque</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <tr>
                                                <td><?php echo formatDate($activity['date_marquage']); ?></td>
                                                <td><?php echo $activity['course_code']; ?></td>
                                                <td><?php echo $activity['session_titre']; ?></td>
                                                <td><?php echo getAttendanceStatusBadge($activity['statut']); ?></td>
                                                <td><?php echo $activity['remarque'] ?: '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Aucune activité récente.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <style>
        .performance-summary {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-color);
        }
        
        .performance-label {
            font-weight: 500;
        }
        
        .performance-count {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .performance-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
        }
    </style>
</body>
</html>