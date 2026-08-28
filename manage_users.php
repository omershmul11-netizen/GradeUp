<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

// שליפת משתמשים קיימים
$usersStmt = $pdo->prepare("
    SELECT 
        u.user_id,
        u.username,
        u.role,
        u.student_id,
        u.teacher_id,
        u.coordinator_id,

        s.first_name AS student_first_name,
        s.last_name AS student_last_name,

        t.first_name AS teacher_first_name,
        t.last_name AS teacher_last_name,

        c.first_name AS coordinator_first_name,
        c.last_name AS coordinator_last_name,
        c.email AS coordinator_email,
        c.role_title AS coordinator_role_title

    FROM users u
    LEFT JOIN students s ON u.student_id = s.student_id
    LEFT JOIN teachers t ON u.teacher_id = t.teacher_id
    LEFT JOIN coordinators c ON u.coordinator_id = c.coordinator_id
    ORDER BY u.user_id ASC
");
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// שליפת תלמידים
$studentsStmt = $pdo->prepare("
    SELECT 
        student_id,
        first_name,
        last_name,
        grade_level,
        class_name,
        email
    FROM students
    ORDER BY grade_level, class_name, last_name, first_name
");
$studentsStmt->execute();
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

// הכנת תלמידים ל-JavaScript
$studentsForJs = array_map(function ($student) {
    return [
        'student_id' => (int)$student['student_id'],
        'first_name' => $student['first_name'] ?? '',
        'last_name' => $student['last_name'] ?? '',
        'grade_level' => (string)($student['grade_level'] ?? ''),
        'class_name' => (string)($student['class_name'] ?? ''),
        'email' => $student['email'] ?? ''
    ];
}, $students);

// שליפת מורים
$teachersStmt = $pdo->prepare("
    SELECT 
        teacher_id,
        first_name,
        last_name,
        email
    FROM teachers
    ORDER BY last_name, first_name
");
$teachersStmt->execute();
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

// שליפת אנשי הנהלה פעילים
$coordinatorsStmt = $pdo->prepare("
    SELECT 
        coordinator_id,
        first_name,
        last_name,
        email,
        phone,
        role_title
    FROM coordinators
    WHERE is_active = 1
    ORDER BY last_name, first_name
");
$coordinatorsStmt->execute();
$coordinators = $coordinatorsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - ניהול משתמשים</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Rubik', sans-serif !important;
        }

        body {
            direction: rtl;
            background: #fcdbd1;
            min-height: 100vh;
            color: #2d3748;
            padding: 0 20px 40px 20px;
            display: block !important;
            overflow-y: auto;
        }

        .header-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(252, 219, 209, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding-top: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.02);
        }

        .topbar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            width: 100% !important;
            margin-bottom: 15px !important;
        }

        .logo {
            font-size: 18px;
            font-weight: 700;
            color: #4a5153;
        }

        .top-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .top-link {
            background: rgba(255, 255, 255, 0.8);
            color: #718096;
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .top-link:hover {
            background: #fff5f5;
            color: #c53030;
            border-color: #fed7d7;
        }

        .hero {
            text-align: center !important;
            margin-bottom: 25px !important;
            width: 100% !important;
        }

        .hero h1 {
            font-size: 34px;
            font-weight: 700;
            color: #4a5153;
            line-height: 1.2;
            position: relative;
            display: inline-block;
        }

        .hero h1::after {
            content: '👥';
            position: absolute;
            top: -22px;
            right: 8px;
            font-size: 22px;
            transform: rotate(15deg);
        }

        .hero p {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 280px 1fr 280px;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            align-items: flex-start;
        }

        .app-container {
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
        }

        .side-panel {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(80, 60, 40, 0.03);
            position: sticky;
            top: 180px;
        }

        .side-panel h3 {
            font-size: 16px;
            color: #4a5153;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            padding-bottom: 6px;
        }

        .side-panel p {
            font-size: 14px;
            color: #718096;
            line-height: 1.6;
        }

        .stat-badge {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border: 1px solid #edf2f7;
        }

        .stat-badge h4 {
            font-size: 32px;
            color: #8fa3db;
            font-weight: 700;
        }

        .stat-badge span {
            font-size: 13px;
            color: #718096;
        }

        .grid {
            display: flex;
            flex-direction: column;
            gap: 25px;
            width: 100%;
        }

        .card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important;
            padding: 28px 22px !important;
            position: relative !important;
            border: 1px solid #f3ebd1 !important;
            width: 100% !important;
            box-shadow:
                0 2px 4px rgba(0,0,0,0.01),
                0 8px 20px rgba(80, 60, 40, 0.06) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px) !important;
            box-shadow:
                0 4px 8px rgba(0,0,0,0.02),
                0 12px 25px rgba(80, 60, 40, 0.09) !important;
        }

        .card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333a42;
            margin-bottom: 8px;
            text-align: center;
        }

        .note {
            color: #718096;
            line-height: 1.5;
            font-size: 13px;
            text-align: center;
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-top: 12px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        select, input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            background: #ffffff;
            color: #2d3748;
            outline: none;
            transition: all 0.2s ease;
        }

        select:focus, input:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        select:disabled {
            background: #edf2f7;
            color: #a0aec0;
            cursor: not-allowed;
        }

        .helper-text {
            font-size: 12px;
            color: #718096;
            line-height: 1.5;
            margin-top: 6px;
        }

        .btn {
            display: block !important;
            width: 100% !important;
            padding: 12px !important;
            margin-top: 15px !important;
            background: #8fa3db !important;
            color: white !important;
            border: none !important;
            border-radius: 12px !important;
            text-align: center !important;
            text-decoration: none !important;
            cursor: pointer !important;
            font-weight: 500 !important;
            font-size: 15px !important;
            box-shadow: 0 4px 10px rgba(143, 163, 219, 0.35) !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn:hover {
            background: #7b8fc7 !important;
            box-shadow: 0 6px 14px rgba(143, 163, 219, 0.5) !important;
            transform: translateY(-1px) !important;
        }

        .hidden {
            display: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #edf2f7;
        }

        th, td {
            border: 1px solid #edf2f7;
            padding: 10px;
            text-align: center;
            font-size: 13px;
            vertical-align: middle;
        }

        th {
            background: #8fa3db;
            color: white;
            font-weight: 500;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }

        .role-coordinator {
            background: #eef5ff;
            color: #0b5fb3;
        }

        .role-teacher {
            background: #e8f7ee;
            color: #0b7a35;
        }

        .role-student {
            background: #fff4e5;
            color: #a15c00;
        }

        .users-table-wrapper {
            overflow-x: auto;
            margin-top: 10px;
        }

        @media (max-width: 1050px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
                max-width: 720px;
            }

            .side-panel {
                text-align: center;
                position: static;
            }

            .header-container {
                position: static;
                background: transparent;
                backdrop-filter: none;
            }
        }

        @media (max-width: 650px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
                padding: 0;
            }

            .side-panel {
                display: none !important;
            }

            body {
                padding: 15px 10px !important;
            }

            .hero h1 {
                font-size: 28px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-workspace">

<div class="header-container">
    <div class="topbar">
        <div class="logo">GradeUp</div>
        <div class="top-actions">
            <a class="top-link" href="index.php">חזרה למסך הנהלה</a>
            <a class="top-link" href="logout.php">יציאה</a>
        </div>
    </div>

    <div class="hero">
        <h1>ניהול משתמשים</h1>
        <p>מסך הנהלה ליצירת משתמשים חדשים ואיפוס סיסמאות למורים, תלמידים ורכזים.</p>
    </div>
</div>

<div class="dashboard-layout">

    <div class="side-panel">
        <h3>פרופיל מחובר</h3>
        <p>שלום, <strong><?= htmlspecialchars($_SESSION['user']) ?></strong></p>
        <p style="font-size: 12px; margin-top: 4px; color: #a0aec0;">סוג חשבון: הנהלה / רכז מערכת</p>
    </div>

    <div class="app-container">
        <div class="grid">

            <div class="card">
                <h2>יצירת משתמש חדש</h2>

                <p class="note">
                    בחר תפקיד, הזן שם משתמש וסיסמה ראשונית. אם מדובר בתלמיד, מורה או איש הנהלה — יש לקשר את המשתמש לרשומה קיימת במערכת.
                </p>

                <label>תפקיד משתמש</label>
                <select id="newRole" onchange="toggleLinkedEntity()">
                    <option value="">-- בחר תפקיד --</option>
                    <option value="coordinator">הנהלה / רכז פדגוגי</option>
                    <option value="teacher">מורה</option>
                    <option value="student">תלמיד</option>
                </select>

                <div id="coordinatorBox" class="hidden">
                    <label>בחר איש הנהלה</label>
                    <select id="coordinatorId">
                        <option value="">-- בחר איש הנהלה --</option>
                        <?php foreach($coordinators as $coordinator): ?>
                            <option value="<?= htmlspecialchars($coordinator['coordinator_id']) ?>">
                                <?= htmlspecialchars($coordinator['first_name'] . ' ' . $coordinator['last_name']) ?>
                                |
                                <?= htmlspecialchars($coordinator['role_title']) ?>
                                |
                                <?= htmlspecialchars($coordinator['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="helper-text">
                        משתמש הנהלה חייב להיות מקושר לאיש הנהלה מתוך טבלת coordinators.
                    </div>
                </div>

                <div id="studentBox" class="hidden">
                    <label>בחר שכבה</label>
                    <select id="studentGrade" onchange="updateClassOptions()">
                        <option value="">-- בחר שכבה --</option>
                    </select>

                    <label>בחר כיתה</label>
                    <select id="studentClass" onchange="updateStudentOptions()" disabled>
                        <option value="">-- בחר קודם שכבה --</option>
                    </select>

                    <label>בחר תלמיד</label>
                    <select id="studentId" disabled>
                        <option value="">-- בחר קודם כיתה --</option>
                    </select>

                    <div class="helper-text" id="studentFilterHelp">
                        לאחר בחירת שכבה וכיתה, תופיע רשימת התלמידים המתאימים בלבד.
                    </div>
                </div>

                <div id="teacherBox" class="hidden">
                    <label>בחר מורה</label>
                    <select id="teacherId">
                        <option value="">-- בחר מורה --</option>
                        <?php foreach($teachers as $teacher): ?>
                            <option value="<?= htmlspecialchars($teacher['teacher_id']) ?>">
                                <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                                |
                                <?= htmlspecialchars($teacher['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label>שם משתמש</label>
                <input type="text" id="newUsername" placeholder="לדוגמה: moshe_teacher">

                <label>סיסמה ראשונית</label>
                <input type="text" id="newPassword" placeholder="לדוגמה: 1234">

                <button class="btn" onclick="createUser()">צור משתמש</button>
            </div>

            <div class="card">
                <h2>איפוס סיסמה</h2>

                <p class="note">
                    בחר משתמש קיים, הזן סיסמה חדשה ולחץ איפוס. הפעולה תעדכן את הסיסמה בטבלת המשתמשים.
                </p>

                <label>בחר משתמש</label>
                <select id="resetUserId">
                    <option value="">-- בחר משתמש --</option>
                    <?php foreach($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['user_id']) ?>">
                            <?= htmlspecialchars($user['username']) ?>
                            |
                            <?= htmlspecialchars($user['role']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>סיסמה חדשה</label>
                <input type="text" id="resetPassword" placeholder="הזן סיסמה חדשה">

                <button class="btn" onclick="resetPassword()">אפס סיסמה</button>
            </div>

            <div class="card">
                <h2>משתמשים קיימים</h2>

                <p class="note">
                    רשימת המשתמשים הפעילים במערכת והקישור שלהם לתלמיד, מורה או איש הנהלה.
                </p>

                <div class="users-table-wrapper">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>שם משתמש</th>
                            <th>תפקיד</th>
                            <th>מקושר אל</th>
                            <th>student_id</th>
                            <th>teacher_id</th>
                            <th>coordinator_id</th>
                        </tr>

                        <?php foreach($users as $user): ?>

                            <?php
                                $roleClass = 'role-badge ';

                                if ($user['role'] === 'coordinator') {
                                    $roleClass .= 'role-coordinator';

                                    $linkedName = trim(($user['coordinator_first_name'] ?? '') . ' ' . ($user['coordinator_last_name'] ?? ''));

                                    if ($linkedName === '') {
                                        $linkedName = 'לא מקושר לאיש הנהלה';
                                    } else {
                                        $linkedName .= ' | ' . ($user['coordinator_role_title'] ?? 'הנהלה');
                                    }

                                } elseif ($user['role'] === 'teacher') {
                                    $roleClass .= 'role-teacher';
                                    $linkedName = trim(($user['teacher_first_name'] ?? '') . ' ' . ($user['teacher_last_name'] ?? ''));

                                    if ($linkedName === '') {
                                        $linkedName = 'לא מקושר למורה';
                                    }

                                } elseif ($user['role'] === 'student') {
                                    $roleClass .= 'role-student';
                                    $linkedName = trim(($user['student_first_name'] ?? '') . ' ' . ($user['student_last_name'] ?? ''));

                                    if ($linkedName === '') {
                                        $linkedName = 'לא מקושר לתלמיד';
                                    }

                                } else {
                                    $roleClass .= 'role-coordinator';
                                    $linkedName = '-';
                                }
                            ?>

                            <tr>
                                <td><?= htmlspecialchars($user['user_id']) ?></td>

                                <td><?= htmlspecialchars($user['username']) ?></td>

                                <td>
                                    <span class="<?= htmlspecialchars($roleClass) ?>">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($linkedName) ?></td>

                                <td><?= htmlspecialchars($user['student_id'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($user['teacher_id'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($user['coordinator_id'] ?? '-') ?></td>
                            </tr>

                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <div class="side-panel">
        <h3>נתונים</h3>

        <div class="stat-badge">
            <h4><?= count($users) ?></h4>
            <span>משתמשים במערכת</span>
        </div>

        <div class="stat-badge">
            <h4><?= count($teachers) ?></h4>
            <span>מורים רשומים</span>
        </div>

        <div class="stat-badge">
            <h4><?= count($students) ?></h4>
            <span>תלמידים רשומים</span>
        </div>

        <div class="stat-badge">
            <h4><?= count($coordinators) ?></h4>
            <span>אנשי הנהלה פעילים</span>
        </div>
    </div>

</div>

<script>
const studentsData = <?= json_encode($studentsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function getGradeLabel(grade) {
    const gradeText = String(grade);

    if (gradeText === '10') {
        return "שכבה י׳";
    }

    if (gradeText === '11') {
        return "שכבה י״א";
    }

    if (gradeText === '12') {
        return "שכבה י״ב";
    }

    return "שכבה " + gradeText;
}

function naturalSort(values) {
    return values.sort((a, b) => {
        return String(a).localeCompare(String(b), 'he', {
            numeric: true,
            sensitivity: 'base'
        });
    });
}

function resetStudentFilters() {
    const gradeSelect = document.getElementById('studentGrade');
    const classSelect = document.getElementById('studentClass');
    const studentSelect = document.getElementById('studentId');
    const help = document.getElementById('studentFilterHelp');

    gradeSelect.innerHTML = '<option value="">-- בחר שכבה --</option>';
    classSelect.innerHTML = '<option value="">-- בחר קודם שכבה --</option>';
    studentSelect.innerHTML = '<option value="">-- בחר קודם כיתה --</option>';

    classSelect.disabled = true;
    studentSelect.disabled = true;

    help.innerText = 'לאחר בחירת שכבה וכיתה, תופיע רשימת התלמידים המתאימים בלבד.';
}

function populateGradeOptions() {
    resetStudentFilters();

    const gradeSelect = document.getElementById('studentGrade');

    const grades = naturalSort([
        ...new Set(
            studentsData
                .map(student => String(student.grade_level || '').trim())
                .filter(grade => grade !== '')
        )
    ]);

    grades.forEach(grade => {
        const option = document.createElement('option');
        option.value = grade;
        option.textContent = getGradeLabel(grade);
        gradeSelect.appendChild(option);
    });
}

function updateClassOptions() {
    const grade = document.getElementById('studentGrade').value;
    const classSelect = document.getElementById('studentClass');
    const studentSelect = document.getElementById('studentId');
    const help = document.getElementById('studentFilterHelp');

    classSelect.innerHTML = '<option value="">-- בחר כיתה --</option>';
    studentSelect.innerHTML = '<option value="">-- בחר קודם כיתה --</option>';
    studentSelect.disabled = true;

    if (!grade) {
        classSelect.disabled = true;
        help.innerText = 'בחר שכבה כדי להציג כיתות זמינות.';
        return;
    }

    const classes = naturalSort([
        ...new Set(
            studentsData
                .filter(student => String(student.grade_level) === String(grade))
                .map(student => String(student.class_name || '').trim())
                .filter(className => className !== '')
        )
    ]);

    if (classes.length === 0) {
        classSelect.disabled = true;
        help.innerText = 'לא נמצאו כיתות עבור השכבה שנבחרה.';
        return;
    }

    classes.forEach(className => {
        const option = document.createElement('option');
        option.value = className;
        option.textContent = className;
        classSelect.appendChild(option);
    });

    classSelect.disabled = false;
    help.innerText = 'בחר כיתה כדי להציג תלמידים מתוך השכבה שנבחרה.';
}

function updateStudentOptions() {
    const grade = document.getElementById('studentGrade').value;
    const className = document.getElementById('studentClass').value;
    const studentSelect = document.getElementById('studentId');
    const help = document.getElementById('studentFilterHelp');

    studentSelect.innerHTML = '<option value="">-- בחר תלמיד --</option>';

    if (!grade || !className) {
        studentSelect.disabled = true;
        help.innerText = 'בחר שכבה וכיתה כדי להציג תלמידים.';
        return;
    }

    const filteredStudents = studentsData.filter(student => {
        return String(student.grade_level) === String(grade)
            && String(student.class_name) === String(className);
    });

    if (filteredStudents.length === 0) {
        studentSelect.disabled = true;
        help.innerText = 'לא נמצאו תלמידים בכיתה שנבחרה.';
        return;
    }

    filteredStudents.forEach(student => {
        const option = document.createElement('option');
        option.value = student.student_id;

        option.textContent =
            student.first_name + ' ' + student.last_name +
            ' | ' + student.class_name +
            ' | ' + (student.email || 'ללא מייל');

        studentSelect.appendChild(option);
    });

    studentSelect.disabled = false;
    help.innerText = 'נמצאו ' + filteredStudents.length + ' תלמידים בכיתה ' + className + '.';
}

function toggleLinkedEntity(){

    const role = document.getElementById('newRole').value;

    document.getElementById('coordinatorBox').classList.add('hidden');
    document.getElementById('studentBox').classList.add('hidden');
    document.getElementById('teacherBox').classList.add('hidden');

    document.getElementById('coordinatorId').value = '';
    document.getElementById('teacherId').value = '';

    resetStudentFilters();

    if(role === 'coordinator'){
        document.getElementById('coordinatorBox').classList.remove('hidden');
    }

    if(role === 'student'){
        document.getElementById('studentBox').classList.remove('hidden');
        populateGradeOptions();
    }

    if(role === 'teacher'){
        document.getElementById('teacherBox').classList.remove('hidden');
    }
}

async function createUser(){

    const role = document.getElementById('newRole').value;
    const username = document.getElementById('newUsername').value.trim();
    const password = document.getElementById('newPassword').value.trim();

    const coordinatorId = document.getElementById('coordinatorId').value;
    const studentId = document.getElementById('studentId').value;
    const teacherId = document.getElementById('teacherId').value;

    if(!role){
        alert('יש לבחור תפקיד משתמש');
        return;
    }

    if(!username){
        alert('יש להזין שם משתמש');
        return;
    }

    if(!password){
        alert('יש להזין סיסמה ראשונית');
        return;
    }

    if(role === 'coordinator' && !coordinatorId){
        alert('יש לבחור איש הנהלה עבור משתמש הנהלה');
        return;
    }

    if(role === 'student'){
        if(!document.getElementById('studentGrade').value){
            alert('יש לבחור שכבה עבור תלמיד');
            return;
        }

        if(!document.getElementById('studentClass').value){
            alert('יש לבחור כיתה עבור תלמיד');
            return;
        }

        if(!studentId){
            alert('יש לבחור תלמיד עבור משתמש תלמיד');
            return;
        }
    }

    if(role === 'teacher' && !teacherId){
        alert('יש לבחור מורה עבור משתמש מורה');
        return;
    }

    const response = await fetch('api/create_user.php', {
        method:'POST',
        headers:{
            'Content-Type':'application/json'
        },
        body:JSON.stringify({
            role:role,
            username:username,
            password:password,
            coordinator_id:coordinatorId,
            student_id:studentId,
            teacher_id:teacherId
        })
    });

    const result = await response.json();

    if(result.success){
        alert('המשתמש נוצר בהצלחה ✅');
        location.reload();
    }else{
        alert('שגיאה: ' + result.message);
    }
}

async function resetPassword(){

    const userId = document.getElementById('resetUserId').value;
    const newPassword = document.getElementById('resetPassword').value.trim();

    if(!userId){
        alert('יש לבחור משתמש');
        return;
    }

    if(!newPassword){
        alert('יש להזין סיסמה חדשה');
        return;
    }

    const response = await fetch('api/reset_user_password.php', {
        method:'POST',
        headers:{
            'Content-Type':'application/json'
        },
        body:JSON.stringify({
            user_id:userId,
            new_password:newPassword
        })
    });

    const result = await response.json();

    if(result.success){
        alert('הסיסמה אופסה בהצלחה ✅');
        document.getElementById('resetPassword').value = '';
    }else{
        alert('שגיאה: ' + result.message);
    }
}
</script>

</body>
</html>
