<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'mkdir', description: 'Create an empty folder/prefix in a bucket')]
class MkdirCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::REQUIRED, '<bucket>/<prefix>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        try {
            [$bucket, $prefix] = $this->splitBucketAndKey((string) $input->getArgument('target'));
        } catch (\InvalidArgumentException $e) {
            $this->writeError($output, $e->getMessage());

            return ExitCode::USAGE_ERROR;
        }

        try {
            $response = $this->client($settings)->makeDirectory($bucket, $prefix);
        } catch (\Throwable $e) {
            return $this->reportException($e, $input, $output);
        }

        if ($this->isJson($input)) {
            $this->outputJson($output, $response['data'] ?? []);
        } else {
            $output->writeln("<info>Created</info> {$bucket}/".rtrim($prefix, '/').'/');
        }

        return ExitCode::SUCCESS;
    }
}
