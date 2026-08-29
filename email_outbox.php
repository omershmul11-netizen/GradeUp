<?php
session_start();

if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_config.php';

$statement = $pdo->query(
    'SELECT email_id, recipient_email, recipient_name, subject, delivery_mode, status, created_at '
    . 'FROM email_outbox ORDER BY email_id DESC LIMIT 100'
);
$messages = $statement->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - תיבת הודעות הדגמה</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
    <style>
        body { background: #fcdbd1; color: #2d3748; padding: 28px 16px; }
        .outbox-shell { width: min(1080px, 100%); margin: 0 auto; }
        .outbox-header { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 20px; }
        .outbox-header h1 { margin: 0; color: #4a5153; }
        .back-link { color: #5b6fb4; text-decoration: none; font-weight: 700; }
        .demo-note { background: #fff9e6; border: 1px solid #f3ebd1; border-radius: 16px; padding: 16px; margin-bottom: 18px; line-height: 1.6; }
        .message-list { display: grid; gap: 12px; }
        .message-card { background: white; border: 1px solid #edf2f7; border-radius: 16px; padding: 18px; box-shadow: 0 8px 24px rgba(80,60,40,.06); }
        .message-card h2 { font-size: 17px; margin: 0 0 8px; color: #333a42; }
        .message-meta { display: flex; flex-wrap: wrap; gap: 8px 16px; color: #718096; font-size: 13px; }
        .status { display: inline-block; background: #e6fffa; color: #276749; padding: 4px 9px; border-radius: 999px; font-weight: 700; }
        .empty { text-align: center; background: white; border-radius: 16px; padding: 40px 20px; color: #718096; }
    </style>
</head>
<body class="gradeup-ui">
<main class="outbox-shell">
    <header class="outbox-header">
        <div>
            <h1>תיבת הודעות הדגמה</h1>
            <p>הודעות שנוצרו על ידי שיבוצים, משימות ודיווחי נוכחות</p>
        </div>
        <a class="back-link" href="index.php">חזרה למסך הראשי</a>
    </header>

    <div class="demo-note">
        במצב ההדגמה ההודעות נשמרות כאן ואינן נשלחות לכתובות אמיתיות. בסביבת ייצור ניתן לחבר את Brevo באמצעות משתני סביבה פרטיים.
    </div>

    <?php if (!$messages): ?>
        <div class="empty">עדיין לא נוצרו הודעות. צרו שיבוץ חכם או משימה חדשה כדי לראות את התהליך.</div>
    <?php else: ?>
        <section class="message-list" aria-label="הודעות אחרונות">
            <?php foreach ($messages as $message): ?>
                <article class="message-card">
                    <h2><?= htmlspecialchars($message['subject']) ?></h2>
                    <div class="message-meta">
                        <span><strong>אל:</strong> <?= htmlspecialchars($message['recipient_name'] ?: 'ללא שם') ?> · <?= htmlspecialchars($message['recipient_email']) ?></span>
                        <span><strong>נוצר:</strong> <?= htmlspecialchars($message['created_at']) ?></span>
                        <span class="status"><?= $message['delivery_mode'] === 'demo' ? 'הודגם בהצלחה' : htmlspecialchars($message['status']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
