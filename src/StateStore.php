<?php

declare(strict_types=1);

namespace AccessSwitch;

use RuntimeException;

final class StateStore
{
    public function __construct(
        private readonly string $path,
        private readonly bool $defaultOpen,
    ) {
    }

    public function isOpen(): bool
    {
        $state = $this->read();

        return (bool) ($state['open'] ?? $this->defaultOpen);
    }

    /** @return array{open: bool, updated_at?: string} */
    public function read(): array
    {
        if (!is_file($this->path)) {
            return ['open' => $this->defaultOpen];
        }

        $raw = @file_get_contents($this->path);
        if ($raw === false) {
            throw new RuntimeException('Cannot read state file');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['open' => $this->defaultOpen];
        }

        return $decoded;
    }

    public function setOpen(bool $open): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create state directory');
        }

        $payload = json_encode(
            [
                'open' => $open,
                'updated_at' => gmdate('c'),
            ],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
        );

        $handle = @fopen($this->path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cannot open state file');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock state file');
            }
            ftruncate($handle, 0);
            rewind($handle);
            $written = fwrite($handle, $payload . "\n");
            fflush($handle);
            if ($written === false) {
                throw new RuntimeException('Cannot write state file');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
