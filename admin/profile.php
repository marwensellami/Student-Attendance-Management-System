<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un administrateur
requireAuth(ROLE_ADMIN);

$error = '';
$success = '';

try {
    global $pdo;
    
    // Récupérer les informations de l'administrateur
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
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
            $stmt->execute([$email, $username, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                $error = "Un autre utilisateur utilise déjà cet email ou nom d'utilisateur.";
            } else {
                // Mise à jour
                $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, username = ?, 
                        date_naissance = ?, telephone = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prenom, $email, $username, $date_naissance, $telephone, $_SESSION['user_id']]);
                
                // Mettre à jour la session
                $_SESSION['user_nom'] = $nom;
                $_SESSION['user_prenom'] = $prenom;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_username'] = $username;
                
                logAction($_SESSION['user_id'], 'profile_updated', "Mise à jour du profil administrateur");
                $success = "Profil mis à jour avec succès !";
                
                // Recharger les données
                $sql = "SELECT * FROM users WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['user_id']]);
                $admin = $stmt->fetch();
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
        } elseif (!password_verify($current_password, $admin['password'])) {
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
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            
            logAction($_SESSION['user_id'], 'password_changed', "Changement de mot de passe administrateur");
            $success = "Mot de passe modifié avec succès !";
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur profil admin: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la mise à jour.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Mon Profil Administrateur</h1>
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
                                               value="<?php echo $admin['nom'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Prénom *</label>
                                        <input type="text" name="prenom" class="form-control" 
                                               value="<?php echo $admin['prenom'] ?? ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo $admin['email'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nom d'utilisateur *</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo $admin['username'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Date de Naissance</label>
                                        <input type="date" name="date_naissance" class="form-control" 
                                               value="<?php echo $admin['date_naissance'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Téléphone</label>
                                        <input type="tel" name="telephone" class="form-control" 
                                               value="<?php echo $admin['telephone'] ?? ''; ?>">
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
            
            <!-- Changement de mot de passe -->
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
                                    <span class="badge badge-danger">Administrateur</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Date de création</th>
                                <td><?php echo formatDate($admin['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th>Dernière mise à jour</th>
                                <td><?php echo formatDate($admin['updated_at']); ?></td>
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
        
        <!-- Statistiques personnelles -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3>Activité Récente</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Récupérer les dernières activités de l'admin
                        $sql = "SELECT action, details, created_at 
                                FROM activity_logs 
                                WHERE user_id = ? 
                                ORDER BY created_at DESC 
                                LIMIT 10";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$_SESSION['user_id']]);
                        $activites = $stmt->fetchAll();
                        ?>
                        
                        <?php if (!empty($activites)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Détails</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activites as $activite): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $icons = [
                                                        'user_created' => '👤',
                                                        'user_updated' => '✏️',
                                                        'user_deleted' => '🗑️',
                                                        'course_created' => '📚',
                                                        'course_updated' => '✏️',
                                                        'course_deleted' => '🗑️',
                                                        'attendance_updated' => '✅',
                                                        'profile_updated' => '👤',
                                                        'password_changed' => '🔒',
                                                        'admin_dashboard_access' => '📊'
                                                    ];
                                                    echo $icons[$activite['action']] ?? '📝';
                                                    ?>
                                                    <?php echo ucfirst(str_replace('_', ' ', $activite['action'])); ?>
                                                </td>
                                                <td><?php echo $activite['details'] ?: '-'; ?></td>
                                                <td><?php echo formatDate($activite['created_at']); ?></td>
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
</body>
</html>