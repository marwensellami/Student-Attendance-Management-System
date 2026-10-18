<?php
// Démarrage sécurisé des sessions
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Régénération périodique de l'ID de session pour la sécurité
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Vérification du timeout de session
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Fonctions de gestion des sessions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function requireAuth($requiredRole = null) {
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }
    
    if ($requiredRole && getUserRole() !== $requiredRole) {
        header('Location: ../auth/access_denied.php');
        exit;
    }
}

function hasPermission($requiredRole) {
    $userRole = getUserRole();
    $rolesHierarchy = [ROLE_ETUDIANT, ROLE_ENSEIGNANT, ROLE_ADMIN];
    
    $userLevel = array_search($userRole, $rolesHierarchy);
    $requiredLevel = array_search($requiredRole, $rolesHierarchy);
    
    return $userLevel !== false && $userLevel >= $requiredLevel;
}

// Fonction pour sécuriser les données de session
function secureSessionData($data) {
    if (is_array($data)) {
        return array_map('secureSessionData', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>