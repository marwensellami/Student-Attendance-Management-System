<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isLoggedIn();
$userRole = getUserRole();
?>

<header class="header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="<?php echo $isLoggedIn ? "$userRole/dashboard.php" : "../index.php"; ?>" 
                   style="color: white; text-decoration: none;">
                    <?php echo APP_NAME; ?>
                </a>
            </div>
            
            <?php if ($isLoggedIn): ?>
                <nav class="nav">
                    <ul>
                        <?php if ($userRole === ROLE_ADMIN): ?>
                            <li><a href="dashboard.php"><h2>📊 Tableau de Bord</h2></a></li>
                            <li><a href="gestion_utilisateurs.php"><h2>👥 Utilisateurs</h2></a></li>
                            <li><a href="gestion_cours.php"><h2>📚 Cours</h2></a></li>
                            <li><a href="gestion_inscriptions.php"><h2>🎓 Inscriptions</h2></a></li> <!-- NOUVEAU -->
                            <li><a href="suivi_presence.php"><h2>✅ Présences</h2></a></li>
                            <li><a href="statistiques.php"><h2>📈 Statistiques</h2></a></li>

                        <?php elseif ($userRole === ROLE_ENSEIGNANT): ?>
                            <li><a href="dashboard.php"><h2>📊 Tableau de Bord</h2></a></li>
                            <li><a href="gestion_cours.php"><h2>📚 Mes Cours</h2></a></li>
                            <li><a href="gestion_etudiants.php"><h2>👥 Gérer les étudiants</h2></a></li> <!-- NOUVEAU -->
                            <li><a href="suivi_etudiants.php"><h2>✅ Suivi Présences</h2></a></li>
                            <li><a href="rapports.php"><h2>📈 Rapports</h2></a></li>

                        <?php elseif ($userRole === ROLE_ETUDIANT): ?>
                            <li><a href="dashboard.php"><h2>📊 Tableau de Bord</h2></a></li>
                            <li><a href="cours.php"><h2>📚 Mes Cours</h2></a></li>
                            <li><a href="presence.php"><h2>✅ Mes Présences</h2></a></li>
                        <?php endif; ?>

                        <li>
                            <a href="profile.php" class="user-menu">
                                👤 <?php echo htmlspecialchars($_SESSION['user_prenom']); ?>
                                <span class="badge badge-light" style="margin-left: 5px;">
                                    <?php echo ucfirst($userRole); ?>
                                </span>
                            </a>
                        </li>
                        <li><a href="../auth/logout.php"><h2>🚪 Déconnexion</h2></a></li>
                    </ul>
                </nav>

            <?php else: ?>
                <nav class="nav">
                    <ul>
                        <li><a href="../auth/login.php"><h2>🔐 Connexion</h2></a></li>
                        <li><a href="../auth/register.php"><h2>📝 Inscription</h2></a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</header>
<style>/* Header ultra compact */
.header {
    padding: 5px 0;
    min-height: 50px;
}

.logo {
    font-size: 1.3rem;
}

.nav ul {
    gap: 5px;
}

.nav a {
    padding: 4px 8px;
    font-size: 0.85rem;
}

/* Masquer les emojis sur petits écrans */
@media (max-width: 768px) {
    .nav a span:not(.badge) {
        display: none;
    }
    
    .nav a::before {
        content: '';
        display: inline-block;
        width: 16px;
        height: 16px;
        margin-right: 3px;
    }
    
    /* Exemple pour quelques liens */
    .nav a[href*="dashboard"]::before {
        content: "📊";
    }
    
    .nav a[href*="logout"]::before {
        content: "🚪";
    }
}</style>