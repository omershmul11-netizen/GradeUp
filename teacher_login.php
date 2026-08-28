<?php
session_start();

require_once "db_config.php";

/*
    אם יש סשן של הנהלה או תלמיד — מנקים אותו כדי שלא יתנגש עם כניסת מורה.
*/
if (isset($_SESSION['student_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'coordinator')) {
    session_unset();
    session_destroy();
    session_start();
}

/*
    אם מורה כבר מחובר — מעבירים אותו לדשבורד שלו.
*/
if (isset($_SESSION['teacher_id'])) {
    header("Location: teacher_dashboard.php");
    exit;
}

$error = '';

// עיבוד נתוני טופס הכניסה של המורה
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "יש למלא שם משתמש וסיסמה";
    } else {

        // שאילתת חיבור המשלבת את טבלת המשתמשים והמורים
        $stmt = $pdo->prepare("
            SELECT 
                u.user_id,
                u.username,
                u.password,
                u.role,
                u.teacher_id,
                t.first_name,
                t.last_name,
                t.email
            FROM users u
            JOIN teachers t ON u.teacher_id = t.teacher_id
            WHERE u.username = ?
              AND u.role = 'teacher'
              AND u.teacher_id IS NOT NULL
            LIMIT 1
        ");

        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // אימות זהות וסיסמה
        if ($user && gradeup_verify_password($password, $user['password'])) {

            gradeup_upgrade_password($pdo, $user['user_id'], $password, $user['password']);

            session_regenerate_id(true);

            $_SESSION['teacher_id'] = (int)$user['teacher_id'];
            $_SESSION['teacher_username'] = $user['username'];
            $_SESSION['teacher_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['teacher_email'] = $user['email'];
            $_SESSION['role'] = 'teacher';

            header("Location: teacher_dashboard.php");
            exit;

        } else {
            $error = "שם משתמש או סיסמה שגויים";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - כניסת מורים</title>

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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow-y: auto;
        }

        .decor-ruler {
            position: absolute;
            top: 60px;
            left: 18%;
            font-size: 140px;
            transform: rotate(-20deg);
            opacity: 0.22;
            user-select: none;
            pointer-events: none;
        }

        .decor-pencil-blue {
            position: absolute;
            bottom: 80px;
            left: 20%;
            font-size: 120px;
            transform: rotate(55deg);
            opacity: 0.28;
            user-select: none;
            pointer-events: none;
        }

        .decor-pencil-pink {
            position: absolute;
            bottom: 100px;
            right: 18%;
            font-size: 110px;
            transform: rotate(-35deg);
            opacity: 0.28;
            user-select: none;
            pointer-events: none;
        }

        .decor-clip-1 {
            position: absolute;
            top: 30%;
            right: 22%;
            font-size: 75px;
            transform: rotate(25deg);
            opacity: 0.18;
            user-select: none;
            pointer-events: none;
        }

        .decor-clip-2 {
            position: absolute;
            bottom: 35%;
            left: 16%;
            font-size: 70px;
            transform: rotate(-45deg);
            opacity: 0.18;
            user-select: none;
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 380px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 10;
        }

        .badge-top {
            background: rgba(255, 255, 255, 0.6);
            color: #4a5153;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.4);
            margin-bottom: -5px;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .brand-header h1 {
            font-size: 42px;
            font-weight: 700;
            color: #4a5153;
            letter-spacing: -0.5px;
            line-height: 1.1;
            display: inline-block;
            position: relative;
        }

        .brand-header h1::after {
            content: '🎓';
            position: absolute;
            top: -14px;
            left: -26px;
            font-size: 22px;
            transform: rotate(15deg);
        }

        .brand-header p {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
            opacity: 0.85;
            line-height: 1.4;
        }

        .login-card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important;
            padding: 35px 24px 30px 24px !important;
            position: relative;
            border: 1px solid #f3ebd1 !important;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.01), 0 10px 25px rgba(80, 60, 40, 0.07);
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
            text-align: right;
        }

        input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            background: #ffffff;
            color: #2d3748;
            outline: none;
            transition: all 0.2s ease;
            text-align: right;
        }

        input:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        .btn-submit {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 24px;
            background: #8fa3db;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(143, 163, 219, 0.35);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
        }

        .btn-submit:hover {
            background: #7b8fc7;
            box-shadow: 0 6px 16px rgba(143, 163, 219, 0.5);
            transform: translateY(-1px);
        }

        .login-footer {
            text-align: center;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            gap: 12px;
            width: 100%;
        }

        .secondary-link {
            display: inline-block;
            color: #718096;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.4);
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .secondary-link:hover {
            color: #2d3748;
            background: rgba(255, 255, 255, 0.7);
            transform: translateY(-1px);
        }

        .error-message {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
            padding: 10px;
            border-radius: 10px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .required {
            color: #c53030;
            font-weight: 700;
            margin-right: 4px;
        }

        @media (max-width: 768px) {
            .decor-ruler,
            .decor-pencil-blue,
            .decor-pencil-pink,
            .decor-clip-1,
            .decor-clip-2 {
                display: none !important;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>
<body class="gradeup-ui gradeup-auth">

    <div class="decor-ruler">📐</div>
    <div class="decor-pencil-blue">✏️</div>
    <div class="decor-pencil-pink">🖍️</div>
    <div class="decor-clip-1">📎</div>
    <div class="decor-clip-2">📎</div>

    <div class="login-container">

        <div class="badge-top">כניסת מורים בלבד</div>

        <div class="brand-header">
            <h1>GradeUp</h1>
            <p>סביבת מורה לניהול קבוצות תגבור אישיות, סימון נוכחות ויצירת דפי עבודה.</p>
        </div>

        <div class="login-card">
            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>שם משתמש <span class="required">*</span></label>
                    <input type="text" name="username" placeholder="הכנס שם משתמש" required>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label>סיסמה <span class="required">*</span></label>
                    <input type="password" name="password" placeholder="הכנס סיסמה" required>
                </div>

                <button type="submit" class="btn-submit">כניסה לסביבת מורה</button>
            </form>
        </div>

        <div class="login-footer">
            <a href="login.php" class="secondary-link">💼 כניסת הנהלה</a>
            <a href="student_login.php" class="secondary-link">🧑‍🎓 כניסת תלמידים</a>
        </div>

    </div>

</body>
</html>
