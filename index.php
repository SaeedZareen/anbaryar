<?php
session_start();
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: installer.php');
    exit;
}

require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/plugin.php';

ensure_csrf_token();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            set_flash('error', 'نام کاربری و رمز عبور الزامی است.');
        } elseif (!login($username, $password)) {
            set_flash('error', 'ورود ناموفق بود. لطفاً اطلاعات را بررسی کنید.');
        } else {
            redirect('index.php');
        }
    }
}

$user = current_user();

if (!is_logged_in()) {
    $messages = get_flash();
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>ورود به سامانه انباریار</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;500;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Vazirmatn', sans-serif;
                background: linear-gradient(135deg, #1f3c88 0%, #39a2db 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                margin: 0;
            }
            .login-card {
                background: rgba(255, 255, 255, 0.95);
                padding: 40px 48px;
                border-radius: 18px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.15);
                width: 420px;
                max-width: 90%;
            }
            h1 {
                margin-top: 0;
                text-align: center;
                color: #1f3c88;
            }
            label {
                display: block;
                margin-bottom: 10px;
                color: #1f1f1f;
            }
            input[type="text"], input[type="password"] {
                width: 100%;
                padding: 12px 16px;
                border: 1px solid #d0d7ff;
                border-radius: 10px;
                margin-bottom: 18px;
                font-size: 16px;
                background: #f5f7ff;
            }
            input:focus {
                border-color: #39a2db;
                outline: none;
            }
            button {
                width: 100%;
                padding: 12px 16px;
                border: none;
                border-radius: 10px;
                background: #1f3c88;
                color: #fff;
                font-size: 18px;
                cursor: pointer;
            }
            .alert {
                background: rgba(220, 53, 69, 0.15);
                color: #842029;
                border-radius: 10px;
                padding: 12px 14px;
                margin-bottom: 16px;
            }
        </style>
    </head>
    <body>
    <div class="login-card">
        <h1>ورود به انباریار</h1>
        <?php foreach ($messages as $message): ?>
            <div class="alert"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <label for="username">نام کاربری</label>
            <input type="text" id="username" name="username" required>

            <label for="password">رمز عبور</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" name="login" value="1">ورود</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = db();
$pluginManager = new PluginManager();
$pluginManager->loadPlugins(__DIR__ . '/plugins');

$activePlugins = [];
try {
    $stmt = $pdo->query('SELECT slug FROM system_plugins WHERE is_active = 1');
    $activePlugins = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // ignore
}

$plugins = array_filter(
    $pluginManager->getPlugins(),
    fn($plugin) => in_array($plugin->getSlug(), $activePlugins ?: [$plugin->getSlug()], true)
);

$pluginSlug = $_GET['plugin'] ?? (array_key_first($plugins) ?: null);
$action = $_GET['action'] ?? 'index';
$currentPlugin = $pluginSlug ? ($plugins[$pluginSlug] ?? null) : null;

$messages = get_flash();

function render_menu(array $plugins, ?string $currentSlug): string
{
    $items = [];
    foreach ($plugins as $plugin) {
        foreach ($plugin->getMenu() as $menuItem) {
            $isActive = str_contains($menuItem['url'], 'plugin=' . $plugin->getSlug());
            $items[] = sprintf(
                '<a href="%s" class="menu-item %s">%s <span>%s</span></a>',
                e($menuItem['url']),
                $currentSlug === $plugin->getSlug() ? 'active' : '',
                $menuItem['icon'] ?? '📁',
                e($menuItem['label'])
            );
        }
    }
    return implode('', $items);
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>داشبورد انباریار</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Vazirmatn', sans-serif;
            background: #f2f5ff;
            color: #1f1f1f;
        }
        .layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: #1f3c88;
            color: #fff;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
        }
        .sidebar h2 {
            margin: 0 0 24px;
            font-size: 22px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: background 0.2s ease;
            font-size: 15px;
        }
        .menu-item span {
            margin-right: 8px;
        }
        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .sidebar .user-info {
            margin-top: auto;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 32px 40px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .topbar h1 {
            margin: 0;
            font-size: 24px;
            color: #1f3c88;
        }
        .logout {
            color: #d32f2f;
            text-decoration: none;
            font-weight: 600;
        }
        .flash {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
        }
        .flash.success {
            background: rgba(25, 135, 84, 0.12);
            color: #0f5132;
        }
        .flash.error {
            background: rgba(220, 53, 69, 0.12);
            color: #842029;
        }
        .warning {
            background: rgba(255, 193, 7, 0.18);
            color: #8a6d3b;
            padding: 16px 18px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <h2>انباریار</h2>
        <?= render_menu($plugins, $pluginSlug) ?>
        <div class="user-info">
            <div><?= e($user['full_name'] ?? '') ?></div>
            <div style="font-size:13px;opacity:0.8;">نقش: <?= e($user['role'] ?? '') ?></div>
            <?php if (!empty($user['warehouse_name'])): ?>
                <div style="font-size:13px;opacity:0.8;">انبار: <?= e($user['warehouse_name']) ?></div>
            <?php endif; ?>
        </div>
    </aside>
    <main class="content">
        <div class="topbar">
            <h1><?= $currentPlugin ? e($currentPlugin->getName()) : 'داشبورد' ?></h1>
            <a class="logout" href="?action=logout">خروج</a>
        </div>
        <?php foreach ($messages as $message): ?>
            <div class="flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <div class="module-content">
            <?php
            if ($currentPlugin) {
                echo $currentPlugin->handle($action);
            } else {
                echo '<p>هیچ افزونه‌ای فعال نشده است.</p>';
            }
            ?>
        </div>
    </main>
</div>
</body>
</html>
