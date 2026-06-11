<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PrivacyAnonymizeStaleCommandTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private CommandTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->clock = $container->get('clock');

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        self::assertNotNull(self::$kernel);
        $app = new Application(self::$kernel);
        $this->tester = new CommandTester($app->find('privacy:anonymize-stale'));
    }

    public function testHappyPath(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $entity = new FixtureEntity();
        $entity->setLabel('old');
        $this->em->persist($entity);
        $this->em->flush();
        $this->clock->modify('2026-01-01 00:00:00');

        $exit = $this->tester->execute(['--older-than' => 'P1Y']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Anonymized 1 row(s)', $this->tester->getDisplay());
    }

    public function testMissingIntervalExitsFailure(): void
    {
        $exit = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('--older-than is required', $this->tester->getDisplay());
    }

    public function testInvalidIntervalExitsFailure(): void
    {
        $exit = $this->tester->execute(['--older-than' => 'not-iso']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Invalid interval', $this->tester->getDisplay());
    }

    public function testDryRunReportsAndDoesNotMutate(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $entity = new FixtureEntity();
        $entity->setLabel('original');
        $this->em->persist($entity);
        $this->em->flush();
        $this->clock->modify('2026-01-01 00:00:00');

        $exit = $this->tester->execute(['--older-than' => 'P1Y', '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('[DRY RUN]', $this->tester->getDisplay());
        self::assertStringContainsString('Anonymized 1 row(s)', $this->tester->getDisplay());

        $this->em->clear();
        $fresh = $this->em->getRepository(FixtureEntity::class)->find($entity->getId());
        self::assertNotNull($fresh);
        self::assertSame('original', $fresh->getLabel(), 'dry-run must not mutate');
    }
}
