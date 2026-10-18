<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Redirection si déjà connecté
if (isLoggedIn()) {
    redirectToDashboard();
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    }
    // Validation des champs
    elseif (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    }
    elseif (!isValidEmail($email)) {
        $error = "Adresse email invalide.";
    }
    else {
        // Vérification des identifiants
        $user = verifyCredentials($email, $password);
        
        if ($user) {
            // Connexion réussie
            loginUser($user);
            
            // Message de bienvenue
            $_SESSION['success_message'] = "Bienvenue " . $user['prenom'] . " " . $user['nom'] . " !";
            
            // Redirection vers le dashboard approprié
            redirectToDashboard();
        } else {
            $error = "Identifiants incorrects ou compte inactif.";
        }
    }
}

// Génération du token CSRF
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 20px;
        }
        
        .login-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--gray);
            font-size: 1.1rem;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--light-color);
        }
        
        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .demo-accounts {
            margin-top: 2rem;
            padding: 1rem;
            background-color: var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }
        
        .demo-accounts h4 {
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }
        
        .demo-account {
            margin: 0.25rem 0;
            padding: 0.5rem;
            background: var(--white);
            border-radius: 4px;
            border-left: 3px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo"><?php echo APP_NAME; ?></div>
                <div class="login-subtitle">Connectez-vous à votre compte</div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert alert-warning">
                    Votre session a expiré. Veuillez vous reconnecter.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">
                    Inscription réussie ! Vous pouvez maintenant vous connecter.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-info">
                    Vous avez été déconnecté avec succès.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="email" class="form-label">Adresse Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo $_POST['email'] ?? ''; ?>" 
                           required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                </div>
            </form>
            
            <div class="form-footer">
                <a href="reset_password.php">Mot de passe oublié ?</a>
                <br>
                <span>Nouveau sur la plateforme ? <a href="register.php">Créer un compte</a></span>
            </div>
            

        </div>
    </div>
    
    <script>
        // Focus sur le champ email au chargement
        document.getElementById('email').focus();
        
        // Afficher/masquer le mot de passe (optionnel)
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const togglePassword = document.createElement('button');
            togglePassword.type = 'button';
            togglePassword.innerHTML = '👁️';
            togglePassword.style.background = 'none';
            togglePassword.style.border = 'none';
            togglePassword.style.position = 'absolute';
            togglePassword.style.right = '10px';
            togglePassword.style.top = '50%';
            togglePassword.style.transform = 'translateY(-50%)';
            togglePassword.style.cursor = 'pointer';
            
            const passwordGroup = passwordInput.parentElement;
            passwordGroup.style.position = 'relative';
            passwordGroup.appendChild(togglePassword);
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
            });
        });
    </script>
</body>
</html>