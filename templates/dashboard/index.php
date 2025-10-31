<?php
/** @var array $messages */
/** @var array<string, Plugin> $plugins */
/** @var Plugin|null $currentPlugin */
/** @var string|null $currentSlug */
/** @var string $content */
/** @var array|null $user */
/** @var string $action */

$menuItems = [];
foreach ($plugins as $plugin) {
    foreach ($plugin->getMenu() as $item) {
        $isPluginActive = $currentSlug === $plugin->getSlug();
        $isItemActive = $isPluginActive && str_contains($item['url'], 'action=' . $action);
        if (!$isItemActive && $isPluginActive && !str_contains($item['url'], 'action=')) {
            $isItemActive = true;
        }
        $menuItems[] = [
            'label' => $item['label'],
            'url' => $item['url'],
            'icon' => $item['icon'] ?? '📁',
            'slug' => $plugin->getSlug(),
            'active' => $isItemActive,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>داشبورد انباریار</title>
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
            --bg-muted: #f4f6fb;
            --sidebar-width: 260px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Vazirmatn', sans-serif;
            background: var(--bg-muted);
            color: #202124;
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, rgba(39,70,144,0.95), rgba(87,108,168,0.92));
            color: #fff;
            padding: 32px 24px 24px;
            display: flex;
            flex-direction: column;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 32px;
        }
        .brand span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.18);
            font-size: 22px;
        }
        .menu {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }
        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .menu a.active {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            transform: translateX(-4px);
        }
        .menu a:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .menu .section-title {
            margin: 20px 0 8px;
            font-size: 13px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            opacity: 0.65;
        }
        .logout {
            margin-top: auto;
            padding-top: 24px;
        }
        .logout a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
        }
        .logout a:hover { background: rgba(255, 255, 255, 0.2); }
        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 32px;
            background: #fff;
            box-shadow: 0 1px 0 rgba(17, 24, 39, 0.08);
        }
        .topbar h1 {
            margin: 0;
            font-size: 22px;
            color: var(--brand-primary);
        }
        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            border-radius: 12px;
            background: rgba(39, 70, 144, 0.08);
            color: var(--brand-primary);
            font-weight: 600;
        }
        .main-panel {
            flex: 1;
            padding: 32px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .alerts {
            display: grid;
            gap: 12px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
        }
        .alert.success {
            background: rgba(27, 153, 139, 0.12);
            border: 1px solid rgba(27, 153, 139, 0.24);
            color: #136f63;
        }
        .alert.error {
            background: rgba(220, 53, 69, 0.12);
            border: 1px solid rgba(220, 53, 69, 0.24);
            color: #9b1c31;
        }
        .panel {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 18px 35px rgba(15, 31, 62, 0.08);
            min-height: 200px;
        }
        .empty-state {
            text-align: center;
            padding: 48px 16px;
            color: #6b7280;
            font-size: 16px;
        }
        @media (max-width: 960px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                flex-direction: row;
                align-items: center;
                gap: 16px;
                overflow-x: auto;
            }
            .sidebar .menu {
                flex-direction: row;
                flex-wrap: nowrap;
                gap: 12px;
            }
            .sidebar .menu a {
                flex: 0 0 auto;
            }
            .logout {
                margin: 0 0 0 16px;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand"><span>📦</span>انباریار</div>
        <div class="menu">
            <?php if (!$menuItems): ?>
                <div class="section-title">افزونه فعال وجود ندارد</div>
            <?php else: ?>
                <?php foreach ($menuItems as $item): ?>
                    <a href="<?php echo e($item['url']); ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>">
                        <span><?php echo $item['icon']; ?></span>
                        <span><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="logout">
            <a href="?action=logout">خروج</a>
        </div>
    </aside>
    <div class="content">
        <header class="topbar">
            <h1><?php echo $currentPlugin ? e($currentPlugin->getName()) : 'داشبورد انباریار'; ?></h1>
            <?php if ($user): ?>
                <div class="user-badge">
                    <span>👤</span>
                    <div>
                        <div><?php echo e($user['full_name'] ?? $user['username']); ?></div>
                        <?php if (!empty($user['warehouse_name'])): ?>
                            <small><?php echo e($user['warehouse_name']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </header>
        <main class="main-panel">
            <?php if (!empty($messages)): ?>
                <div class="alerts">
                    <?php foreach ($messages as $message): ?>
                        <div class="alert <?php echo e($message['type']); ?>"><?php echo e($message['message']); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="panel">
                <?php echo $content; ?>
            </div>
        </main>
    </div>
</body>
</html>
