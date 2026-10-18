<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Journaliser la déconnexion
if (isset($_SESSION['user_id'])) {
    logAction($_SESSION['user_id'], 'logout', 'Déconnexion de l\'utilisateur');
}

// Déconnexion de l'utilisateur
logoutUser();

// Redirection vers la page de connexion avec un message
header('Location: login.php?logout=1');
exit;