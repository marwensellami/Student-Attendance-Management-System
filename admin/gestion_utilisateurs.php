<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un administrateur
requireAuth(ROLE_ADMIN);

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Traitement des différentes actions
try {
    global $pdo;
    
    // AJOUT d'utilisateur
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
        $nom = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitize($_POST['role'] ?? 'etudiant');
        $date_naissance = sanitize($_POST['date_naissance'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        
        // Validation
        if (empty($nom) || empty($prenom) || empty($email) || empty($username) || empty($password)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!isValidEmail($email)) {
            $error = "Adresse email invalide.";
        } elseif (!isValidPassword($password)) {
            $error = "Le mot de passe doit contenir au moins 8 caractères.";
        } else {
            // Vérifier si l'email ou username existe déjà
            $sql = "SELECT id FROM users WHERE email = ? OR username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $username]);
            
            if ($stmt->fetch()) {
                $error = "Un utilisateur avec cet email ou nom d'utilisateur existe déjà.";
            } else {
                // Hash du mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insertion
                $sql = "INSERT INTO users (nom, prenom, email, username, password, role, date_naissance, telephone, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prenom, $email, $username, $hashedPassword, $role, $date_naissance, $telephone]);
                
                $user_id = $pdo->lastInsertId();
                logAction($_SESSION['user_id'], 'user_created', "Création de l'utilisateur ID: $user_id");
                
                $success = "Utilisateur créé avec succès !";
                $action = 'list'; // Revenir à la liste
            }
        }
    }
    
    // MODIFICATION d'utilisateur
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
        $user_id = $_POST['user_id'] ?? '';
        $nom = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $date_naissance = sanitize($_POST['date_naissance'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($user_id)) {
            $error = "ID utilisateur manquant.";
        } else {
            // Vérifier les conflits d'email/username
            $sql = "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $username, $user_id]);
            
            if ($stmt->fetch()) {
                $error = "Un autre utilisateur utilise déjà cet email ou nom d'utilisateur.";
            } else {
                // Mise à jour
                $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, username = ?, role = ?, 
                        date_naissance = ?, telephone = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nom, $prenom, $email, $username, $role, $date_naissance, $telephone, $is_active, $user_id]);
                
                logAction($_SESSION['user_id'], 'user_updated', "Modification de l'utilisateur ID: $user_id");
                $success = "Utilisateur modifié avec succès !";
                $action = 'list';
            }
        }
    }
    
    // SUPPRESSION d'utilisateur
    if ($action === 'delete' && isset($_GET['id'])) {
        $user_id = $_GET['id'];
        
        // Empêcher l'auto-suppression
        if ($user_id == $_SESSION['user_id']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            
            logAction($_SESSION['user_id'], 'user_deleted', "Suppression de l'utilisateur ID: $user_id");
            $success = "Utilisateur supprimé avec succès !";
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur gestion utilisateurs: " . $e->getMessage());
    $error = "Une erreur est survenue lors de l'opération.";
}

// Récupérer les données pour l'affichage
$users = [];
$user = null;

if ($action === 'list') {
    $sql = "SELECT * FROM users ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
} elseif (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_GET['id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Utilisateur non trouvé.";
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Utilisateurs - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>
                <?php 
                switch ($action) {
                    case 'add': echo 'Ajouter un Utilisateur'; break;
                    case 'edit': echo 'Modifier l\'Utilisateur'; break;
                    case 'view': echo 'Détails de l\'Utilisateur'; break;
                    default: echo 'Gestion des Utilisateurs';
                }
                ?>
            </h1>
            <div class="header-actions">
                <?php if ($action !== 'list'): ?>
                    <a href="gestion_utilisateurs.php" class="btn btn-outline">← Retour à la liste</a>
                <?php endif; ?>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">➕ Ajouter Utilisateur</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- LISTE des utilisateurs -->
        <?php if ($action === 'list'): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Liste des Utilisateurs (<?php echo count($users); ?>)</h3>
                    <div class="filters">
                        <input type="text" id="searchUsers" placeholder="Rechercher..." class="form-control">
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($users)): ?>
                        <div class="table-responsive">
                            <table class="table" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Statut</th>
                                        <th>Date Création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $u['prenom'] . ' ' . $u['nom']; ?></strong>
                                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge badge-info">Vous</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $u['email']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $u['role'] === 'admin' ? 'danger' : 
                                                         ($u['role'] === 'enseignant' ? 'warning' : 'success'); 
                                                ?>">
                                                    <?php echo ucfirst($u['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $u['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $u['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($u['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?action=view&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline" title="Voir">
                                                        👁️
                                                    </a>
                                                    <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline" title="Modifier">
                                                        ✏️
                                                    </a>
                                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                        <a href="?action=delete&id=<?php echo $u['id']; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           title="Supprimer"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                                            🗑️
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">Aucun utilisateur trouvé.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- FORMULAIRE Ajout/Modification -->
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $user['id'] : ''; ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Nom *</label>
                                    <input type="text" name="nom" class="form-control" 
                                           value="<?php echo $user['nom'] ?? ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Prénom *</label>
                                    <input type="text" name="prenom" class="form-control" 
                                           value="<?php echo $user['prenom'] ?? ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo $user['email'] ?? ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Nom d'utilisateur *</label>
                                    <input type="text" name="username" class="form-control" 
                                           value="<?php echo $user['username'] ?? ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($action === 'add'): ?>
                            <div class="form-group">
                                <label class="form-label">Mot de passe *</label>
                                <input type="password" name="password" class="form-control" 
                                       minlength="8" required>
                                <small>Le mot de passe doit contenir au moins 8 caractères.</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Rôle *</label>
                                    <select name="role" class="form-select" required>
                                        <option value="etudiant" <?php echo ($user['role'] ?? '') === 'etudiant' ? 'selected' : ''; ?>>Étudiant</option>
                                        <option value="enseignant" <?php echo ($user['role'] ?? '') === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Date de Naissance</label>
                                    <input type="date" name="date_naissance" class="form-control" 
                                           value="<?php echo $user['date_naissance'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" name="telephone" class="form-control" 
                                           value="<?php echo $user['telephone'] ?? ''; ?>">
                                </div>
                            </div>
                            <?php if ($action === 'edit'): ?>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Statut</label>
                                        <div class="checkbox-group">
                                            <input type="checkbox" name="is_active" id="is_active" 
                                                   <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                            <label for="is_active">Compte actif</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'Créer l\'utilisateur' : 'Modifier l\'utilisateur'; ?>
                            </button>
                            <a href="gestion_utilisateurs.php" class="btn btn-outline">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- VUE détaillée -->
        <?php if ($action === 'view' && $user): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Détails de l'Utilisateur</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h4>Informations Personnelles</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Nom Complet</th>
                                    <td><?php echo $user['prenom'] . ' ' . $user['nom']; ?></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td><?php echo $user['email']; ?></td>
                                </tr>
                                <tr>
                                    <th>Nom d'utilisateur</th>
                                    <td><?php echo $user['username']; ?></td>
                                </tr>
                                <tr>
                                    <th>Rôle</th>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $user['role'] === 'admin' ? 'danger' : 
                                                 ($user['role'] === 'enseignant' ? 'warning' : 'success'); 
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-6">
                            <h4>Informations Complémentaires</h4>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Date de Naissance</th>
                                    <td><?php echo $user['date_naissance'] ? formatDate($user['date_naissance'], 'd/m/Y') : 'Non renseignée'; ?></td>
                                </tr>
                                <tr>
                                    <th>Téléphone</th>
                                    <td><?php echo $user['telephone'] ?: 'Non renseigné'; ?></td>
                                </tr>
                                <tr>
                                    <th>Statut</th>
                                    <td>
                                        <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Date de Création</th>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                </tr>
                                <tr>
                                    <th>Dernière Mise à Jour</th>
                                    <td><?php echo formatDate($user['updated_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-primary">Modifier</a>
                        <a href="gestion_utilisateurs.php" class="btn btn-outline">Retour à la liste</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Recherche en temps réel
        document.getElementById('searchUsers').addEventListener('input', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html>