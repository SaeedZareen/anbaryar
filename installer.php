<?php
session_start();
$installed = file_exists(__DIR__ . '/config.php');
if ($installed) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');
    $baseUrl = trim($_POST['base_url'] ?? '/');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        $errors[] = 'لطفاً اطلاعات پایگاه داده را کامل وارد کنید.';
    }
    if ($adminName === '' || $adminUsername === '' || $adminPassword === '') {
        $errors[] = 'اطلاعات مدیر سیستم ضروری است.';
    }
    if ($adminPassword !== $adminPasswordConfirm) {
        $errors[] = 'رمز عبور و تکرار آن یکسان نیست.';
    }

    if (!$errors) {
        try {
            $dsnWithoutDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbHost, $dbPort ?: '3306');
            $pdo = new PDO($dsnWithoutDb, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $e) {
            $errors[] = 'اتصال به پایگاه داده با خطا مواجه شد: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort ?: '3306', $dbName);
            $db = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $schemaSql))) as $statement) {
                if ($statement === '') {
                    continue;
                }
                $db->exec($statement);
            }

            $stmt = $db->prepare('INSERT INTO warehouses (name, type) VALUES (:name, :type)');
            $warehouses = [
                ['مواد اولیه', 'raw_material'],
                ['محصولات', 'product'],
                ['ملزومات', 'supplies'],
            ];
            foreach ($warehouses as $warehouse) {
                $stmt->execute(['name' => $warehouse[0], 'type' => $warehouse[1]]);
            }

            $adminWarehouseId = null;
            $stmtWarehouse = $db->prepare('SELECT id FROM warehouses WHERE type = :type LIMIT 1');
            $stmtWarehouse->execute(['type' => 'product']);
            $adminWarehouseId = $stmtWarehouse->fetchColumn();

            $stmtUser = $db->prepare('INSERT INTO users (full_name, username, password, role, warehouse_id) VALUES (:full_name, :username, :password, :role, :warehouse_id)');
            $stmtUser->execute([
                'full_name' => $adminName,
                'username' => $adminUsername,
                'password' => password_hash($adminPassword, PASSWORD_BCRYPT),
                'role' => 'admin',
                'warehouse_id' => $adminWarehouseId,
            ]);

            $db->prepare('INSERT INTO system_plugins (slug, name, is_active) VALUES (:slug, :name, 1)')
                ->execute(['slug' => 'products', 'name' => 'انبار محصولات']);

            $configContent = "<?php\nreturn [\n    'db' => [\n        'host' => '" . addslashes($dbHost) . "',\n        'port' => '" . addslashes($dbPort ?: '3306') . "',\n        'database' => '" . addslashes($dbName) . "',\n        'username' => '" . addslashes($dbUser) . "',\n        'password' => '" . addslashes($dbPass) . "',\n        'charset' => 'utf8mb4',\n    ],\n    'app' => [\n        'base_url' => '" . addslashes($baseUrl ?: '/') . "',\n    ],\n];\n";

            if (!file_put_contents(__DIR__ . '/config.php', $configContent)) {
                $errors[] = 'نوشتن فایل پیکربندی با مشکل مواجه شد.';
            }
        } catch (PDOException $e) {
            $errors[] = 'اجرای دستورات پایگاه داده با خطا مواجه شد: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>نصب انباریار</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: #f4f6fb;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 720px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 40px 48px;
        }
        h1 {
            margin-top: 0;
            color: #1f3c88;
            text-align: center;
            font-size: 28px;
        }
        .step {
            margin-bottom: 32px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2f2f2f;
        }
        input[type="text"],
        input[type="password"],
        input[type="url"],
        input[type="number"] {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #ccd6f6;
            background: #f9fbff;
            transition: border-color 0.2s ease;
        }
        input:focus {
            border-color: #1f3c88;
            outline: none;
        }
        button {
            width: 100%;
            padding: 14px 18px;
            border: none;
            border-radius: 12px;
            background: #1f3c88;
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        button:hover {
            background: #162c5b;
        }
        .alert {
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .alert.error {
            background: rgba(220, 53, 69, 0.1);
            color: #842029;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .alert.success {
            background: rgba(25, 135, 84, 0.1);
            color: #0f5132;
            border: 1px solid rgba(25, 135, 84, 0.3);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 24px;
            color: #555;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>نصب هوشمند سامانه انباریار</h1>
    <p style="text-align:center;color:#455a64">برای شروع آماده‌ایم؛ اطلاعات را با دقت وارد کنید تا یک انبار حرفه‌ای بسازیم.</p>
    <?php if ($errors): ?>
        <div class="alert error">
            <ul style="margin:0;padding-right:20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert success">
            نصب با موفقیت انجام شد!<br>
            <a href="index.php" style="color:#0f5132;font-weight:600;">ورود به سامانه</a>
        </div>
    <?php else: ?>
    <form method="post">
        <div class="step">
            <h2 style="color:#1f3c88;font-size:22px;">گام اول: اتصال به پایگاه داده</h2>
            <div class="grid">
                <div>
                    <label for="db_host">هاست پایگاه داده</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label for="db_port">پورت</label>
                    <input type="number" id="db_port" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="db_name">نام پایگاه داده</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label for="db_user">نام کاربری</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label for="db_pass">رمز عبور</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="base_url">آدرس پایه سامانه</label>
                    <input type="text" id="base_url" name="base_url" placeholder="مثلاً https://whm.niksarang.ir/anbaryar" value="<?= htmlspecialchars($_POST['base_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </div>
        <div class="step">
            <h2 style="color:#1f3c88;font-size:22px;">گام دوم: ساخت مدیر سامانه</h2>
            <div class="grid">
                <div>
                    <label for="admin_name">نام و نام خانوادگی</label>
                    <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label for="admin_username">نام کاربری مدیر</label>
                    <input type="text" id="admin_username" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label for="admin_password">رمز عبور</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                <div>
                    <label for="admin_password_confirm">تکرار رمز عبور</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                </div>
            </div>
        </div>
        <button type="submit">شروع نصب</button>
    </form>
    <?php endif; ?>
    <div class="footer">
        نسخه اولیه سامانه انبار محصولات - کاملاً پلاگین‌محور و قابل توسعه
    </div>
</div>
</body>
</html>
