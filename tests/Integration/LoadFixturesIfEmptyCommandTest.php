<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repository\BookRepository;
use App\Tests\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadFixturesIfEmptyCommandTest extends DatabaseTestCase
{
    public function testLoadsSampleBooksIntoEmptyDatabase(): void
    {
        $this->runFixturesCommand();

        $books = self::getContainer()->get(BookRepository::class)->findAll();

        self::assertCount(5, $books);
        self::assertSame(
            1,
            count(array_filter($books, static fn ($book) => $book->isBorrowed())),
            'Dokładnie jedna książka z fixtures ma być wypożyczona.',
        );
    }

    public function testSecondRunDoesNotDuplicateData(): void
    {
        $this->runFixturesCommand();
        $this->runFixturesCommand();

        self::assertCount(5, self::getContainer()->get(BookRepository::class)->findAll());
    }

    // Nazwa inna niż `runCommand`, bo Symfony 8.1 KernelTestCase (przez
    // ConsoleCommandAssertionsTrait) definiuje już statyczną metodę
    // `runCommand()` — nadpisanie jej niestatyczną metodą prywatną
    // powoduje fatal error PHP ("Cannot make static method non static").
    private function runFixturesCommand(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:load-fixtures-if-empty'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        self::getContainer()->get('doctrine')->getManager()->clear();
    }
}
