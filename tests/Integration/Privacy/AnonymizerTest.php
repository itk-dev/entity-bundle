<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Privacy\Anonymizer;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\NonAuditableEntity;
use ITKDev\EntityBundle\Tests\Integration\IntegrationTestCase;

final class AnonymizerTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;
    private Anonymizer $anonymizer;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->anonymizer = $container->get(Anonymizer::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testReturnsFalseWhenEntityClassHasNoRules(): void
    {
        $entity = new NonAuditableEntity();
        $entity->setLabel('untouched');
        $this->em->persist($entity);
        $this->em->flush();

        // NonAuditableEntity carries no #[Anonymize] attributes, so the registry has
        // no rules for it and anonymize() must return false without flushing changes.
        self::assertFalse($this->anonymizer->anonymize($entity));
        self::assertSame('untouched', $entity->getLabel());
    }

    public function testReturnsFalseWhenEntityIsAlreadyAnonymized(): void
    {
        $entity = new FixtureEntity();
        $entity->setLabel('original');
        $entity->markAnonymized(new \DateTimeImmutable('2025-01-01'));
        $this->em->persist($entity);
        $this->em->flush();

        // Rules exist for FixtureEntity but isAnonymized() is true, so anonymize()
        // must short-circuit with false and leave the label untouched.
        self::assertFalse($this->anonymizer->anonymize($entity));
        self::assertSame('original', $entity->getLabel());
    }
}
