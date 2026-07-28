<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Book;
use App\Entity\Loan;
use App\Tests\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

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
}
