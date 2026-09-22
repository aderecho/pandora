<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bucket:create', description: 'Create a new bucket')]
class BucketCreateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('bucket', InputArgument::REQUIRED, 'Bucket name')
            ->addOption('quota-bytes', null, InputOption::VALUE_REQUIRED, 'Storage quota in bytes')
            ->addOption('max-file-size-bytes', null, InputOption::VALUE_REQUIRED, 'Maximum single file size in bytes')
            ->addOption('versioning', null, InputOption::VALUE_NONE, 'Enable object versioning')
            ->addOption('public', null, InputOption::VALUE_NONE, 'Allow public access');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        $options = array_filter([
            'quota_bytes' => $input->getOption('quota-bytes') !== null ? (int) $input->getOption('quota-bytes') : null,
            'max_file_size_bytes' => $input->getOption('max-file-size-bytes') !== null ? (int) $input->getOption('max-file-size-bytes') : null,
            'versioning_enabled' => $input->getOption('versioning') ? true : null,
            'public_access' => $input->getOption('public') ? true : null,
        ], fn ($v) => $v !== null);

        try {
            $response = $this->client($settings)->createBucket((string) $input->getArgument('bucket'), $options);
        } catch (\Throwable $e) {
            return $this->reportException($e, $input, $output);
        }

        if ($this->isJson($input)) {
            $this->outputJson($output, $response['data'] ?? []);
        } else {
            $output->writeln("<info>Bucket created:</info> {$response['data']['name']}");
        }

        return ExitCode::SUCCESS;
    }
}
