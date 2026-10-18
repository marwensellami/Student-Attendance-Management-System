<?php
// Fonctions utilitaires générales

/**
 * Redirige vers une URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Génère un token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide un token CSRF
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Formate une date pour l'affichage
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '';
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Calcule le pourcentage
 */
function calculatePercentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 2);
}

/**
 * Vérifie si une date est dans le futur
 */
function isFutureDate($date) {
    return strtotime($date) > time();
}

/**
 * Vérifie si une date est aujourd'hui
 */
function isToday($date) {
    return date('Y-m-d') == date('Y-m-d', strtotime($date));
}

/**
 * Sanitize les données utilisateur
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Génère un code de cours unique
 */
function generateCourseCode($titre) {
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $titre), 0, 3));
    $random = rand(100, 999);
    return $prefix . $random;
}

/**
 * Récupère le statut de présence avec une classe CSS
 */
function getAttendanceStatusBadge($statut) {
    $badges = [
        'present' => '<span class="badge badge-success">Présent</span>',
        'absent' => '<span class="badge badge-danger">Absent</span>',
        'retard' => '<span class="badge badge-warning">Retard</span>',
        'excusé' => '<span class="badge badge-info">Excusé</span>'
    ];
    return $badges[$statut] ?? '<span class="badge badge-secondary">Inconnu</span>';
}

/**
 * Log les actions importantes
 */
function logAction($userId, $action, $details = '') {
    global $pdo;
    
    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $userId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

// Fonctions de validation
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPassword($password) {
    return strlen($password) >= 8;
}

function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>