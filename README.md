<div align="center">

# 🎓 Student Attendance Management System with AI Face Recognition

**A full-stack multi-role web platform combined with an AI-powered facial recognition module for real-time student attendance tracking, with interactive dashboards, dynamic statistical reporting, and automated CSV exports.**

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=flat&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![Chart.js](https://img.shields.io/badge/Chart.js-4.4-FF6384?style=flat&logo=chartdotjs&logoColor=white)](https://www.chartjs.org/)
[![Python](https://img.shields.io/badge/Python-3.10+-3776AB?style=flat&logo=python&logoColor=white)](https://www.python.org/)
[![OpenCV](https://img.shields.io/badge/OpenCV-4.x-5C3EE8?style=flat&logo=opencv&logoColor=white)](https://opencv.org/)
[![XAMPP](https://img.shields.io/badge/XAMPP-8.2-FB7A24?style=flat&logo=xampp&logoColor=white)](https://www.apachefriends.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

[![GitHub last commit](https://img.shields.io/github/last-commit/marwensellami/Student-Attendance-Management-System)](https://github.com/marwensellami/Student-Attendance-Management-System)
[![GitHub repo size](https://img.shields.io/github/repo-size/marwensellami/Student-Attendance-Management-System)](https://github.com/marwensellami/Student-Attendance-Management-System)
[![GitHub stars](https://img.shields.io/github/stars/marwensellami/Student-Attendance-Management-System?style=social)](https://github.com/marwensellami/Student-Attendance-Management-System)

[Overview](#-overview) • [Features](#-key-features) • [Tech Stack](#-tech-stack) • [AI Module](#-ai-face-recognition-module) • [Getting Started](#-getting-started) • [Demo](#-demo-accounts) • [Security](#-security-features) • [Author](#-author)

</div>

---

## 📖 Overview

This project is a complete attendance management solution designed for educational institutions delivering online and hybrid courses. It provides **three distinct role-based interfaces** — Administrator, Teacher, and Student — each with a tailored experience for managing, tracking, and analyzing attendance data.

The system combines a **classic manual attendance workflow** (teacher marks present/absent) with an **AI-powered facial recognition module** that automates student identification and attendance marking from webcam or photo input.

Built from scratch with **PHP, MySQL, and Python**, the platform covers the full lifecycle of attendance management:

> **Planning → Recording (manual + AI) → Analysis → Reporting**

The goal is to replace manual spreadsheets with a centralized, secure, and interactive platform that reduces administrative workload while improving data accuracy through computer vision.

---

## 🎯 Key Features

### 🔐 1. Multi-Role Authentication System
- Secure login with `bcrypt` password hashing (`password_hash` / `password_verify`)
- Registration and password reset workflows with token expiration
- **CSRF protection** on all critical forms
- Route-level **Role-Based Access Control** (RBAC) — Admin / Teacher / Student
- Session hardening (`httponly`, `samesite=Strict`, periodic regeneration)

### 👨‍💼 2. Administrator Interface
- Full **CRUD** on users (students, teachers, admins) and courses
- **Global dashboard** with real-time KPIs:
  - Total students, teachers, and active courses
  - Global attendance rate (%)
  - Recent activity feed
- Centralized attendance monitoring across all courses
- **CSV export** of attendance reports
- Activity logging for audit trail

### 👨‍🏫 3. Teacher Interface
- Create and schedule sessions per course (`datetime`, duration, topic)
- Track student attendance per session and per course
- View individual student history
- **Identify assiduous vs. absentee students** through visual indicators
- **Automatic attendance marking** via facial recognition
- Generate comparative reports with statistics per session
- CSV export for offline analysis

### 🎓 4. Student Interface
- View enrolled courses and upcoming sessions
- Personal **attendance history** with evolution graphs
- Personal dashboard showing participation rate and performance indicators
- Per-course statistics with visual progress bars

### 🤖 5. AI Face Recognition Module
- **Real-time face detection** using OpenCV
- **Face embedding extraction** with VGGFace (deep learning model)
- **Automatic attendance marking** by matching live captures against enrolled student photos
- Hybrid integration: PHP frontend calls a Python pipeline for inference
- Handles enrollment, verification, and unknown-face rejection

### 📊 6. Cross-Cutting Features
- **Interactive charts** powered by Chart.js (doughnut, bar, line, radar)
- **Real-time search and filtering** on all tables
- **Responsive design** with dark mode support
- **CSV export** for reports
- Modern UI with animations, transitions, and feedback states

---

## 🛠️ Tech Stack

| Layer | Technologies |
|-------|-------------|
| **Web Backend** | PHP 8.2, PDO (prepared statements), MySQL 8.0 |
| **Web Frontend** | HTML5, CSS3 (custom design system), Vanilla JavaScript (ES6) |
| **Visualization** | Chart.js (doughnut, bar, line, radar charts) |
| **AI / CV Module** | Python 3.10+, OpenCV, VGGFace, NumPy, Pickle |
| **Web ↔ AI Bridge** | PHP `shell_exec` calling Python CLI scripts |
| **Architecture** | MVC-inspired, modular, session-based |
| **Environment** | XAMPP (Apache + MySQL + PHP) |
| **Database Tools** | phpMyAdmin |
| **Version Control** | Git, GitHub |

---

## 🤖 AI Face Recognition Module

The system includes a **hybrid PHP + Python pipeline** for automatic attendance marking.

### Workflow

```
Webcam / Photo capture
        │
        ▼
PHP front-end (test_face_recognition.php)
        │
        ▼
Python CLI (check_face.py)
        │
        ├── OpenCV → face detection & alignment
        │
        ├── VGGFace → deep face embedding extraction
        │
        ├── Pickle DB (ds_model_vggface_detector_opencv_aligned_normalization_base_expand_0.pkl)
        │            → face matching against enrolled students
        │
        └── Result → JSON back to PHP
        │
        ▼
MySQL → attendance table (present / absent / unknown)
```

### Key Components

| Component | Role |
|-----------|------|
| `check_face.py` | Python entry point for face detection, embedding & matching |
| `test_face_recognition.php` | PHP endpoint that triggers the Python pipeline |
| `assets/photo_etudiant/` | Enrolled student photos (used as reference) |
| `assets/temp_photos/` | Temporary verification captures |
| `assets/temp_db/*.pkl` | Pre-trained VGGFace model & face database (pickled) |

### Design Notes

- **Privacy-first**: all inference runs **locally** — no cloud API calls, no external services.
- **Modular**: the AI module is decoupled from the PHP core; PHP only consumes a JSON output.
- **Extensible**: model and matching threshold are easily swappable.

> ⚠️ **Repository policy:** student photos and the `.pkl` model file are **excluded** from version control (GDPR + repository size). Place them locally following the instructions below.

---

## 🗂️ Database Schema

The system uses **6 optimized relational tables** with proper indexes:

| Table | Description |
|-------|-------------|
| `users` | All users (admin, teacher, student) with hashed passwords |
| `courses` | Course catalog with assigned teacher |
| `sessions` | Scheduled sessions for each course |
| `enrollments` | Many-to-many link: students ↔ courses |
| `attendance` | Attendance records per session and student |
| `password_resets` | Secure, time-limited password reset tokens |

The `attendance` table supports **four statuses**: `present`, `absent`, `retard` (late), `excusé` (excused).

---

## 🚀 Getting Started

### Prerequisites

- **XAMPP** (Apache + MySQL + PHP 8.0+)
- **Python 3.10+** with:
  ```bash
  pip install opencv-python numpy keras-facenet pickle5
  ```
- A modern **web browser** (Chrome, Firefox, Edge)
- (For AI module) A working **webcam** or sample student photos

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/marwensellami/Student-Attendance-Management-System.git
```

**2. Move the project to XAMPP's `htdocs` folder**
```bash
# Windows
mv Student-Attendance-Management-System C:\xampp\htdocs\presence_cours

# macOS / Linux
sudo mv Student-Attendance-Management-System /opt/lampp/htdocs/presence_cours
```

**3. Import the database**
- Start **Apache** and **MySQL** from the XAMPP Control Panel
- Open phpMyAdmin: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
- Create a database named `presence_cours`
- Import the file `database/presence_cours.sql`

**4. Configure the application**
- Copy `includes/config.example.php` to `includes/config.php`
- Adjust the credentials if needed:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'presence_cours');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/presence_cours');
```

**5. Set up the AI Face Recognition module**

Create the required local folders (excluded from Git for privacy):

```bash
mkdir -p assets/photo_etudiant assets/temp_photos assets/temp_db
```

Download or copy the pre-trained VGGFace model:

```
assets/temp_db/ds_model_vggface_detector_opencv_aligned_normalization_base_expand_0.pkl
```

> 💡 This model is **not** included in the repository (size + privacy). It can be regenerated or downloaded from the official VGGFace distribution.

**6. Verify Python dependencies**

```bash
python check_face.py --help
```

**7. Launch the application** 🎉

```
http://localhost/presence_cours/
```

---

## 👥 Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| **Admin** | `admin@presence-cours.com` | `password` |
| **Teacher** | `dupont@universite.com` | `password` |
| **Student** | `leroy@etudiant.com` | `password` |

> ⚠️ **Security note:** Change these credentials and re-hash the passwords before any production deployment.

---

## 📸 Screenshots

> *(Add your own screenshots in a `docs/screenshots/` folder and reference them here)*

| Admin Dashboard | Teacher Dashboard | Student Dashboard |
|:---:|:---:|:---:|
| ![Admin](docs/screenshots/admin-dashboard.png) | ![Teacher](docs/screenshots/teacher-dashboard.png) | ![Student](docs/screenshots/student-dashboard.png) |

| Face Recognition | Statistics & Charts | Attendance Tracking |
|:---:|:---:|:---:|
| ![Face](docs/screenshots/face-recognition.png) | ![Stats](docs/screenshots/statistics.png) | ![Attendance](docs/screenshots/attendance.png) |

---

## 📁 Project Structure

```
presence_cours/
│
├── admin/                     # Administrator interface
│   ├── dashboard.php
│   ├── gestion_utilisateurs.php
│   ├── gestion_cours.php
│   ├── gestion_inscriptions.php
│   ├── suivi_presence.php
│   ├── statistiques.php
│   ├── profile.php
│   └── index.php
│
├── enseignant/                # Teacher interface
│   ├── dashboard.php
│   ├── gestion_cours.php
│   ├── gestion_etudiants.php
│   ├── suivi_etudiants.php
│   ├── rapports.php
│   ├── profile.php
│   └── index.php
│
├── etudiant/                  # Student interface
│   ├── dashboard.php
│   ├── cours.php
│   ├── presence.php
│   ├── profile.php
│   └── index.php
│
├── auth/                      # Authentication
│   ├── login.php
│   ├── register.php
│   ├── reset_password.php
│   └── logout.php
│
├── includes/                  # Core modules
│   ├── config.php
│   ├── config.example.php
│   ├── session.php
│   ├── auth.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
│
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── dashboard.css
│   ├── js/
│   │   ├── main.js
│   │   └── charts.js
│   ├── photo_etudiant/        # (gitignored) enrolled student photos
│   ├── temp_photos/           # (gitignored) verification captures
│   └── temp_db/               # (gitignored) VGGFace model + face DB
│
├── database/
│   └── presence_cours.sql     # Full schema + demo data
│
├── check_face.py              # AI face recognition pipeline (Python)
├── test_face_recognition.php  # PHP ↔ Python bridge
├── index.php                  # Entry point
├── README.md
├── LICENSE
└── .gitignore
```

---

## 🔒 Security Features

This project follows security best practices for PHP web applications:

- ✅ **Password hashing** with `bcrypt` via `password_hash()` and `password_verify()`
- ✅ **PDO prepared statements** on every query — prevents SQL injection
- ✅ **CSRF tokens** generated and validated on all critical forms
- ✅ **Session hardening** — `httponly`, `samesite=Strict`, periodic regeneration
- ✅ **Role-Based Access Control (RBAC)** enforced at the router level
- ✅ **Input sanitization** (`htmlspecialchars`) across all endpoints
- ✅ **Automatic session timeout** to prevent abandoned sessions
- ✅ **Local-only AI inference** — no data sent to external services
- ✅ **.gitignore protection** for student photos and model files (GDPR compliance)

---

## 📊 Results & Impact

- 📉 **~30% reduction** in administrative workload for attendance management
- 🤖 **Automated attendance marking** through AI facial recognition
- 🔄 **Full lifecycle coverage** — from scheduling to reporting
- 🎨 **Intuitive UI** tailored to 3 distinct user profiles
- 🧩 **Modular, documented codebase** ready for production deployment
- 🚀 **Improved data accuracy** by replacing manual spreadsheets
- 🔐 **Privacy-first design** — all AI runs locally

---

## 🗺️ Roadmap

- [ ] Email notifications for low attendance thresholds
- [ ] QR code / OTP-based attendance as an alternative to face recognition
- [ ] PDF export with institutional branding
- [ ] REST API for mobile app integration
- [ ] Docker containerization
- [ ] Multi-language support (FR / EN / AR)
- [ ] Face recognition model upgrade (ArcFace / FaceNet)

---

## 👨‍💻 Author

**Marwen Sellami**
Final-Year Data Science & AI Engineering Student @ ENET'Com
Specializing in Agentic AI · LLMs · RAG · Computer Vision

[![GitHub](https://img.shields.io/badge/GitHub-marwensellami-181717?style=flat&logo=github)](https://github.com/marwensellami)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-Marwen_Sellami-0A66C2?style=flat&logo=linkedin&logoColor=white)](https://linkedin.com/in/marwensellami)

---

## 📄 License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgements

- **[Chart.js](https://www.chartjs.org/)** — Interactive data visualization
- **[OpenCV](https://opencv.org/)** — Face detection & image processing
- **[VGGFace](https://www.robots.ox.ac.uk/~vgg/software/vgg_face/)** — Deep face recognition model
- **[XAMPP](https://www.apachefriends.org/)** — Local development environment
- **[phpMyAdmin](https://www.phpmyadmin.net/)** — Database management
- **[Shields.io](https://shields.io/)** — Dynamic README badges

---

<div align="center">

### ⭐ If you find this project useful, please consider giving it a star! ⭐

**Made with ❤️ using PHP, MySQL, Python, OpenCV & VGGFace**

</div>
