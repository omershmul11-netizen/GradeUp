<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

// שליפת מקצועות קיימים עבור הטופס
$subjectsQuery = "SELECT * FROM subjects ORDER BY subject_name ASC";
$subjectsStmt = $pdo->query($subjectsQuery);
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - שיבוץ חכם</title>

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
            position: -webkit-sticky;
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
            content: '⚡';
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
            max-width: 440px;
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
            position: relative;
            border: 1px solid #f3ebd1 !important;
            width: 100%;
            box-shadow:
                0 2px 4px rgba(0,0,0,0.01),
                0 8px 20px rgba(80, 60, 40, 0.06);
        }

        .card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333a42;
            margin-bottom: 15px;
            text-align: center;
        }

        label {
            display: block;
            margin-top: 14px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        .required {
            color: #c53030;
            font-weight: 700;
            margin-right: 4px;
        }

        select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 15px;
            background: #ffffff;
            color: #2d3748;
            outline: none;
            transition: all 0.2s ease;
        }

        select:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        .btn {
            display: block !important;
            width: 100% !important;
            padding: 14px !important;
            margin-top: 20px !important;
            background: #8fa3db !important;
            color: white !important;
            border: none !important;
            border-radius: 14px !important;
            text-align: center !important;
            cursor: pointer !important;
            font-weight: 500 !important;
            font-size: 16px !important;
            box-shadow: 0 5px 12px rgba(143, 163, 219, 0.4) !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none !important;
        }

        .btn:hover {
            background: #7b8fc7 !important;
            box-shadow: 0 7px 16px rgba(143, 163, 219, 0.6) !important;
            transform: translateY(-1px) !important;
        }

        .btn:disabled {
            background: #cbd5e1 !important;
            box-shadow: none !important;
            cursor: not-allowed !important;
            transform: none !important;
        }

        .btn.secondary {
            background: #718096 !important;
            box-shadow: 0 5px 12px rgba(113, 128, 150, 0.3) !important;
        }

        .btn.secondary:hover {
            background: #4a5568 !important;
            box-shadow: 0 7px 16px rgba(113, 128, 150, 0.5) !important;
        }

        .message {
            margin-top: 20px;
            padding: 14px;
            border-radius: 12px;
            display: none;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
        }

        .success {
            background: #f0fff4;
            color: #2f855a;
            border: 1px solid #c6f6d5;
        }

        .error {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        .info {
            background: #ebf8ff;
            color: #2b6cb0;
            border: 1px solid #bee3f8;
        }

        .summary {
            margin-top: 20px;
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 18px;
            display: none;
            line-height: 1.7;
            font-size: 14px;
        }

        .groups-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 25px;
            width: 100%;
        }

        .group-card {
            background: #ffffff;
            border: 1px solid #edf2f7;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .group-card h3 {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 10px;
            border-bottom: 2px solid #f7fafc;
            padding-bottom: 6px;
        }

        .group-meta {
            margin-bottom: 8px;
            font-size: 13px;
            color: #718096;
            line-height: 1.7;
        }

        .student-row {
            padding: 8px 0;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #4a5568;
        }

        .student-row:last-child {
            border-bottom: none;
        }

        .actions-row {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }

        @media (max-width: 1050px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
                max-width: 440px;
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

        @media (max-width: 480px) {
            body {
                padding: 20px 10px;
            }

            .card {
                padding: 22px 18px !important;
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
            <h1>שיבוץ חכם לקבוצות תגבור</h1>
            <p>
                המערכת מאתרת תלמידים מתאימים לפי שכבה ומקצוע, ומציעה חלוקה חכמה לקבוצות לפי מגבלות גודל, מורים וזמנים.
            </p>
        </div>
    </div>

    <div class="dashboard-layout">

        <div class="side-panel">
            <h3>פרופיל מחובר</h3>

            <p>
                שלום, <strong><?= htmlspecialchars($_SESSION['user']) ?></strong>
            </p>

            <p style="font-size: 12px; margin-top: 4px; color: #a0aec0;">
                סוג חשבון: רכז מערכת
            </p>
        </div>

        <div class="app-container">

            <div class="card">
                <h2>הגדרות שיבוץ אוטומטי</h2>

                <label>בחר שכבה <span class="required">*</span></label>
                <select id="gradeSelect">
                    <option value="">-- בחר שכבה --</option>
                    <option value="10">שכבה י'</option>
                    <option value="11">שכבה י"א</option>
                    <option value="12">שכבה י"ב</option>
                </select>

                <label>בחר מקצוע <span class="required">*</span></label>
                <select id="subjectSelect">
                    <option value="">-- בחר מקצוע --</option>

                    <?php foreach($subjects as $sub): ?>
                        <?php
                        $subjectNamesHebrew = [
                            'Math' => 'מתמטיקה',
                            'Mathematics' => 'מתמטיקה',
                            'English' => 'אנגלית',
                            'Hebrew' => 'עברית',
                            'Computer Science' => 'מדעי המחשב',
                            'ComputerScience' => 'מדעי המחשב',
                            'CS' => 'מדעי המחשב',
                            'Physics' => 'פיזיקה'
                        ];

                        $displayName = $subjectNamesHebrew[$sub['subject_name']] ?? $sub['subject_name'];
                        ?>

                        <option value="<?= htmlspecialchars($sub['subject_id']) ?>">
                            <?= htmlspecialchars($displayName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn" id="runBtn" onclick="runMatching(false)">
                    התחל שיבוץ
                </button>
            </div>

            <div id="msgBox" class="message"></div>

            <div id="summaryBox" class="summary"></div>

            <div id="resultsGrid" class="groups-grid"></div>

        </div>

        <div class="side-panel">
            <h3>כללי שיבוץ AI</h3>

            <p style="font-size: 13px; color:#718096; line-height:1.6;">
                השיבוץ מתבצע לפי שכבה ומקצוע בלבד.
                המערכת שואפת ליצור קבוצות של 8–10 תלמידים,
                לא לשבץ תלמיד באותה שעה בשתי קבוצות,
                ולא לעבור שני תגבורים ביום לתלמיד.
            </p>

            <p style="font-size: 13px; color:#718096; line-height:1.6; margin-top:10px;">
                בנוסף, המערכת מנסה לקבץ תלמידים עם מורים שלמדו איתם בעבר, ככל שהנתונים מאפשרים.
            </p>
        </div>

    </div>

<script>
let currentPreview = null;
let shuffleCounter = 0;

function showMessage(text, type) {
    const msgBox = document.getElementById('msgBox');
    msgBox.className = 'message ' + type;
    msgBox.innerHTML = text;
    msgBox.style.display = 'block';
}

function clearResults() {
    document.getElementById('summaryBox').style.display = 'none';
    document.getElementById('summaryBox').innerHTML = '';
    document.getElementById('resultsGrid').innerHTML = '';
    currentPreview = null;
}

function dayToHebrew(day) {
    const map = {
        'Sunday': 'ראשון',
        'Monday': 'שני',
        'Tuesday': 'שלישי',
        'Wednesday': 'רביעי',
        'Thursday': 'חמישי',
        'Friday': 'שישי',
        'Saturday': 'שבת'
    };

    return map[day] || day;
}

async function runMatching(isReshuffle) {
    const grade = document.getElementById('gradeSelect').value;
    const subjectId = document.getElementById('subjectSelect').value;

    if (!grade || !subjectId) {
        alert('יש לבחור גם שכבה וגם מקצוע');
        return;
    }

    if (isReshuffle) {
        shuffleCounter++;
    } else {
        shuffleCounter = 0;
    }

    const runBtn = document.getElementById('runBtn');
    runBtn.disabled = true;

    clearResults();

    showMessage('מריץ שיבוץ חכם מבוסס AI, אנא המתן... ⏳', 'info');

    const shuffleSeed = Date.now() + shuffleCounter;

    try {
        const response = await fetch(
            `api_preview_smart_groups.php?grade_level=${encodeURIComponent(grade)}&subject_id=${encodeURIComponent(subjectId)}&shuffle=${shuffleSeed}`
        );

        const result = await response.json();

        if (!result.success) {
            showMessage(result.message || 'שגיאה בהרצת השיבוץ.', 'error');
            runBtn.disabled = false;
            return;
        }

        if (!result.groups || result.groups.length === 0) {
            showMessage('לא נמצאו קבוצות מתאימות לשיבוץ.', 'info');
            runBtn.disabled = false;
            return;
        }

        currentPreview = result;

        showMessage(
            `השיבוץ הסתיים בהצלחה! נמצאו ${result.total_students} תלמידים מתאימים לשיבוץ.`,
            'success'
        );

        const summaryBox = document.getElementById('summaryBox');
        summaryBox.style.display = 'block';

        const sourceText = result.algorithm_source === 'gpt'
            ? 'שיבוץ מבוסס GPT'
            : 'שיבוץ חכם פנימי';

        summaryBox.innerHTML = `
            <strong>סיכום הצעה לשיבוץ:</strong><br>
            • סך הכל תלמידים שאותרו: ${result.total_students}<br>
            • כמות קבוצות מוצעת: <strong>${result.group_count} קבוצות תגבור</strong><br>
            • שיטת שיבוץ: ${sourceText}<br>
            • כלל חלוקה: עד 10 תלמידים בקבוצה, עדיפות ל־8–10 תלמידים ככל שניתן.<br>
            • שעות פעילות: בין 15:00 ל־19:00 בלבד.<br>
            • מגבלות תלמיד: אין שיבוץ כפול באותה שעה, ועד 2 תגבורים ביום.
        `;

        let gridHtml = '';

        result.groups.forEach((group, index) => {
            const dayHebrew = dayToHebrew(group.day_of_week);
            const startTime = group.start_time ? group.start_time.substring(0, 5) : '-';
            const endTime = group.end_time ? group.end_time.substring(0, 5) : '-';

            gridHtml += `
                <div class="group-card">
                    <h3>קבוצה מוצעת מספר ${index + 1}</h3>

                    <div class="group-meta">
                        <strong>מורה:</strong> ${group.teacher_name}<br>
                        <strong>כמות תלמידים:</strong> ${group.students.length}<br>
                        <strong>ממוצע ציונים:</strong> ${group.average_grade ?? '-'}<br>
                        <strong>מועד:</strong> יום ${dayHebrew}, ${startTime}–${endTime}
                    </div>
            `;

            group.students.forEach(st => {
                gridHtml += `
                    <div class="student-row">
                        • ${st.first_name} ${st.last_name} (${st.class_name}) - ציון נוכחי: ${st.latest_grade}
                    </div>
                `;
            });

            gridHtml += `</div>`;
        });

        gridHtml += `
            <div class="actions-row">
                <button class="btn" onclick="approveMatching(this)">אישור ופרסום שיבוץ במערכת</button>
                <button class="btn secondary" onclick="runMatching(true)">שיבוץ נוסף</button>
                <button class="btn secondary" style="background:#718096 !important;" onclick="cancelMatching()">ביטול</button>
            </div>
        `;

        document.getElementById('resultsGrid').innerHTML = gridHtml;

    } catch (e) {
        console.error(e);
        showMessage('שגיאת תקשורת בשרת בעת הרצת השיבוץ.', 'error');
    } finally {
        runBtn.disabled = false;
    }
}

async function approveMatching(btn) {
    if (!currentPreview) {
        alert('אין נתוני שיבוץ בתוקף');
        return;
    }

    if (!confirm('האם אתה בטוח שברצונך לאשר ולפתוח את קבוצות התגבור הללו במערכת?')) {
        return;
    }

    btn.disabled = true;
    btn.innerText = '⏳ שומר שיבוץ ושולח הודעות... נא להמתין';

    try {
        const response = await fetch('api_create_smart_groups.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(currentPreview)
        });

        const result = await response.json();

        if (result.success) {
            let message = 'השיבוץ פורסם ונשמר בהצלחה בבסיס הנתונים! ✅';

            if (result.mail_results) {
                const teacherEmails = result.mail_results.teacher_emails_sent ?? 0;
                const studentEmails = result.mail_results.student_emails_sent ?? 0;
                const parentEmails = result.mail_results.parent_emails_sent ?? 0;

                message += '\n\nמיילים למורים שנשלחו: ' + teacherEmails;
                message += '\nמיילים לתלמידים שנשלחו: ' + studentEmails;
                message += '\nמיילים להורים שנשלחו: ' + parentEmails;

                if (result.mail_results.failed_emails && result.mail_results.failed_emails.length > 0) {
                    message += '\n\nשימו לב: היו מיילים שלא נשלחו. ניתן לבדוק בשרת או בלוגים.';
                }
            }

            alert(message);
            window.location.href = 'index.php';

        } else {
            alert('שגיאה בשמירת השיבוץ: ' + (result.error || result.message));
            btn.disabled = false;
            btn.innerText = 'אישור ופרסום שיבוץ במערכת';
        }

    } catch (e) {
        console.error(e);
        alert('שגיאת תקשורת בשמירת השיבוץ.');
        btn.disabled = false;
        btn.innerText = 'אישור ופרסום שיבוץ במערכת';
    }
}

function cancelMatching() {
    if (confirm('לבטל את השיבוץ המוצע?')) {
        currentPreview = null;
        document.getElementById('msgBox').style.display = 'none';
        document.getElementById('summaryBox').style.display = 'none';
        document.getElementById('summaryBox').innerHTML = '';
        document.getElementById('resultsGrid').innerHTML = '';
    }
}
</script>

</body>
</html>
