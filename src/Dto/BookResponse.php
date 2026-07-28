<?php

declare(strict_types=1);

namespace App\Dto;

use App\Domain\BookStatus;
use App\Entity\Book;

final readonly class BookResponse
{
    public function __construct(
        public string $serialNumber,
        public string $title,
        public string $author,
        public BookStatus $status,
        public ?LoanResponse $currentLoan,
    ) {
    }

    public static function fromBook(Book $book): self
    {
        $activeLoan = $book->getActiveLoan();

        return new self(
            $book->getSerialNumber(),
            $book->getTitle(),
            $book->getAuthor(),
            $book->status(),
            null !== $activeLoan ? LoanResponse::fromLoan($activeLoan) : null,
        );
    }
}
