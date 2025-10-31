<?php
require_once __DIR__ . '/../../core/plugin.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/auth.php';

class ProductInventoryPlugin extends Plugin
{
    private PDO $pdo;
    private ?int $warehouseId = null;

    private array $brands = [
        'ندارد',
        'آدرینا',
        'نیک سارنگ',
        'نیک الوان',
        'win color',
        'آبادیس',
    ];

    private array $containers = [
        'مخزن ibc',
        'گالن نیمه براق',
        'گالن مات',
        'گالن صادراتی ارتفاع کوتاه',
        'گالن صادرات ارتفاع بلند',
        'گالن چهارگوش 4.5 لیتری',
        'گالن تمام تخلیه درب رینگی',
        'گالن تمام تخلیه',
        'گالن براق',
        'گالن آستر طوسی',
        'گالن اخرا',
        'گالن 4 لیتری بدنه پلاستیکی',
        'گالن',
        'کوارت نیمه براق',
        'کوارت مات',
        'کوارت براق',
        'کوارت آستر طوسی',
        'کوارت آستر اخرا',
        'کفی پلاستیکی گالن',
        'کفی پلاستیکی کوارت',
        'کارتن هاردنر 4 لیتری 4 عددی لمینتی',
        'کارتن هاردنر 4 لیتری بدون چاپ',
        'کارتن هاردنر 1 لیتری 12 عددی لمینتی',
        'کارتن گالن 4 عددی لمینتی',
        'کارتن گالن 4 عددی',
        'کارتن تینر 4 لیتری لمینتی',
        'کارتن تینر 1 لیتری لمینتی',
        'قوطی 1 کیلویی فلزی',
        'قوطی نیمی فلزی',
        'قوطی کتابی 1 لیتری بدون چاپ',
        'قوطی ربعی',
        'قوطی چهارگوش 1 لیتری',
        'شیرینگ 80*65',
        'شیرینگ 80*62',
        'شیرینگ 70*57',
        'سطل گرد 3/3 لیتری (RP2)',
        'سطل گرد 1 لیتری',
        'حلب نیمه براق',
        'حلب روغنی صنعتی',
        'حلب بدون چاپ صادراتی',
        'حلب',
        '4لیتری کتابی (بدون چاپ)',
        'پلمپ پلاستیکی درب حلب',
        'پت 4 لیتری کتابی',
        'پت 3 لیتری کتابی',
        'پت 1 لیتری کتابی',
        'بشکه تینر',
        'بشکه',
        '20لیتری',
    ];

    private array $packagingTypes = [
        'ندارد',
        'کارتن',
        'کفی',
        'شرینک',
        'استرچ پالت',
        'چیدمان روی پالت',
        'تسمه کشی',
    ];

    private array $categories = [
        'رنگ',
        'خورده بار',
        'بدون انقضا',
    ];

    public function __construct()
    {
        $this->pdo = db();
        $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE type = :type LIMIT 1');
        $stmt->execute(['type' => 'product']);
        $this->warehouseId = (int) $stmt->fetchColumn();
    }

    public function getSlug(): string
    {
        return 'products';
    }

    public function getName(): string
    {
        return 'انبار محصولات';
    }

    public function getMenu(): array
    {
        return [
            [
                'label' => 'انبار محصولات',
                'url' => '?plugin=' . $this->getSlug(),
                'icon' => '📦',
            ],
            [
                'label' => 'ورود کالا',
                'url' => '?plugin=' . $this->getSlug() . '&action=create',
                'icon' => '➕',
            ],
        ];
    }

    public function handle(string $action): string
    {
        $user = current_user();
        if (!user_can_access_warehouse($user, 'product')) {
            return '<div class="warning">شما به این بخش دسترسی ندارید.</div>';
        }

        return match ($action) {
            'create' => $this->handleCreate(),
            'exit' => $this->handleExit(),
            'logs' => $this->renderLogs(),
            default => $this->renderList(),
        };
    }

    private function handleCreate(): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
                set_flash('error', 'نشست شما منقضی شده است.');
                redirect(current_url(['action' => 'create']));
            }

            $data = $this->sanitizeInventoryInput($_POST);
            $errors = $this->validateInventoryInput($data);

            if (empty($errors)) {
                $entryDate = jalali_to_gregorian_date($data['entry_date']);
                $expiryDate = jalali_to_gregorian_date($data['expiry_date']);
                $totalWeight = $data['total_weight'] ?: ($data['entry_count'] * $data['weight_unit']);
                $user = current_user();

                try {
                    $stmt = $this->pdo->prepare('INSERT INTO product_inventory
                        (warehouse_id, code, name, brand, package, batch, location, entry_date, entry_count, weight_unit, total_weight, expiry_date, exit_date, exit_count, category, packaging_type, notes, created_by)
                        VALUES (:warehouse_id, :code, :name, :brand, :package, :batch, :location, :entry_date, :entry_count, :weight_unit, :total_weight, :expiry_date, NULL, NULL, :category, :packaging_type, :notes, :created_by)
                    ');
                    $stmt->execute([
                        'warehouse_id' => $this->warehouseId,
                        'code' => $data['code'],
                        'name' => $data['name'],
                        'brand' => $data['brand'],
                        'package' => $data['package'],
                        'batch' => $data['batch'],
                        'location' => $data['location'],
                        'entry_date' => $entryDate,
                        'entry_count' => $data['entry_count'],
                        'weight_unit' => $data['weight_unit'],
                        'total_weight' => $totalWeight,
                        'expiry_date' => $expiryDate,
                        'category' => $data['category'],
                        'packaging_type' => $data['packaging_type'],
                        'notes' => $data['notes'],
                        'created_by' => $user['id'],
                    ]);

                    $inventoryId = (int) $this->pdo->lastInsertId();
                    $this->logAction($inventoryId, 'entry', 'ثبت ورود کالا توسط ' . ($user['full_name'] ?? ''));

                    set_flash('success', 'ورود کالا با موفقیت ثبت شد.');
                    redirect('?plugin=' . $this->getSlug());
                } catch (PDOException $e) {
                    $errors[] = 'خطا در ذخیره‌سازی اطلاعات: ' . $e->getMessage();
                }
            }

            if ($errors) {
                set_flash('error', implode(' | ', $errors));
                $_SESSION['form_data'] = $data;
                redirect(current_url(['action' => 'create']));
            }
        }

        $formData = $_SESSION['form_data'] ?? [
            'entry_date' => gregorian_to_jalali_date(date('Y-m-d')),
        ];
        unset($_SESSION['form_data']);

        ob_start();
        ?>
        <style>
            .card {
                background: #fff;
                border-radius: 16px;
                padding: 24px 28px;
                box-shadow: 0 10px 25px rgba(31,60,136,0.08);
                margin-bottom: 20px;
            }
            .form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 20px;
            }
            label {
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
                color: #1f3c88;
            }
            input[type="text"], input[type="number"], select, textarea {
                width: 100%;
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid #d0d7ff;
                background: #f7f9ff;
                font-family: inherit;
            }
            textarea { min-height: 100px; }
            .actions {
                display: flex;
                justify-content: flex-end;
                margin-top: 20px;
            }
            .btn {
                background: #1f3c88;
                color: #fff;
                padding: 12px 18px;
                border-radius: 10px;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-size: 15px;
            }
            .btn-secondary {
                background: #d0d7ff;
                color: #1f3c88;
                margin-left: 12px;
            }
        </style>
        <div class="card">
            <h2 style="margin-top:0;color:#1f3c88;">فرم ورود کالا</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="form-grid">
                    <?= $this->renderInput('code', 'کد کالا', $formData['code'] ?? '', true) ?>
                    <?= $this->renderInput('name', 'عنوان کالا', $formData['name'] ?? '', true) ?>
                    <?= $this->renderSelect('brand', 'برند', $this->brands, $formData['brand'] ?? '') ?>
                    <?= $this->renderSelect('package', 'نوع ظرف', $this->containers, $formData['package'] ?? '') ?>
                    <?= $this->renderSelect('packaging_type', 'نوع بسته‌بندی', $this->packagingTypes, $formData['packaging_type'] ?? '') ?>
                    <?= $this->renderInput('batch', 'شماره بچ', $formData['batch'] ?? '', true) ?>
                    <?= $this->renderInput('location', 'موقعیت در انبار', $formData['location'] ?? '', true) ?>
                    <?= $this->renderInput('entry_date', 'تاریخ ورود (جلالی)', $formData['entry_date'] ?? '', true) ?>
                    <?= $this->renderNumber('entry_count', 'تعداد ورودی', $formData['entry_count'] ?? '', true) ?>
                    <?= $this->renderNumber('weight_unit', 'وزن هر واحد (کیلوگرم)', $formData['weight_unit'] ?? '', true) ?>
                    <?= $this->renderNumber('total_weight', 'مجموع وزن (در صورت نیاز)', $formData['total_weight'] ?? '') ?>
                    <?= $this->renderInput('expiry_date', 'تاریخ انقضا (جلالی)', $formData['expiry_date'] ?? '') ?>
                    <?= $this->renderSelect('category', 'نوع شیت', $this->categories, $formData['category'] ?? '') ?>
                </div>
                <div style="margin-top:16px;">
                    <label for="notes">توضیحات</label>
                    <textarea name="notes" id="notes"><?= e($formData['notes'] ?? '') ?></textarea>
                </div>
                <div class="actions">
                    <a class="btn btn-secondary" href="?plugin=<?= $this->getSlug() ?>">بازگشت</a>
                    <button class="btn" type="submit">ثبت ورود</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function handleExit(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $record = $this->fetchInventory($id);
        if (!$record) {
            return '<div class="warning">رکورد مورد نظر یافت نشد.</div>';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!check_csrf_token($_POST['csrf_token'] ?? '')) {
                set_flash('error', 'نشست شما منقضی شده است.');
                redirect(current_url());
            }

            $exitCount = (float) ($_POST['exit_count'] ?? 0);
            $exitDate = jalali_to_gregorian_date($_POST['exit_date'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($exitCount <= 0) {
                set_flash('error', 'تعداد خروج باید بزرگتر از صفر باشد.');
                redirect(current_url());
            }
            if ($exitCount > $record['entry_count']) {
                set_flash('error', 'تعداد خروج نمی‌تواند بیشتر از تعداد ورودی باشد.');
                redirect(current_url());
            }

            try {
                $user = current_user();
                $stmt = $this->pdo->prepare('UPDATE product_inventory SET exit_count = :exit_count, exit_date = :exit_date, notes = :notes, updated_by = :updated_by WHERE id = :id');
                $stmt->execute([
                    'exit_count' => $exitCount,
                    'exit_date' => $exitDate,
                    'notes' => $notes,
                    'updated_by' => $user['id'],
                    'id' => $id,
                ]);
                $this->logAction($id, 'exit', 'ثبت خروج کالا به تعداد ' . $exitCount . ' توسط ' . ($user['full_name'] ?? ''));
                set_flash('success', 'خروج کالا ثبت شد.');
                redirect('?plugin=' . $this->getSlug());
            } catch (PDOException $e) {
                set_flash('error', 'خطا در ثبت خروج: ' . $e->getMessage());
                redirect(current_url());
            }
        }

        ob_start();
        ?>
        <style>
            .card {
                background: #fff;
                border-radius: 16px;
                padding: 24px 28px;
                box-shadow: 0 10px 25px rgba(31,60,136,0.08);
                margin-bottom: 20px;
            }
            label {
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
                color: #1f3c88;
            }
            input[type="text"], input[type="number"], textarea {
                width: 100%;
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid #d0d7ff;
                background: #f7f9ff;
                font-family: inherit;
            }
            textarea { min-height: 100px; }
            .btn {
                background: #1f3c88;
                color: #fff;
                padding: 12px 18px;
                border-radius: 10px;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-size: 15px;
            }
            .btn-secondary {
                background: #d0d7ff;
                color: #1f3c88;
                margin-left: 12px;
            }
        </style>
        <div class="card">
            <h2 style="margin-top:0;color:#1f3c88;">ثبت خروج برای <?= e($record['name']) ?></h2>
            <p style="color:#607d8b;">موجودی ورودی: <?= e($record['entry_count']) ?> | خروجی ثبت شده: <?= e($record['exit_count'] ?? 0) ?></p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <?= $this->renderNumber('exit_count', 'تعداد خروج', '', true) ?>
                <?= $this->renderInput('exit_date', 'تاریخ خروج (جلالی)', gregorian_to_jalali_date($record['exit_date']) ?? gregorian_to_jalali_date(date('Y-m-d')), true) ?>
                <label for="notes">توضیحات</label>
                <textarea name="notes" id="notes" style="width:100%;min-height:100px;"><?= e($record['notes'] ?? '') ?></textarea>
                <div class="actions" style="margin-top:16px;">
                    <a class="btn btn-secondary" href="?plugin=<?= $this->getSlug() ?>">بازگشت</a>
                    <button class="btn" type="submit">ثبت خروج</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderLogs(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $record = $this->fetchInventory($id);
        if (!$record) {
            return '<div class="warning">رکورد مورد نظر یافت نشد.</div>';
        }

        $stmt = $this->pdo->prepare('SELECT l.*, u.full_name FROM product_inventory_logs l LEFT JOIN users u ON u.id = l.performed_by WHERE l.inventory_id = :id ORDER BY l.created_at DESC');
        $stmt->execute(['id' => $id]);
        $logs = $stmt->fetchAll();

        ob_start();
        ?>
        <style>
            .card { background:#fff;border-radius:16px;padding:20px 24px;box-shadow:0 8px 24px rgba(31,60,136,0.08); margin-bottom: 20px; }
            table { width:100%; border-collapse: collapse; }
            th, td { padding: 12px; text-align: right; }
            thead { background: #eef2ff; color: #1f3c88; }
            tbody tr:nth-child(even) { background: #f9fbff; }
            .btn {
                background: #1f3c88;
                color: #fff;
                padding: 10px 16px;
                border-radius: 10px;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-size: 14px;
            }
            .btn-secondary {
                background: #d0d7ff;
                color: #1f3c88;
                margin-left: 12px;
            }
        </style>
        <div class="card">
            <h2 style="margin-top:0;color:#1f3c88;">لاگ‌های <?= e($record['name']) ?></h2>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f0f3ff;color:#1f3c88;">
                        <th style="padding:12px;text-align:right;">تاریخ</th>
                        <th style="padding:12px;text-align:right;">اقدام</th>
                        <th style="padding:12px;text-align:right;">توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="3" style="padding:12px;">هیچ لاگی ثبت نشده است.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom:1px solid #e0e7ff;">
                            <td style="padding:12px;"><?= e(gregorian_to_jalali_date(substr($log['created_at'], 0, 10)) ?? '') ?></td>
                            <td style="padding:12px;"><?= e($log['action']) ?></td>
                            <td style="padding:12px;"><?= e($log['description'] . ($log['full_name'] ? ' (' . $log['full_name'] . ')' : '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <div class="actions" style="margin-top:16px;">
                <a class="btn btn-secondary" href="?plugin=<?= $this->getSlug() ?>">بازگشت</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderList(): string
    {
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'brand' => trim($_GET['brand'] ?? ''),
            'package' => trim($_GET['package'] ?? ''),
            'category' => trim($_GET['category'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
        ];

        $conditions = ['p.warehouse_id = :warehouse_id'];
        $params = ['warehouse_id' => $this->warehouseId];

        if ($filters['search'] !== '') {
            $conditions[] = '(p.name LIKE :search OR p.code LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if ($filters['brand'] !== '') {
            $conditions[] = 'p.brand = :brand';
            $params['brand'] = $filters['brand'];
        }
        if ($filters['package'] !== '') {
            $conditions[] = 'p.package = :package';
            $params['package'] = $filters['package'];
        }
        if ($filters['category'] !== '') {
            $conditions[] = 'p.category = :category';
            $params['category'] = $filters['category'];
        }
        if ($filters['status'] === 'available') {
            $conditions[] = '(p.exit_count IS NULL OR p.exit_count < p.entry_count)';
        } elseif ($filters['status'] === 'released') {
            $conditions[] = 'p.exit_count >= p.entry_count';
        }

        $sql = 'SELECT p.*, u.full_name AS created_by_name FROM product_inventory p LEFT JOIN users u ON u.id = p.created_by WHERE ' . implode(' AND ', $conditions) . ' ORDER BY p.entry_date DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        ob_start();
        ?>
        <style>
            .card { background:#fff;border-radius:16px;padding:20px 24px;box-shadow:0 8px 24px rgba(31,60,136,0.08); }
            table { width:100%; border-collapse: collapse; }
            th, td { padding: 12px; text-align: right; }
            thead { background: #eef2ff; color: #1f3c88; }
            tbody tr:nth-child(even) { background: #f9fbff; }
            .filters { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 12px; margin-bottom: 16px; }
            .filters input, .filters select { padding: 10px 12px; border-radius: 10px; border: 1px solid #d0d7ff; background:#f7f9ff; }
            .badge { display:inline-block; padding:6px 10px; border-radius: 12px; font-size:12px; }
            .badge.success { background: rgba(25,135,84,0.12); color:#0f5132; }
            .badge.warning { background: rgba(255,193,7,0.2); color:#8a6d3b; }
            .actions a { margin-left: 6px; text-decoration:none; color:#1f3c88; font-weight:600; }
        </style>
        <div class="card">
            <h2 style="margin:0 0 16px;color:#1f3c88;">موجودی انبار محصولات</h2>
            <form method="get" class="filters">
                <input type="hidden" name="plugin" value="<?= $this->getSlug() ?>">
                <input type="text" name="search" placeholder="جستجو کد یا نام" value="<?= e($filters['search']) ?>">
                <?= $this->renderSelect('brand', 'برند', array_merge(['' => 'همه برندها'], array_combine($this->brands, $this->brands)), $filters['brand'], false) ?>
                <?= $this->renderSelect('package', 'نوع ظرف', array_merge(['' => 'همه ظرف‌ها'], array_combine($this->containers, $this->containers)), $filters['package'], false) ?>
                <?= $this->renderSelect('category', 'نوع شیت', array_merge(['' => 'همه'], array_combine($this->categories, $this->categories)), $filters['category'], false) ?>
                <?= $this->renderSelect('status', 'وضعیت', ['' => 'همه وضعیت‌ها', 'available' => 'دارای موجودی', 'released' => 'خارج شده'], $filters['status'], false) ?>
                <button class="btn" type="submit" style="align-self:stretch;">اعمال فیلتر</button>
            </form>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>کد</th>
                            <th>نام کالا</th>
                            <th>برند</th>
                            <th>ظرف</th>
                            <th>بسته‌بندی</th>
                            <th>تعداد ورودی</th>
                            <th>تعداد خروج</th>
                            <th>موقعیت</th>
                            <th>تاریخ ورود</th>
                            <th>تاریخ انقضا</th>
                            <th>وضعیت</th>
                            <th>اقدامات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="12" style="text-align:center;">رکوردی یافت نشد.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <?php
                                $status = 'در انتظار خروج';
                                $statusClass = 'warning';
                                if (!empty($record['exit_count']) && $record['exit_count'] >= $record['entry_count']) {
                                    $status = 'خارج شده';
                                    $statusClass = 'success';
                                } elseif (empty($record['exit_count'])) {
                                    $status = 'موجود';
                                    $statusClass = 'success';
                                }
                                ?>
                                <tr>
                                    <td><?= e($record['code']) ?></td>
                                    <td><?= e($record['name']) ?></td>
                                    <td><?= e($record['brand']) ?></td>
                                    <td><?= e($record['package']) ?></td>
                                    <td><?= e($record['packaging_type'] ?? '-') ?></td>
                                    <td><?= e(format_number((float)$record['entry_count'])) ?></td>
                                    <td><?= e($record['exit_count'] !== null ? format_number((float)$record['exit_count']) : '-') ?></td>
                                    <td><?= e($record['location']) ?></td>
                                    <td><?= e(gregorian_to_jalali_date($record['entry_date']) ?? '-') ?></td>
                                    <td><?= e(gregorian_to_jalali_date($record['expiry_date']) ?? '-') ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= e($status) ?></span></td>
                                    <td class="actions">
                                        <a href="?plugin=<?= $this->getSlug() ?>&action=exit&id=<?= (int)$record['id'] ?>">خروج</a>
                                        <a href="?plugin=<?= $this->getSlug() ?>&action=logs&id=<?= (int)$record['id'] ?>">لاگ‌ها</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function fetchInventory(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM product_inventory WHERE id = :id AND warehouse_id = :warehouse_id');
        $stmt->execute(['id' => $id, 'warehouse_id' => $this->warehouseId]);
        $record = $stmt->fetch();
        return $record ?: null;
    }

    private function logAction(int $inventoryId, string $action, string $description): void
    {
        $user = current_user();
        if (!$user) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO product_inventory_logs (inventory_id, action, description, performed_by) VALUES (:inventory_id, :action, :description, :performed_by)');
        $stmt->execute([
            'inventory_id' => $inventoryId,
            'action' => $action,
            'description' => $description,
            'performed_by' => $user['id'],
        ]);
    }

    private function sanitizeInventoryInput(array $input): array
    {
        return [
            'code' => trim($input['code'] ?? ''),
            'name' => trim($input['name'] ?? ''),
            'brand' => trim($input['brand'] ?? ''),
            'package' => trim($input['package'] ?? ''),
            'batch' => trim($input['batch'] ?? ''),
            'location' => trim($input['location'] ?? ''),
            'entry_date' => trim($input['entry_date'] ?? ''),
            'entry_count' => (float) ($input['entry_count'] ?? 0),
            'weight_unit' => (float) ($input['weight_unit'] ?? 0),
            'total_weight' => isset($input['total_weight']) && $input['total_weight'] !== '' ? (float) $input['total_weight'] : null,
            'expiry_date' => trim($input['expiry_date'] ?? ''),
            'category' => trim($input['category'] ?? ''),
            'packaging_type' => trim($input['packaging_type'] ?? ''),
            'notes' => trim($input['notes'] ?? ''),
        ];
    }

    private function validateInventoryInput(array $data): array
    {
        $errors = [];
        if ($data['code'] === '' || $data['name'] === '') {
            $errors[] = 'کد و نام کالا الزامی است.';
        }
        if (!in_array($data['brand'], $this->brands, true)) {
            $errors[] = 'برند انتخاب شده معتبر نیست.';
        }
        if (!in_array($data['package'], $this->containers, true)) {
            $errors[] = 'نوع ظرف انتخاب شده معتبر نیست.';
        }
        if ($data['packaging_type'] !== '' && !in_array($data['packaging_type'], $this->packagingTypes, true)) {
            $errors[] = 'نوع بسته‌بندی انتخاب شده معتبر نیست.';
        }
        if (!in_array($data['category'], $this->categories, true)) {
            $errors[] = 'نوع شیت انتخاب شده معتبر نیست.';
        }
        if ($data['entry_count'] <= 0) {
            $errors[] = 'تعداد ورودی باید بیشتر از صفر باشد.';
        }
        if ($data['weight_unit'] <= 0) {
            $errors[] = 'وزن هر واحد باید بیشتر از صفر باشد.';
        }
        if ($data['entry_date'] === '' || !jalali_to_gregorian_date($data['entry_date'])) {
            $errors[] = 'تاریخ ورود معتبر نیست.';
        }
        if ($data['expiry_date'] !== '' && !jalali_to_gregorian_date($data['expiry_date'])) {
            $errors[] = 'تاریخ انقضا معتبر نیست.';
        }
        return $errors;
    }

    private function renderInput(string $name, string $label, $value = '', bool $required = false): string
    {
        return '<div><label for="' . e($name) . '">' . e($label) . ($required ? ' *' : '') . '</label><input type="text" id="' . e($name) . '" name="' . e($name) . '" value="' . e((string)$value) . '"' . ($required ? ' required' : '') . '></div>';
    }

    private function renderNumber(string $name, string $label, $value = '', bool $required = false): string
    {
        return '<div><label for="' . e($name) . '">' . e($label) . ($required ? ' *' : '') . '</label><input type="number" step="0.01" id="' . e($name) . '" name="' . e($name) . '" value="' . e((string)$value) . '"' . ($required ? ' required' : '') . '></div>';
    }

    private function renderSelect(string $name, string $label, array $options, $selected = '', bool $wrap = true): string
    {
        $html = '<div><label for="' . e($name) . '">' . e($label) . '</label><select id="' . e($name) . '" name="' . e($name) . '">';
        foreach ($options as $value => $text) {
            if (is_int($value)) {
                $value = $text;
            }
            $html .= '<option value="' . e((string)$value) . '"' . ($value === $selected ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        $html .= '</select></div>';
        return $wrap ? $html : $html;
    }
}

return new ProductInventoryPlugin();
