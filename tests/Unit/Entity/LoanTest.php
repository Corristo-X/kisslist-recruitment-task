<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Book;
use App\Entity\Loan;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    public function testNewLoanIsActiveAndAttachesItselfToBook(): void
    {
        $book = new Book('123456', 'Lalka', 'Bolesław Prus');
        $loan = new Loan($book, '998877', new \DateTimeImmutable('2026-01-01 10:00:00'));

        self::assertTrue($loan->isActive());
        self::assertNull($loan->getReturnedAt());
        self::assertTrue($book->getLoans()->contains($loan));
        self::assertSame($loan, $book->getActiveLoan());
        self::assertTrue($book->isBorrowed());
    }

    public function testMarkReturnedClosesLoan(): void
    {
        $book = new Book('123456', 'Lalka', 'Bolesław Prus');
        $loan = new Loan($book, '998877', new \DateTimeImmutable('2026-01-01 10:00:00'));

        $loan->markReturned(new \DateTimeImmutable('2026-01-05 10:00:00'));

        self::assertFalse($loan->isActive());
        self::assertSame('2026-01-05', $loan->getReturnedAt()?->format('Y-m-d'));
        self::assertFalse($book->isBorrowed());
        self::assertNull($book->getActiveLoan());
    }

    public function testCannotReturnTheSameLoanTwice(): void
    {
        $book = new Book('123456', 'Lalka', 'Bolesław Prus');
        $loan = new Loan($book, '998877', new \DateTimeImmutable('2026-01-01 10:00:00'));
        $loan->markReturned(new \DateTimeImmutable('2026-01-05 10:00:00'));

        $this->expectException(\LogicException::class);
        $loan->markReturned(new \DateTimeImmutable('2026-01-06 10:00:00'));
    }
}
