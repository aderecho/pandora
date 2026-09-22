<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'download', description: 'Download <bucket>/<object-key> to a local destination')]
class DownloadCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('source', InputArgument::REQUIRED, '<bucket>/<object-key>')
            ->addArgument('destination', InputArgument::REQUIRED, 'Local destination path')
            ->setHelp('Example: fileserver download documents/reports/2026/q3.pdf ./q3.pdf');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        try {
            [$bucket, $key] = $this->splitBucketAndKey((string) $input->getArgument('source'));
        } catch (\InvalidArgumentException $e) {
            $this->writeError($output, $e->getMessage());

            return ExitCode::USAGE_ERROR;
        }

        $destination = (string) $input->getArgument('destination');
        $destinationDir = dirname($destination);

        if (! is_dir($destinationDir) && ! mkdir($destinationDir, 0755, true) && ! is_dir($destinationDir)) {
            $this->writeError($output, "Unable to create destination directory: {$destinationDir}");

            return ExitCode::GENERIC_ERROR;
        }

        $quiet = $output->isQuiet() || $this->isJson($input);
        $bar = null;

        try {
            $this->client($settings)->downloadObject($bucket, $key, $destination, function (int $done, int $total) use (&$bar, $output, $quiet) {
                if ($quiet || $total <= 0) {
                    return;
                }
                if ($bar === null) {
                    $bar = new ProgressBar($output, $total);
                    $bar->setFormat(' %current%/%max% bytes [%bar%] %percent:3s%%');
                    $bar->start();
                }
                $bar->setProgress($done);
            });
        } catch (\Throwable $e) {
            $bar?->finish();
            if (! $quiet && $bar !== null) {
                $output->writeln('');
            }

            return $this->reportException($e, $input, $output);
        }

        $bar?->finish();
        if (! $quiet && $bar !== null) {
            $output->writeln('');
        }

        if ($this->isJson($input)) {
            $this->outputJson($output, ['bucket' => $bucket, 'key' => $key, 'destination' => $destination]);
        } else {
            $output->writeln("<info>Downloaded</info> {$bucket}/{$key} -> {$destination}");
        }

        return ExitCode::SUCCESS;
    }
}
