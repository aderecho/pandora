<?php

namespace FileServerCli\Commands;

use FileServerCli\Client\Exceptions\ApiException;
use FileServerCli\Client\Exceptions\NetworkException;
use FileServerCli\Client\FileServerClient;
use FileServerCli\Config\ConfigRepository;
use FileServerCli\Config\Settings;
use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected function settings(InputInterface $input): Settings
    {
        return Settings::resolve(
            new ConfigRepository,
            $input->hasOption('endpoint') ? $input->getOption('endpoint') : null,
            $input->hasOption('token') ? $input->getOption('token') : null,
            $input->hasOption('bucket') ? $input->getOption('bucket') : null,
        );
    }

    protected function requireEndpointAndToken(Settings $settings, OutputInterface $output): bool
    {
        if ($settings->endpoint === null) {
            $this->writeError($output, 'No API endpoint configured. Run: fileserver config:set endpoint <url>');

            return false;
        }

        if ($settings->token === null) {
            $this->writeError($output, 'No API token configured. Run: fileserver config:set token <token>');

            return false;
        }

        return true;
    }

    protected function client(Settings $settings): FileServerClient
    {
        return new FileServerClient((string) $settings->endpoint, $settings->token);
    }

    protected function isJson(InputInterface $input): bool
    {
        return $input->hasOption('json') && (bool) $input->getOption('json');
    }

    protected function outputJson(OutputInterface $output, mixed $data): void
    {
        $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function writeError(OutputInterface $output, string $message): void
    {
        $output->writeln("<error>{$message}</error>");
    }

    /**
     * Splits a "bucket/key" argument. Usage error (exit 2) if the key part
     * is missing.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitBucketAndKey(string $argument): array
    {
        $parts = explode('/', $argument, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException("Expected <bucket>/<key>, got: {$argument}");
        }

        return [$parts[0], $parts[1]];
    }

    protected function exitCodeFor(\Throwable $e): int
    {
        return match (true) {
            $e instanceof ApiException && $e->statusCode === 401 => ExitCode::AUTH_ERROR,
            $e instanceof ApiException && $e->statusCode === 403 => ExitCode::AUTH_ERROR,
            $e instanceof ApiException && $e->statusCode === 404 => ExitCode::NOT_FOUND,
            $e instanceof ApiException => ExitCode::GENERIC_ERROR,
            $e instanceof NetworkException => ExitCode::NETWORK_ERROR,
            $e instanceof \InvalidArgumentException => ExitCode::USAGE_ERROR,
            default => ExitCode::GENERIC_ERROR,
        };
    }

    protected function reportException(\Throwable $e, InputInterface $input, OutputInterface $output): int
    {
        if ($this->isJson($input)) {
            $this->outputJson($output, [
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => $e instanceof ApiException ? $e->errorCode : null,
                ],
            ]);
        } else {
            $this->writeError($output, $e->getMessage());
        }

        return $this->exitCodeFor($e);
    }
}
