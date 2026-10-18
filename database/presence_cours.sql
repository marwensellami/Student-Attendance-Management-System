-- Création de la base de données
CREATE DATABASE IF NOT EXISTS presence_cours CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE presence_cours;

-- Table des utilisateurs
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'enseignant', 'etudiant') NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    telephone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Table des cours
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    enseignant_id INT NOT NULL,
    date_debut DATE,
    date_fin DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enseignant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des séances
CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    date_session DATETIME NOT NULL,
    duree_minutes INT DEFAULT 60,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Table des inscriptions
CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif', 'inactif') DEFAULT 'actif',
    UNIQUE KEY unique_enrollment (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Table des présences
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    statut ENUM('present', 'absent', 'retard', 'excusé') DEFAULT 'absent',
    date_marquage TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    remarque TEXT,
    marked_by INT,
    UNIQUE KEY unique_attendance (session_id, student_id),
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table de réinitialisation des mots de passe
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (email) REFERENCES users(email) ON DELETE CASCADE
);

-- Table des logs d'activité (à ajouter)
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insertion des données de test

-- Administrateur par défaut
INSERT INTO users (username, email, password, role, nom, prenom) VALUES 
('admin', 'admin@presence-cours.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'System');

-- Enseignants de test
INSERT INTO users (username, email, password, role, nom, prenom) VALUES 
('prof.dupont', 'dupont@universite.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 'Dupont', 'Pierre'),
('prof.martin', 'martin@universite.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'enseignant', 'Martin', 'Sophie');

-- Étudiants de test
INSERT INTO users (username, email, password, role, nom, prenom) VALUES 
('etudiant.leroy', 'leroy@etudiant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Leroy', 'Marie'),
('etudiant.bernard', 'bernard@etudiant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Bernard', 'Jean'),
('etudiant.petit', 'petit@etudiant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'etudiant', 'Petit', 'Lucie');

-- Cours de test
INSERT INTO courses (code, titre, description, enseignant_id, date_debut, date_fin) VALUES 
('DEV101', 'Développement Web Dynamique', 'Cours sur PHP, MySQL, JavaScript et frameworks modernes', 2, '2024-01-15', '2024-06-15'),
('BDD201', 'Bases de Données Avancées', 'MySQL, optimisation, modélisation de données', 3, '2024-02-01', '2024-07-01');

-- Séances de test
INSERT INTO sessions (course_id, titre, description, date_session, duree_minutes) VALUES 
(1, 'Introduction à PHP', 'Les bases du langage PHP', '2024-01-15 09:00:00', 120),
(1, 'Programmation Orientée Objet', 'Classes, objets et héritage en PHP', '2024-01-22 09:00:00', 120),
(2, 'Modélisation des données', 'Merise et UML', '2024-02-01 14:00:00', 90);

-- Inscriptions des étudiants
INSERT INTO enrollments (student_id, course_id) VALUES 
(4, 1), (5, 1), (6, 1),  -- Tous les étudiants au cours DEV101
(4, 2), (5, 2);          -- Seulement 2 étudiants au cours BDD201

-- Présences de test
INSERT INTO attendance (session_id, student_id, statut, marked_by) VALUES 
(1, 4, 'present', 2), (1, 5, 'present', 2), (1, 6, 'retard', 2),
(2, 4, 'present', 2), (2, 5, 'absent', 2), (2, 6, 'present', 2),
(3, 4, 'present', 3), (3, 5, 'excusé', 3);

-- Création des index pour optimiser les performances
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_courses_enseignant ON courses(enseignant_id);
CREATE INDEX idx_sessions_course_date ON sessions(course_id, date_session);
CREATE INDEX idx_attendance_session_student ON attendance(session_id, student_id);
CREATE INDEX idx_enrollments_student_course ON enrollments(student_id, course_id);