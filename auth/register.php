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

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitize($_POST['nom'] ?? '');
    $prenom = sanitize($_POST['prenom'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'etudiant');
    $date_naissance = sanitize($_POST['date_naissance'] ?? '');
    $telephone = sanitize($_POST['telephone'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validation CSRF
    if (!validateCSRFToken($csrf_token)) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    }
    // Validation des champs obligatoires
    elseif (empty($nom) || empty($prenom) || empty($email) || empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
    // Validation email
    elseif (!isValidEmail($email)) {
        $error = "Adresse email invalide.";
    }
    // Validation mot de passe
    elseif (!isValidPassword($password)) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    }
    // Confirmation mot de passe
    elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    }
    // Validation rôle
    elseif (!in_array($role, ['etudiant', 'enseignant'])) {
        $error = "Rôle invalide.";
    }
    // Validation date de naissance (si fournie)
    elseif (!empty($date_naissance) && !isValidDate($date_naissance)) {
        $error = "Date de naissance invalide.";
    }
    // Validation téléphone (si fourni)
    elseif (!empty($telephone) && !preg_match('/^[0-9]{8}$/', $telephone)) {
    $error = "Numéro de téléphone invalide. Doit contenir exactement 8 chiffres.";
    }
    // Validation photo (obligatoire pour les étudiants)
    elseif ($role === 'etudiant' && (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK)) {
        $error = "Veuillez télécharger votre photo pour la reconnaissance faciale.";
    }
    else {
        try {
            global $pdo;
            
            // Vérifier si l'email existe déjà
            $sql = "SELECT id FROM users WHERE email = ? OR username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $username]);
            
            if ($stmt->fetch()) {
                $error = "Un compte avec cet email ou nom d'utilisateur existe déjà.";
            } else {
                // Traitement de la photo pour les étudiants
                $photo_path = null;
                if ($role === 'etudiant' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../assets/photo_etudiant/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Validation du type de fichier
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    $file_type = mime_content_type($_FILES['photo']['tmp_name']);
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $error = "Type de fichier non autorisé. Utilisez JPEG, PNG ou GIF.";
                    } else {
                        // Génération du nom de fichier unique
                        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                        $filename = uniqid('etudiant_') . '_' . time() . '.' . $extension;
                        $photo_path = 'assets/photo_etudiant/' . $filename;
                        $full_path = __DIR__ . '/../' . $photo_path;
                        
                        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $full_path)) {
                            $error = "Erreur lors du téléchargement de la photo.";
                        }
                    }
                }
                
                if (!$error) {
                    // Hash du mot de passe
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertion du nouvel utilisateur
                    $sql = "INSERT INTO users (nom, prenom, email, username, password, role, date_naissance, telephone, photo_path, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nom, $prenom, $email, $username, $hashedPassword, $role, $date_naissance, $telephone, $photo_path]);
                    
                    $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
                    
                    // Redirection vers la page de connexion
                    header('Location: login.php?registered=1');
                    exit;
                }
            }
            
        } catch (PDOException $e) {
            error_log("Erreur d'inscription: " . $e->getMessage());
            $error = "Une erreur est survenue lors de la création du compte. Veuillez réessayer.";
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
    <title>Inscription - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 20px;
        }
        
        .register-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-lg);
            padding: 2.5rem;
            width: 100%;
            max-width: 600px;
            margin: 2rem 0;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .register-subtitle {
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
            font-weight: 500;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .strength-weak { color: var(--danger-color); }
        .strength-medium { color: var(--warning-color); }
        .strength-strong { color: var(--success-color); }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .field-hint {
            font-size: 0.875rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger-color);
        }

        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--light-color);
            margin: 1rem auto;
            display: none;
        }

        .photo-upload-area {
            border: 2px dashed var(--light-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .photo-upload-area:hover {
            border-color: var(--primary-color);
            background-color: var(--light-color);
        }

        .photo-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: rgba(var(--primary-color-rgb), 0.1);
        }

        .photo-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray);
        }

        .role-dependent {
            display: none;
        }

        @media (max-width: 768px) {
            .register-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="register-logo">
                    <span>🎓</span>
                    <?php echo APP_NAME; ?>
                </div>
                <div class="register-subtitle">Créez votre compte</div>
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
            
            <form method="POST" action="" id="registerForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom" class="form-label required-field">Nom</label>
                        <input type="text" id="nom" name="nom" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" 
                               required
                               placeholder="Votre nom">
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom" class="form-label required-field">Prénom</label>
                        <input type="text" id="prenom" name="prenom" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" 
                               required
                               placeholder="Votre prénom">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label required-field">Adresse Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           required
                           placeholder="exemple@email.com">
                    <div class="field-hint">Nous enverrons un email de confirmation à cette adresse</div>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label required-field">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           required
                           placeholder="Choisissez un nom d'utilisateur unique">
                    <div class="field-hint">Ce nom sera utilisé pour vous identifier sur la plateforme</div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_naissance" class="form-label">Date de naissance</label>
                        <input type="date" id="date_naissance" name="date_naissance" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone" class="form-label">Numéro de téléphone</label>
                        <input type="tel" id="telephone" name="telephone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>"
                               placeholder="+216 __ ___ ___"
                               pattern="[0-9+\-\s()]{10,20}">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="role" class="form-label required-field">Rôle</label>
                    <select id="role" name="role" class="form-select" required onchange="togglePhotoField()">
                        <option value="">Sélectionnez votre rôle</option>
                        <option value="etudiant" <?php echo ($_POST['role'] ?? '') === 'etudiant' ? 'selected' : ''; ?>>Étudiant</option>
                        <option value="enseignant" <?php echo ($_POST['role'] ?? '') === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                    </select>
                    <div class="field-hint">Les enseignants pourront créer et gérer des cours</div>
                </div>
                
                <!-- Section Photo pour les étudiants -->
                <div id="photo-section" class="role-dependent">
                    <div class="form-group">
                        <label class="form-label required-field">Photo pour reconnaissance faciale</label>
                        <div class="photo-upload-area" id="photoUploadArea" onclick="document.getElementById('photo').click()">
                            <div class="photo-icon">📷</div>
                            <div>Cliquez pour sélectionner votre photo</div>
                            <div class="field-hint">Formats acceptés: JPG, PNG, GIF</div>
                            <div class="field-hint">Taille max: 5MB</div>
                        </div>
                        <input type="file" id="photo" name="photo" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                        <img id="photoPreview" class="photo-preview" alt="Aperçu de la photo">
                        <div class="field-hint" style="text-align: center;">
                            Cette photo sera utilisée pour la reconnaissance faciale lors de la prise de présence
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label required-field">Mot de passe</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" 
                               required
                               minlength="8" 
                               oninput="checkPasswordStrength(this.value)"
                               placeholder="Créez un mot de passe sécurisé">
                        <button type="button" class="password-toggle" style="
                            position: absolute;
                            right: 10px;
                            top: 50%;
                            transform: translateY(-50%);
                            background: none;
                            border: none;
                            cursor: pointer;
                            font-size: 1.2rem;
                        " onclick="togglePasswordVisibility('password')">👁️</button>
                    </div>
                    <div id="password-strength" class="password-strength"></div>
                    <div class="field-hint">Le mot de passe doit contenir au moins 8 caractères</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label required-field">Confirmer le mot de passe</label>
                    <div style="position: relative;">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               required
                               minlength="8" 
                               oninput="checkPasswordMatch()"
                               placeholder="Confirmez votre mot de passe">
                        <button type="button" class="password-toggle" style="
                            position: absolute;
                            right: 10px;
                            top: 50%;
                            transform: translateY(-50%);
                            background: none;
                            border: none;
                            cursor: pointer;
                            font-size: 1.2rem;
                        " onclick="togglePasswordVisibility('confirm_password')">👁️</button>
                    </div>
                    <div id="password-match" class="password-strength"></div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary w-100" style="padding: 1rem; font-size: 1.1rem;">
                        🚀 Créer mon compte
                    </button>
                </div>
            </form>
            
            <div class="form-footer">
                <span>Déjà un compte ? <a href="login.php">Se connecter</a></span>
            </div>
        </div>
    </div>
    
    <script>
        function togglePhotoField() {
            const role = document.getElementById('role').value;
            const photoSection = document.getElementById('photo-section');
            const photoInput = document.getElementById('photo');
            
            if (role === 'etudiant') {
                photoSection.style.display = 'block';
                photoInput.required = true;
            } else {
                photoSection.style.display = 'none';
                photoInput.required = false;
                // Réinitialiser la photo si l'utilisateur change de rôle
                document.getElementById('photo').value = '';
                document.getElementById('photoPreview').style.display = 'none';
            }
        }
        
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            const uploadArea = document.getElementById('photoUploadArea');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validation de la taille (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('La photo est trop volumineuse. Taille maximale: 5MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    uploadArea.style.display = 'none';
                }
                
                reader.readAsDataURL(file);
            }
        }
        
        // Gestion du drag and drop
        const uploadArea = document.getElementById('photoUploadArea');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('photo').files = files;
                previewPhoto(document.getElementById('photo'));
            }
        });
        
        function checkPasswordStrength(password) {
            const strengthElement = document.getElementById('password-strength');
            let strength = '';
            let className = '';
            let suggestions = [];
            
            if (password.length === 0) {
                strength = '';
                suggestions = [];
            } else if (password.length < 8) {
                strength = 'Faible';
                className = 'strength-weak';
                suggestions = ['Le mot de passe doit contenir au moins 8 caractères'];
            } else {
                // Vérifier la complexité
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                const complexity = [hasUpperCase, hasLowerCase, hasNumbers, hasSpecial].filter(Boolean).length;
                
                if (password.length < 12) {
                    strength = 'Moyen';
                    className = 'strength-medium';
                    suggestions = ['Essayez d\'utiliser au moins 12 caractères'];
                } else if (complexity >= 3) {
                    strength = 'Fort';
                    className = 'strength-strong';
                    suggestions = ['Excellent ! Votre mot de passe est sécurisé'];
                } else {
                    strength = 'Moyen';
                    className = 'strength-medium';
                    suggestions = ['Ajoutez des majuscules, chiffres ou caractères spéciaux'];
                }
                
                // Suggestions spécifiques
                if (!hasUpperCase) suggestions.push('Ajoutez une lettre majuscule');
                if (!hasLowerCase) suggestions.push('Ajoutez une lettre minuscule');
                if (!hasNumbers) suggestions.push('Ajoutez un chiffre');
                if (!hasSpecial) suggestions.push('Ajoutez un caractère spécial');
            }
            
            strengthElement.textContent = strength;
            strengthElement.className = 'password-strength ' + className;
            
            // Afficher les suggestions si disponibles
            if (suggestions.length > 0 && password.length > 0) {
                strengthElement.innerHTML += '<br><small style="color: var(--gray); font-weight: normal;">' + suggestions[0] + '</small>';
            }
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
        
        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                toggle.textContent = '🙈';
            } else {
                field.type = 'password';
                toggle.textContent = '👁️';
            }
        }
        
        // Validation du formulaire avant soumission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const dateNaissance = document.getElementById('date_naissance').value;
            const role = document.getElementById('role').value;
            const photo = document.getElementById('photo');
            
            // Vérification de la correspondance des mots de passe
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas. Veuillez vérifier votre saisie.');
                document.getElementById('confirm_password').focus();
                return;
            }
            
            // Vérification de la date de naissance (si fournie)
            if (dateNaissance) {
                const birthDate = new Date(dateNaissance);
                const today = new Date();
                const minAgeDate = new Date(today.getFullYear() - 10, today.getMonth(), today.getDate());
                
                if (birthDate > minAgeDate) {
                    e.preventDefault();
                    alert('Vous devez avoir au moins 10 ans pour vous inscrire.');
                    document.getElementById('date_naissance').focus();
                    return;
                }
            }
            
            // Vérification de la photo pour les étudiants
            if (role === 'etudiant' && (!photo.files || photo.files.length === 0)) {
                e.preventDefault();
                alert('Veuillez télécharger votre photo pour la reconnaissance faciale.');
                return;
            }
            
            // Afficher un indicateur de chargement
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '⏳ Création du compte...';
            submitBtn.disabled = true;
        });
        
        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            togglePhotoField(); // Afficher/masquer la section photo selon le rôle
            
            const card = document.querySelector('.register-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>