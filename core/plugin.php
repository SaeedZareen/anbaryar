<?php
abstract class Plugin
{
    abstract public function getSlug(): string;

    abstract public function getName(): string;

    public function getMenu(): array
    {
        return [
            [
                'label' => $this->getName(),
                'url' => '?plugin=' . $this->getSlug(),
                'icon' => '📦',
            ],
        ];
    }

    public function handle(string $action): string
    {
        return '';
    }
}

class PluginManager
{
    /** @var Plugin[] */
    private array $plugins = [];

    public function loadPlugins(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $entries = scandir($directory);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $pluginFile = $path . '/plugin.php';
                if (file_exists($pluginFile)) {
                    $plugin = require $pluginFile;
                    if ($plugin instanceof Plugin) {
                        $this->plugins[$plugin->getSlug()] = $plugin;
                    }
                }
            }
        }
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function getPlugin(?string $slug): ?Plugin
    {
        if ($slug === null) {
            return null;
        }
        return $this->plugins[$slug] ?? null;
    }
}
