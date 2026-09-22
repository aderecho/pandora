<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use FileServerCli\Support\HumanBytes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'info', description: 'Show metadata and version history for an object')]
class InfoCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::REQUIRED, '<bucket>/<object-key>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        try {
            [$bucket, $key] = $this->splitBucketAndKey((string) $input->getArgument('target'));
        } catch (\InvalidArgumentException $e) {
            $this->writeError($output, $e->getMessage());

            return ExitCode::USAGE_ERROR;
        }

        try {
            $response = $this->client($settings)->objectMetadata($bucket, $key);
        } catch (\Throwable $e) {
            return $this->reportException($e, $input, $output);
        }

        $object = $response['data'] ?? [];

        if ($this->isJson($input)) {
            $this->outputJson($output, $object);

            return ExitCode::SUCCESS;
        }

        $output->writeln("<info>{$bucket}/{$key}</info>");
        $output->writeln('Size:      '.HumanBytes::format($object['size_bytes'] ?? 0));
        $output->writeln('MIME type: '.($object['mime_type'] ?? '-'));
        $output->writeln('Checksum:  '.($object['checksum_sha256'] ?? '-'));
        $output->writeln('Uploaded:  '.($object['uploaded_by'] ?? '-').' at '.($object['created_at'] ?? '-'));

        if (! empty($object['versions'])) {
            $output->writeln('');
            $table = new Table($output);
            $table->setHeaders(['Version', 'Size', 'Current', 'Created']);
            foreach ($object['versions'] as $version) {
                $table->addRow([
                    $version['version_number'],
                    HumanBytes::format($version['size_bytes']),
                    $version['is_current'] ? 'yes' : '',
                    $version['created_at'],
                ]);
            }
            $table->render();
        }

        return ExitCode::SUCCESS;
    }
}
