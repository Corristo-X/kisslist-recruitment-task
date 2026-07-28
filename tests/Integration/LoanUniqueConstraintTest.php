<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Book;
use App\Entity\Loan;
use App\Repository\BookRepository;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\PersistentCollection;

final class LoanUniqueConstraintTest extends DatabaseTestCase
{
    public function testDatabaseRejectsSecondActiveLoanForTheSameBook(): void
    {
        $book = new Book('123456', 'Lalka', 'Bolesław Prus');
        $this->em->persist($book);
        $this->em->persist(new Loan($book, '111111', new \DateTimeImmutable('2026-01-01 10:00:00')));
        $this->em->flush();

        $this->em->persist(new Loan($book, '222222', new \DateTimeImmutable('2026-01-02 10:00:00')));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testReturnedLoanDoesNotBlockNewLoan(): void
    {
        $book = new Book('123456', 'Lalka', 'Bolesław Prus');
        $first = new Loan($book, '111111', new \DateTimeImmutable('2026-01-01 10:00:00'));
        $first->markReturned(new \DateTimeImmutable('2026-01-05 10:00:00'));

        $this->em->persist($book);
        $this->em->persist($first);
        $this->em->flush();

        $second = new Loan($book, '222222', new \DateTimeImmutable('2026-01-06 10:00:00'));
        $this->em->persist($second);
        $this->em->flush();

        self::assertNotNull($second->getId());
        self::assertTrue($book->isBorrowed());
        self::assertSame('222222', $book->getActiveLoan()?->getCardNumber());
    }

    public function testActiveLoanIsFoundOnFreshlyLoadedBook(): void
    {
        $book = new Book('123456', 'Lalka', 'Bolesław Prus');
        $this->em->persist($book);
        $this->em->persist(new Loan($book, '111111', new \DateTimeImmutable('2026-01-01 10:00:00')));
        $this->em->flush();
        $this->em->clear();

        $freshBook = self::getContainer()->get(BookRepository::class)->findOneBySerialNumber('123456');
        self::assertNotNull($freshBook);

        // Dowód, że to gałąź "leniwa": kolekcja świeżo załadowanej encji jest
        // PersistentCollection, która NIE jest jeszcze zainicjalizowana — matching()
        // musi więc delegować do SQL-a, a nie filtrować w pamięci.
        $loans = $freshBook->getLoans();
        self::assertInstanceOf(PersistentCollection::class, $loans);
        self::assertFalse($loans->isInitialized());

        self::assertSame('111111', $freshBook->getActiveLoan()?->getCardNumber());
        self::assertTrue($freshBook->isBorrowed());
    }

    public function testReturnedLoanIsNotReportedAsActiveOnFreshlyLoadedBook(): void
    {
        $book = new Book('123456', 'Lalka', 'Bolesław Prus');
        $loan = new Loan($book, '111111', new \DateTimeImmutable('2026-01-01 10:00:00'));
        $loan->markReturned(new \DateTimeImmutable('2026-01-05 10:00:00'));

        $this->em->persist($book);
        $this->em->persist($loan);
        $this->em->flush();
        $this->em->clear();

        $freshBook = self::getContainer()->get(BookRepository::class)->findOneBySerialNumber('123456');
        self::assertNotNull($freshBook);

        $loans = $freshBook->getLoans();
        self::assertInstanceOf(PersistentCollection::class, $loans);
        self::assertFalse($loans->isInitialized());

        self::assertNull($freshBook->getActiveLoan());
        self::assertFalse($freshBook->isBorrowed());
    }
}
