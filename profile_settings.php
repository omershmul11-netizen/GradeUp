<?php
session_start();

if (
    !isset($_SESSION['user']) &&
    !isset($_SESSION['student_id']) &&
    !isset($_SESSION['teacher_id'])
) {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

$profileType = '';
$username = '';
$fullName = '';
$email = '';
$roleLabel = '';
$extraInfo = '';
$subjectsText = '';
$canEditEmail = false;
$backLink = 'index.php';
$logoutLink = 'logout.php';

/*
    זיהוי תלמיד לפי student_id
*/
if (isset($_SESSION['student_id'])) {

    $studentId = (int)$_SESSION['student_id'];

    $stmt = $pdo->prepare("
        SELECT 
            s.student_id,
            s.first_name,
            s.last_name,
            s.email,
            s.grade_level,
            s.class_name,
            u.username
        FROM students s
        LEFT JOIN users u ON u.student_id = s.student_id
        WHERE s.student_id = ?
        LIMIT 1
    ");

    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $profileType = 'student';
        $username = $student['username'] ?? ($_SESSION['student_username'] ?? '');
        $fullName = trim($student['first_name'] . ' ' . $student['last_name']);
        $email = $student['email'];
        $roleLabel = 'תלמיד';
        $extraInfo = 'שכבה ' . $student['grade_level'] . ' | כיתה ' . $student['class_name'];
        $canEditEmail = true;
        $backLink = 'student_assignments.php';
        $logoutLink = 'student_logout.php';
    }

/*
    זיהוי מורה לפי teacher_id
*/
} elseif (isset($_SESSION['teacher_id'])) {

    $teacherId = (int)$_SESSION['teacher_id'];

    $stmt = $pdo->prepare("
        SELECT 
            t.teacher_id,
            t.first_name,
            t.last_name,
            t.email,
            u.username
        FROM teachers t
        LEFT JOIN users u ON u.teacher_id = t.teacher_id
        WHERE t.teacher_id = ?
        LIMIT 1
    ");

    $stmt->execute([$teacherId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher) {
        $profileType = 'teacher';
        $username = $teacher['username'] ?? ($_SESSION['teacher_username'] ?? '');
        $fullName = trim($teacher['first_name'] . ' ' . $teacher['last_name']);

        if ($fullName === '') {
            $fullName = $_SESSION['teacher_name'] ?? 'מורה';
        }

        $email = $teacher['email'] ?? '';
        $roleLabel = 'מורה';
        $extraInfo = 'חשבון מורה במערכת GradeUp';
        $canEditEmail = true;
        $backLink = 'teacher_dashboard.php';
        $logoutLink = 'teacher_logout.php';

        try {
            $subjectsStmt = $pdo->prepare("
                SELECT s.subject_name
                FROM teacher_subjects ts
                JOIN subjects s ON ts.subject_id = s.subject_id
                WHERE ts.teacher_id = ?
                ORDER BY s.subject_name
            ");

            $subjectsStmt->execute([$teacherId]);
            $subjects = $subjectsStmt->fetchAll(PDO::FETCH_COLUMN);

            if ($subjects && count($subjects) > 0) {
                $subjectMap = [
                    'Math' => 'מתמטיקה',
                    'Mathematics' => 'מתמטיקה',
                    'Computer Science' => 'מדעי המחשב',
                    'ComputerScience' => 'מדעי המחשב',
                    'CS' => 'מדעי המחשב',
                    'Physics' => 'פיזיקה',
                    'Hebrew' => 'עברית',
                    'English' => 'אנגלית'
                ];

                $translatedSubjects = [];

                foreach ($subjects as $subject) {
                    $translatedSubjects[] = $subjectMap[$subject] ?? $subject;
                }

                $subjectsText = implode(', ', $translatedSubjects);
            } else {
                $subjectsText = 'לא הוגדרו מקצועות למורה';
            }

        } catch (Exception $e) {
            $subjectsText = 'לא ניתן לטעון מקצועות כרגע';
        }
    }

/*
    זיהוי לפי username:
    הנהלה / מורה / תלמיד
*/
} elseif (isset($_SESSION['user'])) {

    $usernameSession = $_SESSION['user'];

    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.role,
            u.student_id,
            u.teacher_id,
            u.coordinator_id,

            s.first_name AS student_first_name,
            s.last_name AS student_last_name,
            s.email AS student_email,
            s.grade_level AS student_grade_level,
            s.class_name AS student_class_name,

            t.first_name AS teacher_first_name,
            t.last_name AS teacher_last_name,
            t.email AS teacher_email,

            c.first_name AS coordinator_first_name,
            c.last_name AS coordinator_last_name,
            c.email AS coordinator_email,
            c.phone AS coordinator_phone,
            c.role_title AS coordinator_role_title,
            c.is_active AS coordinator_is_active

        FROM users u
        LEFT JOIN students s ON u.student_id = s.student_id
        LEFT JOIN teachers t ON u.teacher_id = t.teacher_id
        LEFT JOIN coordinators c ON u.coordinator_id = c.coordinator_id
        WHERE u.username = ?
        LIMIT 1
    ");

    $stmt->execute([$usernameSession]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        $username = $user['username'];

        if ($user['role'] === 'teacher') {

            $profileType = 'teacher';
            $fullName = trim(($user['teacher_first_name'] ?? '') . ' ' . ($user['teacher_last_name'] ?? ''));

            if ($fullName === '') {
                $fullName = 'מורה';
            }

            $email = $user['teacher_email'] ?? '';
            $roleLabel = 'מורה';
            $extraInfo = 'חשבון מורה במערכת GradeUp';
            $canEditEmail = true;
            $backLink = 'teacher_dashboard.php';
            $logoutLink = 'teacher_logout.php';

        } elseif ($user['role'] === 'student') {

            $profileType = 'student';
            $fullName = trim(($user['student_first_name'] ?? '') . ' ' . ($user['student_last_name'] ?? ''));

            if ($fullName === '') {
                $fullName = 'תלמיד';
            }

            $email = $user['student_email'] ?? '';
            $roleLabel = 'תלמיד';
            $extraInfo = 'שכבה ' . ($user['student_grade_level'] ?? '-') . ' | כיתה ' . ($user['student_class_name'] ?? '-');
            $canEditEmail = true;
            $backLink = 'student_assignments.php';
            $logoutLink = 'student_logout.php';

        } elseif ($user['role'] === 'coordinator') {

            $profileType = 'coordinator';

            $coordinatorFullName = trim(($user['coordinator_first_name'] ?? '') . ' ' . ($user['coordinator_last_name'] ?? ''));

            if ($coordinatorFullName !== '') {
                $fullName = $coordinatorFullName;
            } else {
                $fullName = 'הנהלה / רכז פדגוגי';
            }

            $email = $user['coordinator_email'] ?? '';
            $roleLabel = $user['coordinator_role_title'] ?? 'הנהלה / רכז פדגוגי';

            $extraInfoParts = [];

            $extraInfoParts[] = 'ניהול מערכת, משתמשים, שיבוצים, נוכחות ומשימות';

            if (!empty($user['coordinator_phone'])) {
                $extraInfoParts[] = 'טלפון: ' . $user['coordinator_phone'];
            }

            if (!empty($user['coordinator_id'])) {
                $extraInfoParts[] = 'coordinator_id: ' . $user['coordinator_id'];
            }

            $extraInfo = implode(' | ', $extraInfoParts);

            /*
                הנהלה יכולה לערוך מייל רק אם המשתמש מקושר לרשומת coordinators.
            */
            $canEditEmail = !empty($user['coordinator_id']);

            $backLink = 'index.php';
            $logoutLink = 'logout.php';

        } else {

            $profileType = $user['role'];
            $fullName = 'משתמש מערכת';
            $roleLabel = $user['role'];
            $extraInfo = 'חשבון משתמש במערכת';
            $canEditEmail = false;
            $backLink = 'index.php';
            $logoutLink = 'logout.php';
        }
    }
}

if ($fullName === '') {
    $fullName = 'משתמש';
}

if ($username === '' && isset($_SESSION['user'])) {
    $username = $_SESSION['user'];
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - הפרופיל שלי</title>

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

        .top-actions {
            display: flex;
            gap: 8px;
            align-items: center;
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
            content: '⚙️';
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
            max-width: 520px;
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
            margin-bottom: 25px;
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

        .profile-row {
            background: rgba(255,255,255,0.65);
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 12px;
            margin-top: 10px;
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
        }

        .profile-row strong {
            color: #2d3748;
        }

        label {
            display: block;
            margin-top: 14px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        input {
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

        input:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
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

        .btn.secondary {
            background: #718096 !important;
            box-shadow: 0 4px 10px rgba(113, 128, 150, 0.25) !important;
        }

        .info-box {
            margin-top: 18px;
            background: rgba(255,255,255,0.55);
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 14px;
            color: #718096;
            font-size: 13px;
            line-height: 1.6;
            text-align: center;
        }

        @media (max-width: 1050px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
                max-width: 620px;
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
            <a class="top-link" href="<?= htmlspecialchars($backLink) ?>">חזרה</a>
            <a class="top-link" href="<?= htmlspecialchars($logoutLink) ?>">יציאה</a>
        </div>
    </div>

    <div class="hero">
        <h1>הפרופיל שלי</h1>
        <p>צפייה בפרטים אישיים ועדכון כתובת מייל במערכת.</p>
    </div>
</div>

<div class="dashboard-layout">

    <div class="side-panel">
        <h3>ניווט</h3>
        <p>חזרה למסך העבודה הראשי שלך.</p>
        <a class="btn secondary" href="<?= htmlspecialchars($backLink) ?>">חזרה למסך הקודם</a>
    </div>

    <div class="app-container">

        <div class="card">
            <h2>פרטים אישיים</h2>

            <p class="note">
                כאן ניתן לצפות בפרטים האישיים כפי שהם שמורים במערכת.
            </p>

            <div class="profile-row">
                <strong>שם מלא:</strong>
                <?= htmlspecialchars($fullName) ?>
            </div>

            <div class="profile-row">
                <strong>שם משתמש:</strong>
                <?= htmlspecialchars($username) ?>
            </div>

            <div class="profile-row">
                <strong>תפקיד:</strong>
                <?= htmlspecialchars($roleLabel) ?>
            </div>

            <?php if ($extraInfo !== ''): ?>
                <div class="profile-row">
                    <strong>פרטים נוספים:</strong>
                    <?= htmlspecialchars($extraInfo) ?>
                </div>
            <?php endif; ?>

            <?php if ($profileType === 'teacher' && $subjectsText !== ''): ?>
                <div class="profile-row">
                    <strong>מקצועות שהמורה מלמד:</strong>
                    <?= htmlspecialchars($subjectsText) ?>
                </div>
            <?php endif; ?>

            <?php if ($canEditEmail): ?>
                <div class="profile-row">
                    <strong>מייל נוכחי:</strong>
                    <?= htmlspecialchars($email) ?>
                </div>
            <?php else: ?>
                <div class="info-box">
                    המשתמש אינו מקושר לרשומת פרופיל עם מייל אישי.  
                    כדי לאפשר שינוי מייל, יש לקשר אותו לתלמיד, מורה או איש הנהלה מתאים.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($canEditEmail): ?>

            <div class="card">
                <h2>עדכון כתובת מייל</h2>

                <p class="note">
                    ניתן לעדכן את כתובת המייל האישית שלך. שינוי סיסמה מתבצע רק על ידי הנהלה במסך ניהול משתמשים.
                </p>

                <label>מייל חדש</label>
                <input 
                    type="email" 
                    id="emailInput" 
                    value="<?= htmlspecialchars($email) ?>"
                    placeholder="הזן כתובת מייל חדשה"
                >

                <button class="btn" onclick="updateEmail()">שמור מייל</button>
            </div>

        <?php endif; ?>

    </div>

    <div class="side-panel">
        <h3>הגדרות אישיות</h3>

        <p>
            במסך זה המשתמש יכול לראות את פרטיו האישיים ולעדכן כתובת מייל בלבד.
        </p>

        <div class="info-box">
            איפוס סיסמה ויצירת משתמשים מתבצעים על ידי הנהלה בלבד.
        </div>
    </div>

</div>

<script>
async function updateEmail(){

    const email = document.getElementById('emailInput').value.trim();

    if(!email){
        alert('יש להזין כתובת מייל');
        return;
    }

    const response = await fetch('api/update_profile_email.php', {
        method:'POST',
        headers:{
            'Content-Type':'application/json'
        },
        body:JSON.stringify({
            email:email
        })
    });

    const result = await response.json();

    if(result.success){
        alert('המייל עודכן בהצלחה ✅');
        location.reload();
    }else{
        alert('שגיאה: ' + result.message);
    }
}
</script>

</body>
</html>
