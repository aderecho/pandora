<?php

namespace FileServerCli\Commands;

use FileServerCli\Client\Exceptions\ApiException;
use FileServerCli\Client\Exceptions\NetworkException;
use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'token:check', description: 'Verify connectivity and that the configured token is valid')]
class TokenCheckCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        $client = $this->client($settings);

        try {
            $client->health();
        } catch (NetworkException $e) {
            return $this->report($input, $output, false, "Cannot reach {$settings->endpoint}: {$e->getMessage()}", ExitCode::NETWORK_ERROR);
        }

        try {
            $client->listBuckets(['per_page' => 1]);
        } catch (ApiException $e) {
            if (in_array($e->statusCode, [401, 403], true)) {
                return $this->report($input, $output, false, 'Token is invalid, expired, or revoked.', ExitCode::AUTH_ERROR);
            }

            return $this->reportException($e, $input, $output);
        } catch (\Throwable $e) {
            return $this->reportException($e, $input, $output);
        }

        return $this->report($input, $output, true, 'Endpoint reachable and token is valid.', ExitCode::SUCCESS);
    }

    private function report(InputInterface $input, OutputInterface $output, bool $ok, string $message, int $exitCode): int
    {
        if ($this->isJson($input)) {
            $this->outputJson($output, ['ok' => $ok, 'message' => $message]);
        } elseif ($ok) {
            $output->writeln("<info>{$message}</info>");
        } else {
            $this->writeError($output, $message);
        }

        return $exitCode;
    }
}
