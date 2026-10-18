<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est un enseignant
requireAuth(ROLE_ENSEIGNANT);

$enseignant_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$error = '';
$success = '';

// Traitement de la reconnaissance faciale via sélection de fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['selected_photo'])) {
    $student_id_to_mark = $_POST['student_id'] ?? '';
    $session_id_mark = $_POST['session_id'] ?? '';
    
    error_log("🎯 Début marquage présence - Étudiant: $student_id_to_mark, Session: $session_id_mark");
    
    if (!$student_id_to_mark || !$session_id_mark) {
        $error = "Données manquantes pour le marquage de présence.";
        error_log("❌ Données manquantes");
    } elseif ($_FILES['selected_photo']['error'] !== UPLOAD_ERR_OK) {
        $error = "Erreur lors du téléchargement de la photo. Code: " . $_FILES['selected_photo']['error'];
        error_log("❌ Erreur upload: " . $_FILES['selected_photo']['error']);
    } else {
        try {
            // Vérifier que l'enseignant a accès à cette session
            $sql = "SELECT s.* FROM sessions s 
                    JOIN courses c ON s.course_id = c.id 
                    WHERE s.id = ? AND c.enseignant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$session_id_mark, $enseignant_id]);
            $session = $stmt->fetch();
            
            if (!$session) {
                $error = "Session non trouvée ou accès non autorisé.";
                error_log("❌ Session non trouvée: $session_id_mark");
            } else {
                // Vérifier que l'étudiant est inscrit au cours
                $sql = "SELECT u.*, e.course_id 
                        FROM users u 
                        JOIN enrollments e ON u.id = e.student_id 
                        JOIN sessions s ON e.course_id = s.course_id 
                        WHERE u.id = ? AND s.id = ? AND u.role = 'etudiant'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$student_id_to_mark, $session_id_mark]);
                $student = $stmt->fetch();
                
                if (!$student) {
                    $error = "Étudiant non trouvé ou non inscrit à ce cours.";
                    error_log("❌ Étudiant non trouvé: $student_id_to_mark");
                } else {
                    // Créer le dossier temporaire pour les photos uploadées
                    $uploadDir = __DIR__ . '/../assets/temp_photos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Sauvegarder la photo sélectionnée
                    $extension = pathinfo($_FILES['selected_photo']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('verif_') . '.' . $extension;
                    $probe_path = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['selected_photo']['tmp_name'], $probe_path)) {
                        error_log("✅ Photo uploadée: $probe_path");
                        
                        // Chemin vers le script Python
                        $pythonPath = "C:\\Users\\msi\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";
                        $scriptPath = __DIR__ . "/../check_face.py";
                        $command = "\"$pythonPath\" \"$scriptPath\" \"$probe_path\"";
                        
                        error_log("🚀 Exécution commande: $command");
                        
                        // Exécuter le script Python
                        $output = shell_exec($command);
                        
                        error_log("📄 Output Python brut: " . $output);
                        
                        if ($output === null) {
                            $error = "Erreur lors de l'exécution du script de reconnaissance faciale.";
                            error_log("❌ Script Python non exécuté");
                        } else {
                            $result = json_decode(trim($output), true);
                            error_log("🔍 Résultat décodé: " . print_r($result, true));
                            
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                $error = "Erreur de décodage JSON: " . json_last_error_msg();
                                error_log("❌ Erreur JSON: " . json_last_error_msg());
                            } elseif ($result && isset($result['status'])) {
                                if ($result['status'] === 'found') {
                                    // Vérifier que la correspondance est avec le bon étudiant
                                    if ($result['student_id'] == $student_id_to_mark) {
                                        $confidence = round(floatval($result['confidence']) * 100, 2);
                                        error_log("✅ Correspondance confirmée - Confiance: $confidence%");
                                        
                                        if ($confidence >= 60) {
    try {
        // Marquer la présence
        $sql = "SELECT id FROM attendance WHERE session_id = ? AND student_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$session_id_mark, $student_id_to_mark]);
        
        if ($stmt->fetch()) {
            // Mise à jour - Vérifier si les colonnes existent
            $sql = "SHOW COLUMNS FROM attendance LIKE 'methode_verification'";
            $check_stmt = $pdo->query($sql);
            $has_extra_columns = $check_stmt->fetch();
            
            if ($has_extra_columns) {
                // Avec les colonnes supplémentaires
                $sql = "UPDATE attendance SET statut = 'present', date_marquage = NOW(), 
                        marked_by = ?, methode_verification = 'reconnaissance_faciale',
                        confidence_score = ? 
                        WHERE session_id = ? AND student_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enseignant_id, $confidence, $session_id_mark, $student_id_to_mark]);
            } else {
                // Sans les colonnes supplémentaires
                $sql = "UPDATE attendance SET statut = 'present', date_marquage = NOW(), 
                        marked_by = ?
                        WHERE session_id = ? AND student_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enseignant_id, $session_id_mark, $student_id_to_mark]);
            }
            error_log("✅ Présence mise à jour");
        } else {
            // Insertion - Vérifier si les colonnes existent
            $sql = "SHOW COLUMNS FROM attendance LIKE 'methode_verification'";
            $check_stmt = $pdo->query($sql);
            $has_extra_columns = $check_stmt->fetch();
            
            if ($has_extra_columns) {
                // Avec les colonnes supplémentaires
                $sql = "INSERT INTO attendance (session_id, student_id, statut, marked_by, 
                        methode_verification, confidence_score) 
                        VALUES (?, ?, 'present', ?, 'reconnaissance_faciale', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$session_id_mark, $student_id_to_mark, $enseignant_id, $confidence]);
            } else {
                // Sans les colonnes supplémentaires
                $sql = "INSERT INTO attendance (session_id, student_id, statut, marked_by) 
                        VALUES (?, ?, 'present', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$session_id_mark, $student_id_to_mark, $enseignant_id]);
            }
            error_log("✅ Présence insérée");
        }
        
        logAction($_SESSION['user_id'], 'attendance_marked_face', 
                 "Présence marquée via reconnaissance faciale pour étudiant ID: $student_id_to_mark - Confiance: $confidence%");
        $success = "✅ Présence marquée avec succès ! Confiance: " . $confidence . "%";
        
    } catch (PDOException $e) {
        error_log("💥 Erreur DB lors du marquage: " . $e->getMessage());
        $error = "Erreur lors de l'enregistrement dans la base de données.";
    }
} else {
                                            $error = "❌ Correspondance trop faible (".$confidence."%). Veuillez réessayer.";
                                            error_log("❌ Confiance insuffisante: $confidence%");
                                        }
                                    } else {
                                        $error = "❌ Photo ne correspond pas à l'étudiant sélectionné. La photo correspond à: " . $result['student_name'];
                                        error_log("❌ Mauvais étudiant - Attendu: $student_id_to_mark, Trouvé: " . $result['student_id']);
                                    }
                                } elseif ($result['status'] === 'not_found') {
                                    $error = "❌ Aucune correspondance trouvée dans la base de données.";
                                    error_log("❌ Aucune correspondance: " . ($result['msg'] ?? ''));
                                } else {
                                    $error = "❌ Erreur reconnaissance faciale: " . ($result['msg'] ?? 'Erreur inconnue');
                                    error_log("❌ Erreur reconnaissance: " . ($result['msg'] ?? 'Erreur inconnue'));
                                }
                            } else {
                                $error = "❌ Format de réponse invalide du script de reconnaissance.";
                                error_log("❌ Format réponse invalide");
                            }
                        }
                        
                        // Supprimer la photo temporaire
                        if (file_exists($probe_path)) {
                            unlink($probe_path);
                            error_log("🧹 Photo temporaire supprimée");
                        }
                    } else {
                        $error = "Erreur lors du sauvegarde de la photo.";
                        error_log("❌ Erreur sauvegarde photo");
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("💥 Erreur DB marquage présence: " . $e->getMessage());
            $error = "Une erreur de base de données est survenue lors du marquage de la présence.";
        } catch (Exception $e) {
            error_log("💥 Erreur générale marquage présence: " . $e->getMessage());
            $error = "Une erreur inattendue est survenue lors du marquage de la présence.";
        }
    }
}

// Marquer comme absent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_absent'])) {
    $student_id_absent = $_POST['student_id'] ?? '';
    $session_id_absent = $_POST['session_id'] ?? '';
    
    if ($student_id_absent && $session_id_absent) {
        try {
            $sql = "SELECT id FROM attendance WHERE session_id = ? AND student_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$session_id_absent, $student_id_absent]);
            
            if ($stmt->fetch()) {
                // Mise à jour
                $sql = "UPDATE attendance SET statut = 'absent', date_marquage = NOW(), marked_by = ? 
                        WHERE session_id = ? AND student_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$enseignant_id, $session_id_absent, $student_id_absent]);
            } else {
                // Insertion
                $sql = "INSERT INTO attendance (session_id, student_id, statut, marked_by) 
                        VALUES (?, ?, 'absent', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$session_id_absent, $student_id_absent, $enseignant_id]);
            }
            
            logAction($_SESSION['user_id'], 'attendance_marked_absent', "Absence marquée pour étudiant ID: $student_id_absent");
            $success = "Absence marquée avec succès.";
            
        } catch (PDOException $e) {
            error_log("Erreur marquage absence: " . $e->getMessage());
            $error = "Erreur lors du marquage de l'absence.";
        }
    }
}

try {
    global $pdo;
    
    // Récupérer les cours de l'enseignant
    $sql = "SELECT c.* 
            FROM courses c 
            WHERE c.enseignant_id = ?
            ORDER BY c.titre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$enseignant_id]);
    $courses = $stmt->fetchAll();
    
    // Récupérer les séances si un cours est sélectionné
    $sessions = [];
    if ($course_id) {
        $sql = "SELECT s.* 
                FROM sessions s 
                WHERE s.course_id = ? 
                ORDER BY s.date_session DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id]);
        $sessions = $stmt->fetchAll();
    }
    
    // Récupérer les étudiants inscrits au cours
    $students = [];
    if ($course_id) {
        $sql = "SELECT u.id, u.nom, u.prenom, u.email, u.photo_path,
                (SELECT COUNT(*) FROM attendance a 
                 JOIN sessions s ON a.session_id = s.id 
                 WHERE s.course_id = ? AND a.student_id = u.id AND a.statut = 'present') as nb_presences,
                (SELECT COUNT(*) FROM sessions WHERE course_id = ?) as total_sessions
                FROM users u
                JOIN enrollments e ON u.id = e.student_id
                WHERE e.course_id = ? AND u.is_active = 1
                ORDER BY u.nom, u.prenom";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$course_id, $course_id, $course_id]);
        $students = $stmt->fetchAll();
        
        // Calculer les taux de présence pour chaque étudiant
        foreach ($students as &$student) {
            $student['taux_presence'] = calculatePercentage($student['nb_presences'], $student['total_sessions']);
        }
    }
    
    // Récupérer les présences pour une séance spécifique
    $attendances = [];
    $session_info = null;
    if ($session_id) {
        // Informations de la séance
        $sql = "SELECT s.*, c.titre as course_titre, c.code as course_code
                FROM sessions s 
                JOIN courses c ON s.course_id = c.id 
                WHERE s.id = ? AND c.enseignant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$session_id, $enseignant_id]);
        $session_info = $stmt->fetch();
    }
    
    // Historique d'un étudiant spécifique
    $student_history = [];
    $student_info = null;
    if ($student_id) {
        // Informations de l'étudiant
        $sql = "SELECT u.* FROM users u WHERE u.id = ? AND u.role = 'etudiant'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_id]);
        $student_info = $stmt->fetch();
    }
    
} catch (PDOException $e) {
    error_log("Erreur suivi étudiants: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Étudiants - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .photo-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--light-color);
        }
        
        .photo-selection-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .photo-selection-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        
        .attendance-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .status-present { background: var(--success-color); color: white; }
        .status-absent { background: var(--danger-color); color: white; }
        .status-retard { background: var(--warning-color); color: white; }
        .status-excuse { background: var(--info-color); color: white; }
        
        .photo-instructions {
            background: var(--light-color);
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            text-align: left;
        }
        
        .photo-upload-area {
            border: 2px dashed var(--primary-color);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 1rem 0;
            background: rgba(var(--primary-color-rgb), 0.05);
        }
        
        .photo-upload-area:hover {
            background: rgba(var(--primary-color-rgb), 0.1);
            border-color: var(--secondary-color);
        }
        
        .photo-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .btn-browse {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin: 0.5rem;
        }
        
        .btn-browse:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Suivi des Étudiants</h1>
            <p>Marquez les présences avec la reconnaissance faciale</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Sélection du cours -->
        <div class="card">
            <div class="card-header">
                <h3>Sélection du Cours</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Sélectionner un cours</label>
                            <select name="course_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Choisir un cours</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                        <?php echo $course_id == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo $c['code'] . ' - ' . $c['titre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <?php if ($course_id): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Sélectionner une séance</label>
                                <select name="session_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Voir tous les étudiants</option>
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" 
                                            <?php echo $session_id == $s['id'] ? 'selected' : ''; ?>>
                                            <?php echo formatDate($s['date_session'], 'd/m/Y H:i') . ' - ' . $s['titre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Marquage des présences avec reconnaissance faciale -->
        <?php if ($session_id && $session_info && !empty($students)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>📸 Marquage des Présences - Reconnaissance Faciale</h3>
                    <div>
                        <span class="badge badge-info"><?php echo $session_info['course_code']; ?></span>
                        <span class="badge badge-secondary"><?php echo formatDate($session_info['date_session']); ?></span>
                    </div>
                </div>
                <div class="card-body">

                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Étudiant</th>
                                    <th>Email</th>
                                    <th>Statut actuel</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): 
                                    // Récupérer le statut actuel pour cette séance
                                    $sql = "SELECT statut FROM attendance WHERE session_id = ? AND student_id = ?";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute([$session_id, $student['id']]);
                                    $current_attendance = $stmt->fetch();
                                    $current_status = $current_attendance ? $current_attendance['statut'] : 'non_marque';
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($student['photo_path']): ?>
                                                <img src="../<?php echo $student['photo_path']; ?>" 
                                                     alt="Photo de <?php echo $student['prenom']; ?>" 
                                                     class="photo-preview">
                                            <?php else: ?>
                                                <div class="photo-preview" style="background: #ccc; display: flex; align-items: center; justify-content: center;">
                                                    📷
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $student['prenom'] . ' ' . $student['nom']; ?></strong>
                                        </td>
                                        <td><?php echo $student['email']; ?></td>
                                        <td>
                                            <?php echo getAttendanceStatusBadge($current_status); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <!-- Bouton sélection de photo -->
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="openPhotoSelection(<?php echo $student['id']; ?>, '<?php echo $student['prenom'] . ' ' . $student['nom']; ?>')"
                                                        <?php echo !$student['photo_path'] ? 'disabled title="L\'étudiant n\'a pas de photo de référence"' : ''; ?>>
                                                    📁 Choisir une photo
                                                </button>
                                                
                                                <!-- Bouton marquer absent -->
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Marquer <?php echo $student['prenom']; ?> comme absent ?')">
                                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                    <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                                    <button type="submit" name="mark_absent" class="btn btn-danger btn-sm">
                                                        ❌ Marquer absent
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Liste des étudiants avec statistiques (sans séance spécifique) -->
        <?php if ($course_id && empty($session_id) && empty($student_id)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Liste des Étudiants</h3>
                    <span class="badge badge-info"><?php echo count($students); ?> étudiants inscrits</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($students)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Étudiant</th>
                                        <th>Email</th>
                                        <th>Présences</th>
                                        
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <?php if ($student['photo_path']): ?>
                                                    <img src="../<?php echo $student['photo_path']; ?>" 
                                                         alt="Photo de <?php echo $student['prenom']; ?>" 
                                                         class="photo-preview">
                                                <?php else: ?>
                                                    <div class="photo-preview" style="background: #ccc; display: flex; align-items: center; justify-content: center;">
                                                        ❌
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo $student['prenom'] . ' ' . $student['nom']; ?></strong>
                                            </td>
                                            <td><?php echo $student['email']; ?></td>
                                            <td>
                                                <?php echo $student['nb_presences']; ?> / <?php echo $student['total_sessions']; ?>
                                            </td>

                                            <td>
                                                <?php 
                                                if ($student['taux_presence'] >= 80) {
                                                    echo '<span class="badge badge-success">Assidu</span>';
                                                } elseif ($student['taux_presence'] >= 60) {
                                                    echo '<span class="badge badge-warning">Régulier</span>';
                                                } else {
                                                    echo '<span class="badge badge-danger">Absentéiste</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="?course_id=<?php echo $course_id; ?>&student_id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-outline">
                                                    📊 Historique
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">Aucun étudiant inscrit à ce cours.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal pour la sélection de photo -->
    <div id="photoSelectionModal" class="photo-selection-modal">
        <div class="photo-selection-content">
            <h3>📁 Sélection de Photo</h3>
            <p id="studentName">Étudiant: <strong></strong></p>
            

            
            <form id="photoSelectionForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="student_id" id="modalStudentId">
                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                
                <div class="photo-upload-area" onclick="document.getElementById('photoInput').click()">
                    <div class="photo-icon">📁</div>
                    <div>Cliquez ici pour sélectionner une photo</div>
                    <div class="field-hint">Formats acceptés: JPG, PNG, GIF</div>
                    <div class="field-hint">Taille max: 5MB</div>
                </div>
                
                <input type="file" id="photoInput" name="selected_photo" accept="image/*" 
                       style="display: none;" onchange="handlePhotoSelection(this)">
                
                <div id="selectedPhotoPreview" style="display: none; margin: 1rem 0;">
                    <img id="previewImage" src="" alt="Aperçu" style="max-width: 200px; max-height: 200px; border-radius: 10px;">
                    <p id="fileName" style="margin-top: 0.5rem; font-weight: bold;"></p>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn-browse" onclick="document.getElementById('photoInput').click()">
                        📁 Ouvrir l'explorateur
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closePhotoSelection()">
                        ❌ Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function openPhotoSelection(studentId, studentName) {
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('studentName').innerHTML = 'Étudiant: <strong>' + studentName + '</strong>';
            document.getElementById('photoSelectionModal').style.display = 'flex';
            // Réinitialiser le formulaire
            document.getElementById('photoInput').value = '';
            document.getElementById('selectedPhotoPreview').style.display = 'none';
        }
        
        function closePhotoSelection() {
            document.getElementById('photoSelectionModal').style.display = 'none';
        }
        
        function handlePhotoSelection(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validation de la taille (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('La photo est trop volumineuse. Taille maximale: 5MB');
                    input.value = '';
                    return;
                }
                
                // Afficher l'aperçu
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('fileName').textContent = file.name;
                    document.getElementById('selectedPhotoPreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
                
                // Soumettre automatiquement le formulaire après un court délai
                setTimeout(() => {
                    document.getElementById('photoSelectionForm').submit();
                    
                    // Afficher un indicateur de chargement
                    const modalContent = document.querySelector('.photo-selection-content');
                    modalContent.innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">⏳</div>
                            <h3>Analyse en cours...</h3>
                            <p>Vérification de la correspondance faciale</p>
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                        </div>
                    `;
                }, 1000);
            }
        }
        
        // Fermer la modal en cliquant à l'extérieur
        document.getElementById('photoSelectionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePhotoSelection();
            }
        });
        
        // Ouvrir l'explorateur au chargement de la modal
        document.getElementById('photoSelectionModal').addEventListener('shown', function() {
            setTimeout(() => {
                document.getElementById('photoInput').click();
            }, 500);
        });
    </script>
</body>
</html>