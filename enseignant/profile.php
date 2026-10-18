<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un enseignant
requireAuth(ROLE_ENSEIGNANT);

$enseignant_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    global $pdo;
    
    // Récupérer les informations de l'enseignant
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $enseignant = $stmt->fetch();
    
    if (!$enseignant) {
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
            $stmt->execute([$email, $username, $enseignant_id]);
            
            if ($stmt->fetch()) {
                $error = "Un autre utilisateur utilise déjà cet email ou nom d'utilisateur.";
            } else {
                // Mise à jour
                $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, username = ?, 
                        date_naissance = ?, telephone = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prenom, $email, $username, $date_naissance, $telephone, $enseignant_id]);
                
                // Mettre à jour la session
                $_SESSION['user_nom'] = $nom;
                $_SESSION['user_prenom'] = $prenom;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_username'] = $username;
                
                logAction($_SESSION['user_id'], 'teacher_profile_updated', "Mise à jour du profil enseignant");
                $success = "Profil mis à jour avec succès !";
                
                // Recharger les données
                $sql = "SELECT * FROM users WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enseignant_id]);
                $enseignant = $stmt->fetch();
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
        } elseif (!password_verify($current_password, $enseignant['password'])) {
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
            $stmt->execute([$hashedPassword, $enseignant_id]);
            
            logAction($_SESSION['user_id'], 'teacher_password_changed', "Changement de mot de passe enseignant");
            $success = "Mot de passe modifié avec succès !";
        }
    }
    
    // Récupérer les statistiques de l'enseignant
    $sql = "SELECT 
            (SELECT COUNT(*) FROM courses WHERE enseignant_id = ?) as nb_cours,
            (SELECT COUNT(*) FROM sessions s 
             JOIN courses c ON s.course_id = c.id 
             WHERE c.enseignant_id = ?) as nb_seances,
            (SELECT COUNT(DISTINCT e.student_id) 
             FROM enrollments e 
             JOIN courses c ON e.course_id = c.id 
             WHERE c.enseignant_id = ?) as nb_etudiants";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id, $enseignant_id, $enseignant_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Erreur profil enseignant: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la mise à jour.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil Enseignant - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Mon Profil Enseignant</h1>
            <p>Gérez vos informations personnelles et votre mot de passe</p>
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
                                               value="<?php echo $enseignant['nom'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Prénom *</label>
                                        <input type="text" name="prenom" class="form-control" 
                                               value="<?php echo $enseignant['prenom'] ?? ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo $enseignant['email'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nom d'utilisateur *</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo $enseignant['username'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Date de Naissance</label>
                                        <input type="date" name="date_naissance" class="form-control" 
                                               value="<?php echo $enseignant['date_naissance'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Téléphone</label>
                                        <input type="tel" name="telephone" class="form-control" 
                                               value="<?php echo $enseignant['telephone'] ?? ''; ?>">
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
                
                <!-- Statistiques de l'enseignant -->
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
                                        <h3 style="color: var(--info-color);"><?php echo $stats['nb_seances']; ?></h3>
                                        <p>Séances</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-card mini">
                                    <div class="stat-info">
                                        <h3 style="color: var(--success-color);"><?php echo $stats['nb_etudiants']; ?></h3>
                                        <p>Étudiants</p>
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
                                    <span class="badge badge-warning">Enseignant</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Date de création</th>
                                <td><?php echo formatDate($enseignant['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th>Dernière mise à jour</th>
                                <td><?php echo formatDate($enseignant['updated_at']); ?></td>
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
        
        <!-- Mes cours récents -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Mes Cours Récents</h3>
                        <a href="gestion_cours.php" class="btn btn-outline btn-sm">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php
                        // Récupérer les 5 derniers cours de l'enseignant
                        $sql = "SELECT c.*, 
                                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as nb_etudiants,
                                (SELECT COUNT(*) FROM sessions WHERE course_id = c.id) as nb_seances
                                FROM courses c 
                                WHERE c.enseignant_id = ?
                                ORDER BY c.created_at DESC 
                                LIMIT 5";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$enseignant_id]);
                        $recent_courses = $stmt->fetchAll();
                        ?>
                        
                        <?php if (!empty($recent_courses)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Titre</th>
                                            <th>Étudiants</th>
                                            <th>Séances</th>
                                            <th>Date Création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_courses as $course): ?>
                                            <tr>
                                                <td><strong><?php echo $course['code']; ?></strong></td>
                                                <td><?php echo $course['titre']; ?></td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $course['nb_etudiants']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary"><?php echo $course['nb_seances']; ?></span>
                                                </td>
                                                <td><?php echo formatDate($course['created_at']); ?></td>
                                                <td>
                                                    <a href="gestion_cours.php?action=view&id=<?php echo $course['id']; ?>" 
                                                       class="btn btn-sm btn-outline">
                                                        👁️ Voir
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Vous n'avez pas encore créé de cours.</p>
                            <div class="text-center">
                                <a href="gestion_cours.php?action=add" class="btn btn-primary">
                                    ➕ Créer mon premier cours
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>