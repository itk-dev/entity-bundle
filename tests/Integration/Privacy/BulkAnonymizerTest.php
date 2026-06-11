<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Privacy\BulkAnonymizer;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class BulkAnonymizerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BulkAnonymizer $anonymizer;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->anonymizer = $container->get(BulkAnonymizer::class);
        $this->clock = $container->get('clock');

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testScrubsRowsOlderThanInterval(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $old = new FixtureEntity();
        $old->setLabel('old-data');
        $this->em->persist($old);
        $this->em->flush();

        $this->clock->modify('2026-01-01 00:00:00');
        $young = new FixtureEntity();
        $young->setLabel('fresh-data');
        $this->em->persist($young);
        $this->em->flush();

        // Threshold: now (2026-01-01) minus P1Y = 2025-01-01. Only `old` qualifies.
        $report = $this->anonymizer->anonymizeOlderThan(new \DateInterval('P1Y'));

        self::assertSame(1, $report->rowsAnonymized);
        self::assertSame(1, $report->classesAffected);

        $this->em->clear();
        $oldFresh = $this->em->getRepository(FixtureEntity::class)->find($old->getId());
        $youngFresh = $this->em->getRepository(FixtureEntity::class)->find($young->getId());

        self::assertSame('[REDACTED]', $oldFresh->getLabel());
        self::assertTrue($oldFresh->isAnonymized());
        self::assertSame('fresh-data', $youngFresh->getLabel());
        self::assertFalse($youngFresh->isAnonymized());
    }

    public function testDryRunCountsButDoesNotMutate(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $entity = new FixtureEntity();
        $entity->setLabel('original');
        $this->em->persist($entity);
        $this->em->flush();
        $this->clock->modify('2026-01-01 00:00:00');

        $report = $this->anonymizer->anonymizeOlderThan(new \DateInterval('P1Y'), dryRun: true);

        self::assertSame(1, $report->rowsAnonymized);

        $this->em->clear();
        $fresh = $this->em->getRepository(FixtureEntity::class)->find($entity->getId());
        self::assertSame('original', $fresh->getLabel());
        self::assertFalse($fresh->isAnonymized());
    }

    public function testIdempotentOnRerun(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $entity = new FixtureEntity();
        $entity->setLabel('original');
        $this->em->persist($entity);
        $this->em->flush();
        $this->clock->modify('2026-01-01 00:00:00');

        $first = $this->anonymizer->anonymizeOlderThan(new \DateInterval('P1Y'));
        self::assertSame(1, $first->rowsAnonymized);

        $second = $this->anonymizer->anonymizeOlderThan(new \DateInterval('P1Y'));
        self::assertSame(0, $second->rowsAnonymized, 'second run finds nothing new');
        self::assertSame(0, $second->classesAffected);
    }
}
