<?php
require_once 'includes/config.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* Styles spécifiques à la page d'accueil */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 6rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" opacity="0.1"><polygon fill="white" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, #fff, #e0f7fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.4rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .hero-actions {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-hero-primary {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .btn-hero-outline {
            border: 2px solid white;
            color: white;
            background: transparent;
        }

        .btn-hero-outline:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-3px);
        }

        .features-section {
            padding: 6rem 0;
            background: var(--white);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--secondary-color);
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 4rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .feature-card {
            background: var(--white);
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--light-color);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-lg);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--secondary-color);
        }

        .feature-description {
            color: var(--gray);
            line-height: 1.6;
        }

        .stats-section {
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-dark));
            color: white;
            padding: 4rem 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #fff, #e0f7fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .cta-section {
            padding: 6rem 0;
            background: var(--gray-light);
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--secondary-color);
        }

        .cta-description {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .footer {
            background: var(--secondary-color);
            color: white;
            padding: 3rem 0 2rem;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .footer-link {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .footer-link:hover {
            opacity: 0.8;
        }

        .footer-copyright {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        /* Header pour la page d'accueil */
        .home-header {
            background: transparent;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .home-header .header-content {
            padding: 1.5rem 2rem;
        }

        .home-header .logo {
            font-size: 1.8rem;
        }

        .home-header .nav a {
            color: white;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .hero-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-hero {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header pour la page d'accueil -->
    <header class="header home-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php" style="color: white; text-decoration: none;">
                        🎓 <?php echo APP_NAME; ?>
                    </a>
                </div>
                <nav class="nav">
                    <ul>
                        <li><a href="#features">Fonctionnalités</a></li>
                        <li><a href="#stats">Statistiques</a></li>
                        <li><a href="auth/login.php" class="btn btn-outline" style="border-color: white; color: white;">Se connecter</a></li>
                        <li><a href="auth/register.php" class="btn btn-primary" style="background: white; color: var(--primary-color);">S'inscrire</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Section Hero -->
    <section class="hero-section">
        <div class="hero-content">
            <br>
            <br>
            <br>
            <h1 class="hero-title">Suivi Intelligent de Présence</h1>
            <p class="hero-subtitle">
                Gérez efficacement les présences de vos étudiants avec notre plateforme moderne et intuitive. 
                Simplifiez le suivi, analysez les données et améliorez l'engagement.
            </p>
            <div class="hero-actions">
                <a href="auth/register.php" class="btn-hero btn-hero-primary">
                    🚀 Commencer gratuitement
                </a>
                <a href="#features" class="btn-hero btn-hero-outline">
                    📚 Découvrir les fonctionnalités
                </a>
            </div>
        </div>
    </section>

    <!-- Section Fonctionnalités -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="section-title">Fonctionnalités Principales</h2>
            <p class="section-subtitle">
                Une plateforme complète pour gérer tous les aspects du suivi de présence
            </p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">✅</div>
                    <h3 class="feature-title">Marquage de Présence</h3>
                    <p class="feature-description">
                        Marquez rapidement les présences avec une interface intuitive. 
                        Support pour différents statuts : présent, absent, retard, excusé.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Analytique Avancée</h3>
                    <p class="feature-description">
                        Visualisez les données de présence avec des graphiques interactifs. 
                        Identifiez les tendances et prenez des décisions éclairées.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">👨‍🏫</div>
                    <h3 class="feature-title">Gestion Multi-Rôles</h3>
                    <p class="feature-description">
                        Interfaces dédiées pour administrateurs, enseignants et étudiants. 
                        Chacun accède aux fonctionnalités adaptées à son rôle.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title">Design Responsive</h3>
                    <p class="feature-description">
                        Accédez à la plateforme depuis n'importe quel appareil. 
                        Interface optimisée pour mobile, tablette et desktop.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3 class="feature-title">Sécurité Renforcée</h3>
                    <p class="feature-description">
                        Données protégées avec authentification sécurisée. 
                        Conformité aux standards de protection des données.
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3 class="feature-title">Rapports Automatisés</h3>
                    <p class="feature-description">
                        Générez des rapports détaillés et exportez les données. 
                        Suivez l'évolution des présences dans le temps.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Statistiques -->
    <section id="stats" class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">99.9%</div>
                    <div class="stat-label">Disponibilité</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Utilisateurs</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">95%</div>
                    <div class="stat-label">Satisfaction</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Call to Action -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title">Prêt à révolutionner votre suivi de présence ?</h2>
            <p class="cta-description">
                Rejoignez les établissements qui font confiance à notre plateforme pour une gestion optimale des présences.
            </p>
            <div class="hero-actions">
                <a href="auth/register.php" class="btn-hero btn-hero-primary">
                    📝 Créer mon compte
                </a>
                <a href="auth/login.php" class="btn-hero btn-hero-outline" style="border-color: var(--primary-color); color: var(--primary-color);">
                    🔐 Me connecter
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="#features" class="footer-link">Fonctionnalités</a>
                <a href="#stats" class="footer-link">Statistiques</a>
                <a href="auth/login.php" class="footer-link">Connexion</a>
                <a href="auth/register.php" class="footer-link">Inscription</a>
            </div>
            <div class="footer-copyright">
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Tous droits réservés.
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>