<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Command;

use ITKDev\EntityBundle\Privacy\BulkAnonymizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'privacy:anonymize-stale',
    description: 'Anonymize entities older than a given interval (retention sweep)',
)]
final class PrivacyAnonymizeStaleCommand extends Command
{
    public function __construct(private readonly BulkAnonymizer $anonymizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'older-than',
            null,
            InputOption::VALUE_REQUIRED,
            'ISO-8601 duration, e.g. P30D, P2Y, PT24H',
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Report what would be anonymized without changing anything',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $raw = $input->getOption('older-than');

        if (!\is_string($raw) || '' === $raw) {
            $io->error('Option --older-than is required (ISO-8601 duration, e.g. P30D)');

            return Command::FAILURE;
        }

        try {
            $interval = new \DateInterval($raw);
        } catch (\Exception) {
            $io->error(sprintf('Invalid interval: %s', $raw));

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $report = $this->anonymizer->anonymizeOlderThan($interval, $dryRun);

        $io->success(sprintf(
            '%sAnonymized %d row(s) across %d class(es); scrubbed %d audit row(s) per retention policy',
            $dryRun ? '[DRY RUN] ' : '',
            $report->rowsAnonymized,
            $report->classesAffected,
            $report->auditRowsScrubbed,
        ));

        return Command::SUCCESS;
    }
}
