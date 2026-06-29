<?php

declare(strict_types=1);

namespace app;

use Psr\Log\AbstractLogger;
use Stringable;

final class Logger extends AbstractLogger
{
    private readonly string $logFile;
    private readonly string $minLevel;
    private readonly int $maxFiles;

    private const LEVELS = [
        'debug' => 0, 'info' => 1, 'notice' => 2,
        'warning' => 3, 'error' => 4, 'critical' => 5,
        'alert' => 6, 'emergency' => 7,
    ];

    public function __construct(array $config)
    {
        $this->logFile = $config['file']
            ?? ($config['log_dir'] ?? dirname(__DIR__, 2) . '/logs') . '/imago.log';
        $this->minLevel = $config['level'] ?? 'info';
        $this->maxFiles = $config['max_files'] ?? 30;

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 0)) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s%s",
            (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            strtoupper($level),
            $this->interpolate($message, $context),
            PHP_EOL,
        );

        $written = @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            fwrite(STDERR, $line);
        }

        if (file_exists($this->logFile) && filesize($this->logFile) > 100 * 1024 * 1024) {
            $this->rotate();
        }
    }

    private function interpolate(string|Stringable $message, array $context): string
    {
        $message = (string) $message;
        $replace = [];

        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = match (true) {
                $val instanceof Stringable => (string) $val,
                is_scalar($val) => (string) $val,
                default => json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            };
        }

        return strtr($message, $replace);
    }

    private function rotate(): void
    {
        $info = pathinfo($this->logFile);

        for ($i = $this->maxFiles; $i > 0; $i--) {
            $old = $info['dirname'] . '/' . $info['filename'] . '.' . $i . '.log';
            $new = $info['dirname'] . '/' . $info['filename'] . '.' . ($i + 1) . '.log';
            if (file_exists($old)) {
                @rename($old, $new);
            }
        }

        @rename($this->logFile, $info['dirname'] . '/' . $info['filename'] . '.1.log');
    }
}
