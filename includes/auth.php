<?php
// Fonctions d'authentification et d'autorisation

/**
 * Vérifie les identifiants de connexion
 */
function verifyCredentials($email, $password) {
    global $pdo;
    
    $sql = "SELECT id, username, email, password, role, nom, prenom, is_active 
            FROM users 
            WHERE email = ? AND is_active = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    
    return false;
}

/**
 * Connecte un utilisateur
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_nom'] = $user['nom'];
    $_SESSION['user_prenom'] = $user['prenom'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['logged_in'] = true;
    
    // Mettre à jour la dernière connexion
    updateLastLogin($user['id']);
}

/**
 * Met à jour la date de dernière connexion
 */
function updateLastLogin($userId) {
    global $pdo;
    
    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
}

/**
 * Déconnecte l'utilisateur
 */
function logoutUser() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Vérifie si l'utilisateur a le rôle requis
 */
function checkRoleAccess($requiredRole) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'];
    $roles = [ROLE_ETUDIANT, ROLE_ENSEIGNANT, ROLE_ADMIN];
    
    $userLevel = array_search($userRole, $roles);
    $requiredLevel = array_search($requiredRole, $roles);
    
    return $userLevel >= $requiredLevel;
}

/**
 * Redirige vers le dashboard approprié selon le rôle
 */
function redirectToDashboard() {
    if (!isset($_SESSION['user_role'])) {
        redirect('../auth/login.php');
    }
    
    switch ($_SESSION['user_role']) {
        case ROLE_ADMIN:
            redirect('../admin/dashboard.php');
            break;
        case ROLE_ENSEIGNANT:
            redirect('../enseignant/dashboard.php');
            break;
        case ROLE_ETUDIANT:
            redirect('../etudiant/dashboard.php');
            break;
        default:
            redirect('../auth/login.php');
    }
}

/**
 * Génère un token de réinitialisation de mot de passe
 */
function generatePasswordResetToken($email) {
    global $pdo;
    
    // Supprimer les anciens tokens
    $sql = "DELETE FROM password_resets WHERE email = ? OR expires_at < NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    
    // Générer un nouveau token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email, $token, $expires]);
    
    return $token;
}

/**
 * Vérifie un token de réinitialisation
 */
function verifyPasswordResetToken($token) {
    global $pdo;
    
    $sql = "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$token]);
    
    return $stmt->fetch();
}

/**
 * Met à jour le mot de passe
 */
function updatePassword($email, $newPassword) {
    global $pdo;
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([$hashedPassword, $email]);
    
    if ($success) {
        // Supprimer le token utilisé
        $sql = "DELETE FROM password_resets WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
    }
    
    return $success;
}
?>