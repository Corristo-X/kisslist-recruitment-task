<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dto\CreateBookRequest;
use App\Exception\BookAlreadyBorrowedException;
use App\Exception\BookNotBorrowedException;
use App\Exception\BookNotFoundException;
use App\Exception\DuplicateSerialNumberException;
use App\Service\LibraryService;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LibraryServiceTest extends DatabaseTestCase
{
    use ClockSensitiveTrait;

    private LibraryService $library;

    protected function setUp(): void
    {
        parent::setUp();

        // Zamraża globalny zegar, z którego korzysta usługa `clock` wstrzykiwana
        // do LibraryService — dzięki temu asercja na borrowedAt jest deterministyczna.
        self::mockTime('2026-07-28 12:00:00');
        $this->library = self::getContainer()->get(LibraryService::class);
    }

    public function testAddsBook(): void
    {
        $book = $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));

        self::assertNotNull($book->getId());
        self::assertFalse($book->isBorrowed());
    }

    public function testRejectsDuplicateSerialNumber(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));

        $this->expectException(DuplicateSerialNumberException::class);
        $this->library->addBook(new CreateBookRequest('123456', 'Inna książka', 'Inny autor'));
    }

    public function testBorrowMarksBookAsBorrowed(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));

        $book = $this->library->borrow('123456', '998877');

        self::assertTrue($book->isBorrowed());
        self::assertSame('998877', $book->getActiveLoan()?->getCardNumber());
        self::assertSame(
            '2026-07-28 12:00:00',
            $book->getActiveLoan()?->getBorrowedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testCannotBorrowAlreadyBorrowedBook(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));
        $this->library->borrow('123456', '998877');

        $this->expectException(BookAlreadyBorrowedException::class);
        $this->library->borrow('123456', '111111');
    }

    public function testReturnClosesActiveLoan(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));
        $this->library->borrow('123456', '998877');

        $book = $this->library->returnBook('123456');

        self::assertFalse($book->isBorrowed());
        self::assertCount(1, $this->library->loanHistory('123456'));
    }

    public function testCannotReturnBookThatIsNotBorrowed(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));

        $this->expectException(BookNotBorrowedException::class);
        $this->library->returnBook('123456');
    }

    public function testBookCanBeBorrowedAgainAfterReturn(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));
        $this->library->borrow('123456', '998877');
        $this->library->returnBook('123456');

        $book = $this->library->borrow('123456', '111111');

        self::assertTrue($book->isBorrowed());
        self::assertCount(2, $this->library->loanHistory('123456'));
    }

    public function testDeletesAvailableBook(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));

        $this->library->deleteBook('123456');

        $this->expectException(BookNotFoundException::class);
        $this->library->getBook('123456');
    }

    public function testCannotDeleteBorrowedBook(): void
    {
        $this->library->addBook(new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'));
        $this->library->borrow('123456', '998877');

        $this->expectException(BookAlreadyBorrowedException::class);
        $this->library->deleteBook('123456');
    }

    public function testUnknownBookThrows(): void
    {
        $this->expectException(BookNotFoundException::class);
        $this->library->getBook('000000');
    }

    public function testListsBooksSortedBySerialNumber(): void
    {
        $this->library->addBook(new CreateBookRequest('222222', 'Druga', 'Autor B'));
        $this->library->addBook(new CreateBookRequest('111111', 'Pierwsza', 'Autor A'));

        $serials = array_map(
            static fn ($book) => $book->getSerialNumber(),
            $this->library->listBooks(),
        );

        self::assertSame(['111111', '222222'], $serials);
    }
}
