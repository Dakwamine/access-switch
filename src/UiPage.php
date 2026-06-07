<?php

declare(strict_types=1);

namespace AccessSwitch;

final class UiPage
{
    public static function html(): string
    {
        $path = dirname(__DIR__) . '/resources/ui.html';
        if (!is_readable($path)) {
            throw new \RuntimeException('UI template not found');
        }

        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException('Cannot read UI template');
        }

        return $html;
    }
}
