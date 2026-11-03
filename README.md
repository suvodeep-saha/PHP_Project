# Online Assignment Submission & Evaluation System (EduSubmit Portal)

A complete, responsive, database-driven educational web portal developed with **Core PHP 8**, **MySQL**, **HTML5**, **CSS3 / Bootstrap 5**, and **JavaScript**. Designed for local deployment using **XAMPP**.

---

## 1. Project Overview & Architecture

EduSubmit provides a paperless academic workflow for modern universities and colleges:
- **Students** view assignments, upload completed solutions, track evaluation statuses (*Pending* &rarr; *Reviewed* &rarr; *Completed*), and inspect instructor feedback.
- **Instructors** publish assignments with deadlines, attached question papers, evaluate student work with marks out of 100, and leave comments.
- **Administrators** govern users, monitor course submissions, override statuses, and audit system metrics.

---

## 2. Directory Structure

```
c:/xampp/files/htdocs/suvo/PROJECT/
├── admin/                         # Administrator Module
│   ├── assignments.php            # View & audit all university assignments
│   ├── dashboard.php              # Administrative control center & metrics
│   ├── delete_user.php            # Delete user account with file cleanup
│   ├── edit_user.php              # Modify user role, status, or reset password
│   ├── profile.php                # Administrator profile & password change
│   ├── submissions.php            # Global submissions registry
│   ├── update_status.php          # Administrative status override & remarks
│   └── users.php                  # User account management (CRUD Read & Create)
├── assets/
│   ├── css/
│   │   └── style.css              # Custom styling for dashboard, cards & tables
│   └── js/
│       └── main.js                # Auto-dismiss alerts, confirmation dialogs, client validation
├── config/
│   ├── config.php                 # App constants, secure session setup, CSRF & flash helpers
│   └── database.php               # PDO database connection with exception handling
├── database/
│   └── schema.sql                 # Complete MySQL schema & initial seed data
├── includes/
│   ├── auth_check.php             # RBAC guard middleware (requireAuth, requireGuest)
│   ├── footer.php                 # Global responsive footer & Bootstrap JS
│   ├── header.php                 # Global responsive header, navbar & flash alerts
│   └── sidebar.php                # Role-specific dashboard navigation sidebar
├── student/                       # Student Module
│   ├── assignment_details.php     # Detailed assignment view & submission status
│   ├── assignments.php            # Browse, filter & sort active assignments
│   ├── dashboard.php              # Student dashboard, upcoming deadlines & score overview
│   ├── profile.php                # Student profile & password settings
│   ├── submissions.php            # History of submitted work, marks & feedback
│   └── submit.php                 # File upload & assignment submission handler
├── teacher/                       # Teacher Module
│   ├── add_assignment.php         # Create new course assignment & upload question sheet
│   ├── assignment_details.php     # Assignment brief & list of student submissions
│   ├── assignments.php            # Manage authored assignments (CRUD Read)
│   ├── dashboard.php              # Faculty dashboard & pending grading queue
│   ├── delete_assignment.php      # Delete assignment & clean associated files
│   ├── edit_assignment.php        # Edit assignment details or replace question sheet
│   ├── evaluate.php               # Grade submission, assign marks & written feedback
│   ├── profile.php                # Teacher profile & password settings
│   └── submissions.php            # Student submissions queue with filters
├── uploads/                       # Secure File Storage
│   ├── .htaccess                  # Execution prevention & security hardening
│   ├── assignments/               # Teacher uploaded question papers
│   ├── avatars/                   # User profile pictures
│   └── submissions/               # Student submitted solution files
├── download.php                   # Secure role-based file streaming controller
├── index.php                      # Public homepage & live statistics showcase
├── install.php                    # One-Click database installer & test harness
├── login.php                      # Multi-role authentication & session initialization
├── logout.php                     # Clean session destruction
├── members.php                    # Role overview & demo credentials directory
└── register.php                   # Student / Teacher account registration
```

---

## 3. Database Schema (`online_assignment_system`)

The system uses three normalized MySQL tables in the `online_assignment_system` database:

### A. `users` Table
| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier |
| `name` | VARCHAR(100) | NOT NULL | User's full name |
| `email` | VARCHAR(150) | NOT NULL, UNIQUE | User's login email address |
| `password` | VARCHAR(255) | NOT NULL | Bcrypt hashed password (`password_hash()`) |
| `role` | ENUM | NOT NULL, DEFAULT 'student' | `'admin'`, `'teacher'`, or `'student'` |
| `phone` | VARCHAR(20) | NULL | Optional contact phone number |
| `bio` | TEXT | NULL | Academic bio / notes |
| `status` | ENUM | NOT NULL, DEFAULT 'active' | `'active'` or `'inactive'` |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Account creation date |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | Last modification date |

### B. `assignments` Table
| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Assignment identifier |
| `teacher_id` | INT UNSIGNED | NOT NULL, FOREIGN KEY | References `users(id)` ON DELETE CASCADE |
| `title` | VARCHAR(200) | NOT NULL | Assignment task title |
| `subject` | VARCHAR(100) | NOT NULL | Course / subject title |
| `description`| TEXT | NOT NULL | Assignment instructions & expectations |
| `deadline` | DATETIME | NOT NULL | Submission cutoff date and time |
| `file` | VARCHAR(255) | NULL | Question paper file stored in `uploads/assignments/` |
| `max_marks` | INT UNSIGNED | NOT NULL, DEFAULT 100 | Maximum grading points |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Publication timestamp |

### C. `submissions` Table
| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Submission identifier |
| `student_id` | INT UNSIGNED | NOT NULL, FOREIGN KEY | References `users(id)` ON DELETE CASCADE |
| `assignment_id`| INT UNSIGNED | NOT NULL, FOREIGN KEY | References `assignments(id)` ON DELETE CASCADE |
| `file` | VARCHAR(255) | NOT NULL | Uploaded document stored in `uploads/submissions/` |
| `student_notes`| TEXT | NULL | Remarks submitted by student |
| `submit_date` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Exact submission timestamp |
| `marks` | DECIMAL(5,2) | NULL | Score awarded by instructor (0 to `max_marks`) |
| `feedback` | TEXT | NULL | Instructor's written critique |
| `status` | ENUM | DEFAULT 'Pending' | `'Pending'`, `'Reviewed'`, or `'Completed'` |
| `evaluated_at`| DATETIME | NULL | Timestamp of evaluation |
| `evaluated_by`| INT UNSIGNED | NULL, FOREIGN KEY | References `users(id)` ON DELETE SET NULL |

---

## 4. Default Demo Credentials

Pre-seeded accounts are provided for immediate testing and semester project demonstrations:

| Role | Email Address | Password | Name |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@assignments.edu` | `admin123` | System Administrator |
| **Teacher** | `prof.sharma@assignments.edu` | `teacher123` | Prof. Ramesh Sharma |
| **Teacher** | `dr.roy@assignments.edu` | `teacher123` | Dr. Ananya Roy |
| **Student** | `suvo@student.edu` | `student123` | Suvodeep Mukherjee |
| **Student** | `priya@student.edu` | `student123` | Priya Sen |
| **Student** | `rahul@student.edu` | `student123` | Rahul Verma |

---

## 5. PHP & MySQL Syllabus Concept Implementation Map

Every requirement from the syllabus is practically implemented:

### A. Forms Handling & User Input
- Forms implemented with `POST` for state mutations and `GET` for filtering/sorting.
- Server-side validation on every input: length, type, email format (`filter_var`), dates, and numerical boundaries.
- Input sanitization via `trim()` and HTML escaping.

### B. PHP and MySQL Integration (PDO)
- Connection managed through `PDO` (`config/database.php`) with `ATTR_ERRMODE => ERRMODE_EXCEPTION` and `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`.
- 100% of queries use **Prepared Statements** with named (`:id`) or positional (`?`) parameters.
- Complete CRUD operations implemented across Users, Assignments, and Submissions.

### C. Session Management & Cookies
- `session_start()` initialized in `config/config.php` with `httponly` and `samesite => 'Lax'`.
- Session-based authentication storing `user_id`, `user_name`, and `user_role`.
- `session_regenerate_id(true)` upon successful login to mitigate session fixation.
- Clean logout destroying sessions and unsetting cookies.
- "Remember Me" persistent cookie using `setcookie()`.

### D. User Authentication & Authorization
- Password hashing using `password_hash($password, PASSWORD_BCRYPT)`.
- Password verification using `password_verify($password, $hash)`.
- Role-based middleware `requireAuth(['role'])` protecting role-specific directories (`student/`, `teacher/`, `admin/`).

### E. Error Handling & Flash Messages
- `try-catch` blocks surrounding all database operations.
- Flash notifications stored in `$_SESSION['flash']` and rendered as dismissible Bootstrap alerts.

### F. File Handling & Secure Uploads
- File uploads for assignments and submissions supporting `PDF`, `DOCX`, `DOC`, `ZIP`, and `TXT`.
- File size restriction enforced at 10 MB.
- Dual-layer validation: extension checking + real MIME signature inspection via `finfo_file()`.
- Unique filename generation (`bin2hex(random_bytes())`) preventing file overwrites.
- `.htaccess` in `uploads/` disabling PHP script execution.
- Safe download streamer (`download.php`) preventing directory traversal and restricting access.

### G. Security in PHP
- **SQL Injection Prevention**: PDO prepared statements used exclusively.
- **XSS Protection**: Universal `e()` helper wrapping `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`.
- **CSRF Protection**: Synchronizer Token Pattern with `csrf_field()` and `verify_csrf_token()`.

---

## 6. How to Run & Test on XAMPP

1. **Start XAMPP**:
   - Open the **XAMPP Control Panel**.
   - Start **Apache** and **MySQL**.
2. **Setup Database**:
   - Open your browser and navigate to:
     `http://localhost/suvo/PROJECT/install.php`
   - Click **Install & Initialize Database**.
   - Alternatively, import `database/schema.sql` via phpMyAdmin (`http://localhost/phpmyadmin`).
3. **Open Application**:
   - Visit: `http://localhost/suvo/PROJECT/index.php`
   - Test login with any of the demo accounts listed above.

