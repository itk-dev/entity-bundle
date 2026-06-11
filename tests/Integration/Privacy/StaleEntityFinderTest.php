<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Entity\Contract\IdentifiableInterface;
use ITKDev\EntityBundle\Privacy\StaleEntityFinder;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class StaleEntityFinderTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StaleEntityFinder $finder;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->finder = $container->get(StaleEntityFinder::class);
        $this->clock = $container->get('clock');

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testFindsRowsOlderThanThreshold(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $old = new FixtureEntity();
        $this->em->persist($old);
        $this->em->flush();

        $this->clock->modify('2025-01-01 00:00:00');
        $young = new FixtureEntity();
        $this->em->persist($young);
        $this->em->flush();

        $results = $this->finder->findOlderThan(FixtureEntity::class, new \DateTimeImmutable('2024-06-01'));

        self::assertCount(1, $results);
        self::assertInstanceOf(IdentifiableInterface::class, $results[0]);
        self::assertEquals($old->getId(), $results[0]->getId());
    }

    public function testExcludesAlreadyAnonymizedRows(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $entity = new FixtureEntity();
        $entity->markAnonymized(new \DateTimeImmutable('2024-02-01'));
        $this->em->persist($entity);
        $this->em->flush();

        $results = $this->finder->findOlderThan(FixtureEntity::class, new \DateTimeImmutable('2024-06-01'));

        self::assertCount(0, $results);
    }

    public function testIncludesSoftDeletedRows(): void
    {
        $this->clock->modify('2024-01-01 00:00:00');
        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();

        $this->em->remove($entity);
        $this->em->flush();

        $results = $this->finder->findOlderThan(FixtureEntity::class, new \DateTimeImmutable('2024-06-01'));

        self::assertCount(1, $results, 'soft-deleted rows are still personal data subject to retention');
    }
}
