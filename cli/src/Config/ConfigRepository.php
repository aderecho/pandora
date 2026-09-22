<?php

namespace FileServerCli\Config;

/**
 * Persists CLI configuration at ~/.fileserver/config.json (chmod 600 —
 * it may hold a client token). Precedence for resolving an actual setting
 * is handled by Settings::resolve(), not here: CLI flag > env var > this
 * file.
 */
class ConfigRepository
{
    private const array ALLOWED_KEYS = ['endpoint', 'token', 'default-bucket'];

    /** @var array<string, string> */
    private array $data;

    private readonly string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: self::defaultPath();
        $this->data = $this->load();
    }

    public static function defaultPath(): string
    {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: sys_get_temp_dir());

        return rtrim($home, '/').'/.fileserver/config.json';
    }

    public static function allowedKeys(): array
    {
        return self::ALLOWED_KEYS;
    }

    public function get(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new \InvalidArgumentException(
                "Unknown config key [{$key}]. Allowed keys: ".implode(', ', self::ALLOWED_KEYS)
            );
        }

        $this->data[$key] = $value;
        $this->save();
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);
        $decoded = json_decode((string) $contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function save(): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($this->path, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        chmod($this->path, 0600);
    }
}
