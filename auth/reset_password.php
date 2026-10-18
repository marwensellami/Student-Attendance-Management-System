<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request'; // request, verify, reset

// Redirection si déjà connecté
if (isLoggedIn() && $step === 'request') {
    redirectToDashboard();
}

// Étape 1: Demande de réinitialisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = sanitize($_POST['email'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    }
    elseif (empty($email) || !isValidEmail($email)) {
        $error = "Veuillez entrer une adresse email valide.";
    }
    else {
        // Vérifier si l'email existe
        global $pdo;
        $sql = "SELECT id, email FROM users WHERE email = ? AND is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Générer le token de réinitialisation
            $token = generatePasswordResetToken($email);
            
            // En production, on enverrait un email ici
            // Pour la démo, on affiche le lien directement
            $reset_link = BASE_URL . "/auth/reset_password.php?step=verify&token=" . $token;
            
            $success = "Un lien de réinitialisation a été généré. ";
            $success .= "<br><strong>Lien de démonstration :</strong> ";
            $success .= "<a href='$reset_link' style='word-break: break-all;'>$reset_link</a>";
            $success .= "<br><small>En production, ce lien serait envoyé par email.</small>";
        } else {
            // Pour la sécurité, on affiche le même message même si l'email n'existe pas
            $success = "Si l'email existe dans notre système, un lien de réinitialisation a été envoyé.";
        }
    }
}

// Étape 2: Vérification du token
if ($step === 'verify') {
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        $error = "Token de réinitialisation manquant.";
        $step = 'request';
    } else {
        $reset_data = verifyPasswordResetToken($token);
        
        if (!$reset_data) {
            $error = "Lien de réinitialisation invalide ou expiré.";
            $step = 'request';
        } else {
            // Stocker l'email en session pour l'étape suivante
            $_SESSION['reset_email'] = $reset_data['email'];
            $_SESSION['reset_token'] = $token;
            $step = 'reset';
        }
    }
}

// Étape 3: Réinitialisation du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    $email = $_SESSION['reset_email'] ?? '';
    $token = $_SESSION['reset_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    }
    elseif (empty($email) || empty($token)) {
        $error = "Session de réinitialisation invalide.";
        $step = 'request';
    }
    elseif (empty($password) || empty($confirm_password)) {
        $error = "Veuillez remplir tous les champs.";
    }
    elseif (!isValidPassword($password)) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    }
    else {
        // Vérifier à nouveau le token
        $reset_data = verifyPasswordResetToken($token);
        
        if (!$reset_data || $reset_data['email'] !== $email) {
            $error = "Lien de réinitialisation invalide ou expiré.";
            $step = 'request';
        } else {
            // Mettre à jour le mot de passe
            if (updatePassword($email, $password)) {
                $success = "Votre mot de passe a été réinitialisé avec succès.";
                
                // Nettoyer la session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_token']);
                
                // Rediriger vers la page de connexion
                header('Location: login.php?password_reset=1');
                exit;
            } else {
                $error = "Une erreur est survenue lors de la réinitialisation. Veuillez réessayer.";
            }
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
    <title>Réinitialisation mot de passe - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 20px;
        }
        
        .reset-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-logo {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .reset-subtitle {
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
        
        .password-strength {
            margin-top: 0.25rem;
            font-size: 0.875rem;
        }
        
        .strength-weak { color: var(--danger-color); }
        .strength-medium { color: var(--warning-color); }
        .strength-strong { color: var(--success-color); }
        
        .demo-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-logo"><?php echo APP_NAME; ?></div>
                <div class="reset-subtitle">
                    <?php if ($step === 'request'): ?>
                        Réinitialisation du mot de passe
                    <?php elseif ($step === 'reset'): ?>
                        Nouveau mot de passe
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Étape 1: Demande de réinitialisation -->
            <?php if ($step === 'request'): ?>
                <div class="demo-info">
                    <strong>Démonstration :</strong> Entrez une adresse email existante pour voir le processus.
                    En production, un email serait envoyé avec le lien de réinitialisation.
                </div>
                
                <form method="POST" action="?step=request">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Adresse Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo $_POST['email'] ?? ''; ?>" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-100">Envoyer le lien de réinitialisation</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Étape 3: Nouveau mot de passe -->
            <?php if ($step === 'reset'): ?>
                <form method="POST" action="?step=reset">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control" required
                               minlength="8" oninput="checkPasswordStrength(this.value)" autofocus>
                        <div id="password-strength" class="password-strength"></div>
                        <small>Le mot de passe doit contenir au moins 8 caractères.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                               minlength="8" oninput="checkPasswordMatch()">
                        <div id="password-match" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-100">Réinitialiser le mot de passe</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="form-footer">
                <a href="login.php">← Retour à la connexion</a>
            </div>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength(password) {
            const strengthElement = document.getElementById('password-strength');
            let strength = '';
            let className = '';
            
            if (password.length === 0) {
                strength = '';
            } else if (password.length < 8) {
                strength = 'Faible';
                className = 'strength-weak';
            } else if (password.length < 12) {
                strength = 'Moyen';
                className = 'strength-medium';
            } else {
                // Vérifier la complexité
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                const complexity = [hasUpperCase, hasLowerCase, hasNumbers, hasSpecial].filter(Boolean).length;
                
                if (complexity >= 3) {
                    strength = 'Fort';
                    className = 'strength-strong';
                } else {
                    strength = 'Moyen';
                    className = 'strength-medium';
                }
            }
            
            strengthElement.textContent = strength;
            strengthElement.className = 'password-strength ' + className;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchElement = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchElement.textContent = '';
            } else if (password === confirmPassword) {
                matchElement.textContent = '✓ Les mots de passe correspondent';
                matchElement.className = 'password-strength strength-strong';
            } else {
                matchElement.textContent = '✗ Les mots de passe ne correspondent pas';
                matchElement.className = 'password-strength strength-weak';
            }
        }
        
        // Focus sur le champ approprié
        <?php if ($step === 'request'): ?>
            document.getElementById('email').focus();
        <?php elseif ($step === 'reset'): ?>
            document.getElementById('password').focus();
        <?php endif; ?>
    </script>
</body>
</html>