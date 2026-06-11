<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Privacy\SubjectAnonymizer;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SubjectAnonymizerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SubjectAnonymizer $anonymizer;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->anonymizer = $container->get(SubjectAnonymizer::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAnonymizesRowsLinkedToSubject(): void
    {
        $alice = $this->aUser();
        $bob = $this->aUser();
        $this->loginAs($alice);

        $aliceEntity = new FixtureEntity();
        $aliceEntity->setLabel('alice-data');
        $this->em->persist($aliceEntity);
        $this->em->flush();

        $this->loginAs($bob);
        $bobEntity = new FixtureEntity();
        $bobEntity->setLabel('bob-data');
        $this->em->persist($bobEntity);
        $this->em->flush();

        $report = $this->anonymizer->anonymize($alice);

        self::assertSame(1, $report->rowsAnonymized);
        self::assertSame(1, $report->classesAffected);

        $this->em->clear();

        $aliceFresh = $this->em->getRepository(FixtureEntity::class)->find($aliceEntity->getId());
        $bobFresh = $this->em->getRepository(FixtureEntity::class)->find($bobEntity->getId());

        self::assertSame('[REDACTED]', $aliceFresh->getLabel());
        self::assertTrue($aliceFresh->isAnonymized());
        self::assertSame('bob-data', $bobFresh->getLabel());
        self::assertFalse($bobFresh->isAnonymized());
    }

    public function testSkipsAlreadyAnonymizedRows(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('original');
        $entity->markAnonymized(new \DateTimeImmutable('2024-01-01'));
        $this->em->persist($entity);
        $this->em->flush();

        $report = $this->anonymizer->anonymize($alice);

        self::assertSame(0, $report->rowsAnonymized);
    }

    public function testAnonymizesSoftDeletedRows(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('to-be-deleted');
        $this->em->persist($entity);
        $this->em->flush();

        $this->em->remove($entity);
        $this->em->flush();

        $report = $this->anonymizer->anonymize($alice);
        self::assertSame(1, $report->rowsAnonymized);

        $this->em->clear();
        $this->em->getFilters()->disable('soft_delete');
        $fresh = $this->em->getRepository(FixtureEntity::class)->find($entity->getId());
        self::assertSame('[REDACTED]', $fresh->getLabel());
        self::assertTrue($fresh->isDeleted());
    }

    private function aUser(): TestUser
    {
        $user = new TestUser();
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function loginAs(TestUser $user): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
