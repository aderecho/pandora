<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use FileServerCli\Support\HumanBytes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list', description: 'List objects and folders in a bucket')]
class ListCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('bucket', InputArgument::REQUIRED, 'Bucket name')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Only list entries under this prefix', '')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Page number', '1')
            ->addOption('per-page', null, InputOption::VALUE_REQUIRED, 'Results per page', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        try {
            $response = $this->client($settings)->listObjects(
                (string) $input->getArgument('bucket'),
                (string) $input->getOption('prefix'),
                ['page' => $input->getOption('page'), 'per_page' => $input->getOption('per-page')],
            );
        } catch (\Throwable $e) {
            return $this->reportException($e, $input, $output);
        }

        $entries = $response['data'] ?? [];

        if ($this->isJson($input)) {
            $this->outputJson($output, $entries);

            return ExitCode::SUCCESS;
        }

        if ($entries === []) {
            $output->writeln('No entries found.');

            return ExitCode::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Type', 'Key', 'Size', 'Modified']);

        foreach ($entries as $entry) {
            $table->addRow($entry['type'] === 'folder'
                ? ['folder', $entry['key'], '-', '-']
                : ['file', $entry['key'], HumanBytes::format($entry['size_bytes']), $entry['updated_at'] ?? '-']);
        }

        $table->render();

        if (isset($response['meta']['pagination'])) {
            $p = $response['meta']['pagination'];
            $output->writeln("Page {$p['current_page']} of {$p['last_page']} ({$p['total']} total)");
        }

        return ExitCode::SUCCESS;
    }
}
