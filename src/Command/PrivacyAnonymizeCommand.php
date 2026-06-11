<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ITKDev\EntityBundle\Entity\Contract\IdentifiableInterface;
use ITKDev\EntityBundle\Privacy\SubjectAnonymizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[AsCommand(
    name: 'privacy:anonymize',
    description: 'Anonymize all personal data linked to a subject',
)]
final class PrivacyAnonymizeCommand extends Command
{
    /**
     * @param class-string<UserInterface&IdentifiableInterface> $userClass
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SubjectAnonymizer $anonymizer,
        #[Autowire(param: 'itk_dev_entity.user_class')]
        private readonly string $userClass,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('subject', InputArgument::REQUIRED, "The subject User's ULID");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('' === $this->userClass) {
            $io->error('privacy:anonymize requires itk_dev_entity.user_class to be configured.');

            return Command::FAILURE;
        }

        $id = (string) $input->getArgument('subject');

        try {
            $ulid = Ulid::fromString($id);
        } catch (\InvalidArgumentException) {
            $io->error(sprintf('Invalid ULID: %s', $id));

            return Command::FAILURE;
        }

        $filters = $this->em->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');
        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }
        try {
            $subject = $this->em->getRepository($this->userClass)->find($ulid);
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }

        if (!$subject instanceof UserInterface || !$subject instanceof IdentifiableInterface) {
            $io->error(sprintf('Subject not found: %s', $id));

            return Command::FAILURE;
        }

        $report = $this->anonymizer->anonymize($subject);

        $io->success(sprintf(
            'Anonymized %d row(s) across %d class(es)',
            $report->rowsAnonymized,
            $report->classesAffected,
        ));

        return Command::SUCCESS;
    }
}
