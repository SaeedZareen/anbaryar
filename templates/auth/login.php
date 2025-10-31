<?php
/** @var array $messages */
/** @var string $csrfToken */
/** @var array $old */
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود به سامانه انباریار</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --brand-primary: #274690;
            --brand-secondary: #576ca8;
            --brand-accent: #1b998b;
            --surface: rgba(255, 255, 255, 0.92);
            --radius-lg: 22px;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Vazirmatn', sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(39, 70, 144, 0.55), rgba(87, 108, 168, 0.25)), linear-gradient(135deg, #0d324d, #7f5a83);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
            color: #1d1d1f;
        }
        .auth-card {
            width: min(440px, 100%);
            padding: 40px 48px;
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: 0 24px 48px rgba(15, 31, 62, 0.2);
            backdrop-filter: blur(4px);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .auth-header h1 {
            margin: 0 0 8px;
            color: var(--brand-primary);
            font-size: 26px;
            font-weight: 700;
        }
        .auth-header p {
            margin: 0;
            color: #3f3f44;
            font-size: 14px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(39, 70, 144, 0.25);
            border-radius: 12px;
            background: rgba(255,255,255,0.85);
            font-size: 16px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 22px;
        }
        input:focus {
            border-color: var(--brand-accent);
            outline: none;
            box-shadow: 0 0 0 4px rgba(27, 153, 139, 0.15);
        }
        button {
            width: 100%;
            padding: 14px 18px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #fff;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(39, 70, 144, 0.25);
        }
        .alerts {
            margin-bottom: 24px;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #a3172f;
            font-size: 14px;
            margin-bottom: 10px;
        }
        @media (max-width: 520px) {
            .auth-card {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-header">
        <h1>ورود به انباریار</h1>
        <p>برای دسترسی به داشبورد ابتدا وارد حساب کاربری شوید.</p>
    </div>
    <?php if (!empty($messages)): ?>
        <div class="alerts">
            <?php foreach ($messages as $message): ?>
                <div class="alert"><?php echo e($message['message']); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <label for="username">نام کاربری</label>
        <input type="text" id="username" name="username" value="<?php echo e($old['username'] ?? ''); ?>" required autofocus>

        <label for="password">رمز عبور</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" name="login" value="1">ورود</button>
    </form>
</div>
</body>
</html>
