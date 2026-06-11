<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use ITKDev\EntityBundle\Tests\Integration\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PrivacyAnonymizeCommandTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        self::assertNotNull(self::$kernel);
        $app = new Application(self::$kernel);
        $this->tester = new CommandTester($app->find('privacy:anonymize'));
    }

    public function testHappyPath(): void
    {
        $alice = new TestUser();
        $this->em->persist($alice);
        $this->em->flush();

        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($alice, 'main', $alice->getRoles()));

        $entity = new FixtureEntity();
        $entity->setLabel('alice-data');
        $this->em->persist($entity);
        $this->em->flush();

        $exit = $this->tester->execute(['subject' => (string) $alice->getId()]);

        self::assertSame(Command::SUCCESS, $exit);
        // Subject (TestUser is anonymizable) + the FixtureEntity it created.
        self::assertStringContainsString('Anonymized 2 row(s)', $this->tester->getDisplay());
    }

    public function testInvalidUlidExitsFailure(): void
    {
        $exit = $this->tester->execute(['subject' => 'not-a-ulid']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Invalid ULID', $this->tester->getDisplay());
    }

    public function testUnknownSubjectExitsFailure(): void
    {
        $exit = $this->tester->execute(['subject' => '01JKAAAAAAAAAAAAAAAAAAAAAA']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Subject not found', $this->tester->getDisplay());
    }
}
