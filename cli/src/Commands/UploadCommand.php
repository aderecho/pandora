<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'upload', description: 'Upload a local file to <bucket>/<object-key>')]
class UploadCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('local-file', InputArgument::REQUIRED, 'Path to the local file')
            ->addArgument('destination', InputArgument::REQUIRED, '<bucket>/<object-key>')
            ->setHelp('Example: fileserver upload ./report.pdf documents/reports/2026/q3.pdf');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        $localFile = (string) $input->getArgument('local-file');

        if (! is_file($localFile)) {
            $this->writeError($output, "Local file not found: {$localFile}");

            return ExitCode::USAGE_ERROR;
        }

        try {
            [$bucket, $key] = $this->splitBucketAndKey((string) $input->getArgument('destination'));
        } catch (\InvalidArgumentException $e) {
            $this->writeError($output, $e->getMessage());

            return ExitCode::USAGE_ERROR;
        }

        $size = (int) filesize($localFile);
        $stream = fopen($localFile, 'rb');

        if ($stream === false) {
            $this->writeError($output, "Unable to open local file: {$localFile}");

            return ExitCode::GENERIC_ERROR;
        }

        $quiet = $output->isQuiet() || $this->isJson($input);
        $bar = null;

        if (! $quiet) {
            $bar = new ProgressBar($output, $size);
            $bar->setFormat(' %current%/%max% bytes [%bar%] %percent:3s%%');
            $bar->start();
        }

        try {
            $response = $this->client($settings)->uploadObject($bucket, $key, $stream, $size, function (int $done, int $total) use ($bar) {
                // Guzzle's multipart total includes MIME boundary/header
                // overhead, so it won't exactly match the raw file size —
                // keep the bar's max in sync rather than showing >100%.
                if ($bar !== null && $total > 0 && $total !== $bar->getMaxSteps()) {
                    $bar->setMaxSteps($total);
                }
                $bar?->setProgress(min($done, $total ?: $done));
            });
        } catch (\Throwable $e) {
            $bar?->finish();
            if (! $quiet) {
                $output->writeln('');
            }

            return $this->reportException($e, $input, $output);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $bar?->finish();
        if (! $quiet) {
            $output->writeln('');
        }

        if ($this->isJson($input)) {
            $this->outputJson($output, $response['data'] ?? []);
        } else {
            $output->writeln("<info>Uploaded</info> {$localFile} -> {$bucket}/{$key}");
        }

        return ExitCode::SUCCESS;
    }
}
