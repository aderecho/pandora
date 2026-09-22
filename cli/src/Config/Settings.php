<?php

namespace FileServerCli\Config;

/**
 * Effective configuration for a single command invocation. Precedence:
 * explicit --flag > environment variable > ~/.fileserver/config.json.
 */
final readonly class Settings
{
    private function __construct(
        public ?string $endpoint,
        public ?string $token,
        public ?string $defaultBucket,
    ) {}

    public static function resolve(
        ConfigRepository $config,
        ?string $endpointFlag = null,
        ?string $tokenFlag = null,
        ?string $bucketFlag = null,
    ): self {
        return new self(
            endpoint: self::firstNonEmpty($endpointFlag, getenv('FILESERVER_ENDPOINT') ?: null, $config->get('endpoint')),
            token: self::firstNonEmpty($tokenFlag, getenv('FILESERVER_TOKEN') ?: null, $config->get('token')),
            defaultBucket: self::firstNonEmpty($bucketFlag, getenv('FILESERVER_BUCKET') ?: null, $config->get('default-bucket')),
        );
    }

    private static function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
