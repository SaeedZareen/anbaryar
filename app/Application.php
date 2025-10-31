<?php

namespace App;

use App\Http\Request;
use App\Http\Response;
use App\View\View;
use PDO;
use PDOException;

class Application
{
    /**
     * @var View
     */
    private $view;

    /**
     * @var PDO|null
     */
    private $pdo = null;

    /**
     * @var \PluginManager|null
     */
    private $pluginManager = null;

    /**
     * @var string[]
     */
    private $activePluginSlugs = [];

    /**
     * @var Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->view = new View();
        \ensure_csrf_token();
    }

    public function run(): void
    {
        $this->handleLogout();

        if (!\is_logged_in()) {
            $this->handleLogin();
            return;
        }

        $this->bootPlugins();
        $this->renderDashboard();
    }

    private function handleLogout(): void
    {
        if ($this->request->query('action') === 'logout') {
            \logout();
            Response::redirect('index.php');
        }
    }

    private function handleLogin(): void
    {
        $oldInput = [
            'username' => (string) $this->request->post('username', ''),
        ];

        if ($this->request->isPost() && $this->request->hasPost('login')) {
            $this->processLoginAttempt();
        }

        $messages = \get_flash();

        $this->view->render('auth/login', [
            'messages' => $messages,
            'csrfToken' => $_SESSION['csrf_token'],
            'old' => $oldInput,
        ]);
    }

    private function processLoginAttempt(): void
    {
        $token = (string) $this->request->post('csrf_token', '');
        if (!\check_csrf_token($token)) {
            \set_flash('error', 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.');
            return;
        }

        $username = trim((string) $this->request->post('username', ''));
        $password = (string) $this->request->post('password', '');

        if ($username === '' || $password === '') {
            \set_flash('error', 'نام کاربری و رمز عبور الزامی است.');
            return;
        }

        if (!\login($username, $password)) {
            \set_flash('error', 'ورود ناموفق بود. لطفاً اطلاعات را بررسی کنید.');
            return;
        }

        Response::redirect('index.php');
    }

    private function bootPlugins(): void
    {
        $this->pdo = \db();
        $this->pluginManager = new \PluginManager();
        $this->pluginManager->loadPlugins($this->basePath('plugins'));

        try {
            $stmt = $this->pdo->query('SELECT slug FROM system_plugins WHERE is_active = 1');
            $this->activePluginSlugs = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            $this->activePluginSlugs = [];
        }
    }

    private function renderDashboard(): void
    {
        $plugins = $this->resolveActivePlugins();
        $currentSlug = $this->resolveCurrentSlug($plugins);
        $currentPlugin = $currentSlug ? ($plugins[$currentSlug] ?? null) : null;
        $action = (string) $this->request->query('action', 'index');

        $content = '';
        if ($currentPlugin) {
            $content = $currentPlugin->handle($action);
        } elseif (!$plugins) {
            $content = '<div class="empty-state">هیچ افزونه‌ای در سیستم فعال نیست.</div>';
        }

        $messages = \get_flash();
        $user = \current_user();

        $this->view->render('dashboard/index', [
            'messages' => $messages,
            'plugins' => $plugins,
            'currentPlugin' => $currentPlugin,
            'currentSlug' => $currentSlug,
            'action' => $action,
            'content' => $content,
            'user' => $user,
        ]);
    }

    /**
     * @param array<string, \Plugin> $plugins
     */
    private function resolveCurrentSlug(array $plugins): ?string
    {
        $requested = $this->request->query('plugin');
        if ($requested && isset($plugins[$requested])) {
            return $requested;
        }
        if ($plugins) {
            return array_key_first($plugins);
        }
        return null;
    }

    /**
     * @return array<string, \Plugin>
     */
    private function resolveActivePlugins(): array
    {
        if (!$this->pluginManager) {
            return [];
        }

        $all = $this->pluginManager->getPlugins();
        if (empty($this->activePluginSlugs)) {
            return $all;
        }

        return array_filter(
            $all,
            function ($plugin) {
                return in_array($plugin->getSlug(), $this->activePluginSlugs, true);
            }
        );
    }

    private function basePath(string $path = ''): string
    {
        $base = dirname(__DIR__);
        return rtrim($base . '/' . ltrim($path, '/'), '/');
    }
}
