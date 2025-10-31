<?php

namespace App\View;

class View
{
    public function render(string $template, array $data = []): void
    {
        echo $this->renderToString($template, $data);
    }

    public function renderToString(string $template, array $data = []): string
    {
        $templatePath = $this->resolveTemplate($template);
        if (!file_exists($templatePath)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return (string) ob_get_clean();
    }

    private function resolveTemplate(string $template): string
    {
        $template = str_replace('..', '', $template);
        $template = str_replace(['\\', ':'], DIRECTORY_SEPARATOR, $template);
        return dirname(__DIR__, 2) . '/templates/' . $template . '.php';
    }
}
