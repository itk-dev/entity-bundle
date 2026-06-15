<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use ITKDev\EntityBundle\Command\PrivacyAnonymizeCommand;
use ITKDev\EntityBundle\Privacy\SubjectAnonymizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PrivacyAnonymizeCommandTest extends TestCase
{
    public function testFailsWhenUserClassParamIsEmpty(): void
    {
        $exit = $this->runWithUserClass('');
        self::assertSame(Command::FAILURE, $exit['code']);
        self::assertStringContainsString('itk_dev_entity.user_class', $exit['display']);
    }

    public function testFailsWhenUserClassParamPointsToNonexistentClass(): void
    {
        $exit = $this->runWithUserClass('No\\Such\\Class');
        self::assertSame(Command::FAILURE, $exit['code']);
        self::assertStringContainsString('itk_dev_entity.user_class', $exit['display']);
    }

    /**
     * @return array{code: int, display: string}
     */
    private function runWithUserClass(string $userClass): array
    {
        // SubjectAnonymizer is final; the command's bad-user_class branch returns before
        // ever touching it, so reflection gives us a usable stand-in without invoking its
        // constructor.
        $anonymizer = (new \ReflectionClass(SubjectAnonymizer::class))->newInstanceWithoutConstructor();

        $command = new PrivacyAnonymizeCommand(
            $this->createStub(EntityManagerInterface::class),
            $anonymizer,
            $userClass,
        );
        $tester = new CommandTester($command);
        $code = $tester->execute(['subject' => '01JKAAAAAAAAAAAAAAAAAAAAAA']);

        return ['code' => $code, 'display' => $tester->getDisplay()];
    }
}
