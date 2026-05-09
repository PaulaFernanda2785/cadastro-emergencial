<?php

declare(strict_types=1);

namespace App\Services;

final class LoginThrottleService
{
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockSeconds;

    public function __construct()
    {
        $config = require BASE_PATH . '/config/security.php';

        $this->maxAttempts = max(3, min(20, (int) ($config['login_max_attempts'] ?? 5)));
        $this->windowSeconds = max(60, min(86400, (int) ($config['login_attempt_window_seconds'] ?? 900)));
        $this->lockSeconds = max(60, min(86400, (int) ($config['login_lock_seconds'] ?? 300)));
    }

    public function status(string $email): array
    {
        $entry = $this->read($email);
        $blockedUntil = (int) ($entry['blocked_until'] ?? 0);
        $now = time();

        if ($blockedUntil <= $now) {
            return ['blocked' => false, 'seconds_remaining' => 0];
        }

        return ['blocked' => true, 'seconds_remaining' => $blockedUntil - $now];
    }

    public function recordFailure(string $email): array
    {
        $entry = $this->read($email);
        $now = time();
        $firstAttemptAt = (int) ($entry['first_attempt_at'] ?? 0);
        $blockedUntil = (int) ($entry['blocked_until'] ?? 0);

        if ($blockedUntil > $now) {
            return $this->status($email);
        }

        if ($firstAttemptAt <= 0 || ($now - $firstAttemptAt) > $this->windowSeconds) {
            $entry = [
                'count' => 0,
                'first_attempt_at' => $now,
                'blocked_until' => 0,
            ];
        }

        $entry['count'] = ((int) ($entry['count'] ?? 0)) + 1;
        $entry['last_attempt_at'] = $now;

        if ((int) $entry['count'] >= $this->maxAttempts) {
            $entry['blocked_until'] = $now + $this->lockSeconds;
        }

        $this->write($email, $entry);

        return $this->status($email);
    }

    public function clear(string $email): void
    {
        $path = $this->path($email);

        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    private function read(string $email): array
    {
        $path = $this->path($email);

        if ($path === null || !is_file($path) || !is_readable($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function write(string $email, array $entry): void
    {
        $path = $this->path($email);

        if ($path === null) {
            return;
        }

        @file_put_contents($path, json_encode($entry, JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($path, 0640);
    }

    private function path(string $email): ?string
    {
        $dir = BASE_PATH . '/storage/cache/security/login-throttle';

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        if (!is_writable($dir)) {
            return null;
        }

        return $dir . '/' . $this->key($email) . '.json';
    }

    private function key(string $email): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'local');
        $ip = strlen($ip) <= 64 ? $ip : 'invalid';

        return hash('sha256', strtolower(trim($email)) . '|' . $ip);
    }
}
