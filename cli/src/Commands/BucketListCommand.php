<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use FileServerCli\Support\HumanBytes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bucket:list', description: 'List buckets visible to the configured token')]
class BucketListCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        try {
            $response = $this->client($settings)->listBuckets();
        } catch (\Throwable $e) {
            return $this->reportException($e, $input, $output);
        }

        $buckets = $response['data'] ?? [];

        if ($this->isJson($input)) {
            $this->outputJson($output, $buckets);

            return ExitCode::SUCCESS;
        }

        if ($buckets === []) {
            $output->writeln('No buckets found.');

            return ExitCode::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Status', 'Versioning', 'Quota', 'Public']);

        foreach ($buckets as $bucket) {
            $table->addRow([
                $bucket['name'],
                $bucket['status'],
                $bucket['versioning_enabled'] ? 'yes' : 'no',
                $bucket['quota_bytes'] !== null ? HumanBytes::format($bucket['quota_bytes']) : '-',
                $bucket['public_access'] ? 'yes' : 'no',
            ]);
        }

        $table->render();

        return ExitCode::SUCCESS;
    }
}
