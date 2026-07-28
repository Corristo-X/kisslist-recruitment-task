<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateBookRequest;
use App\Entity\Book;
use App\Entity\Loan;
use App\Exception\BookAlreadyBorrowedException;
use App\Exception\BookNotBorrowedException;
use App\Exception\BookNotFoundException;
use App\Exception\DuplicateSerialNumberException;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class LibraryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookRepository $books,
        private LoanRepository $loans,
        private ClockInterface $clock,
    ) {
    }

    public function addBook(CreateBookRequest $request): Book
    {
        if (null !== $this->books->findOneBySerialNumber($request->serialNumber)) {
            throw DuplicateSerialNumberException::withSerialNumber($request->serialNumber);
        }

        $book = new Book($request->serialNumber, $request->title, $request->author);
        $this->entityManager->persist($book);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Dwa równoległe żądania z tym samym numerem — sprawdzenie wyżej ich nie złapie.
            throw DuplicateSerialNumberException::withSerialNumber($request->serialNumber);
        }

        return $book;
    }

    /** @return Book[] */
    public function listBooks(): array
    {
        return $this->books->findAllWithActiveLoan();
    }

    public function getBook(string $serialNumber): Book
    {
        return $this->books->findOneBySerialNumber($serialNumber)
            ?? throw BookNotFoundException::withSerialNumber($serialNumber);
    }

    public function deleteBook(string $serialNumber): void
    {
        $book = $this->getBook($serialNumber);

        if ($book->isBorrowed()) {
            throw BookAlreadyBorrowedException::cannotDelete($serialNumber);
        }

        $this->entityManager->remove($book);
        $this->entityManager->flush();
    }

    public function borrow(string $serialNumber, string $cardNumber): Book
    {
        $book = $this->getBook($serialNumber);

        $active = $book->getActiveLoan();
        if (null !== $active) {
            throw BookAlreadyBorrowedException::forBook($serialNumber, $active->getCardNumber(), $active->getBorrowedAt());
        }

        $loan = new Loan($book, $cardNumber, $this->clock->now());
        $this->entityManager->persist($loan);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Partial unique index złapał wyścig dwóch równoległych wypożyczeń.
            throw BookAlreadyBorrowedException::forBook($serialNumber, $cardNumber, $this->clock->now());
        }

        return $book;
    }

    public function returnBook(string $serialNumber): Book
    {
        $book = $this->getBook($serialNumber);

        $active = $book->getActiveLoan()
            ?? throw BookNotBorrowedException::forBook($serialNumber);

        $active->markReturned($this->clock->now());
        $this->entityManager->flush();

        return $book;
    }

    /** @return Loan[] */
    public function loanHistory(string $serialNumber): array
    {
        return $this->loans->findHistoryForBook($this->getBook($serialNumber));
    }
}
