<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Loan;

final readonly class LoanResponse
{
    public function __construct(
        public string $cardNumber,
        public string $borrowedAt,
        public ?string $returnedAt,
    ) {
    }

    public static function fromLoan(Loan $loan): self
    {
        return new self(
            $loan->getCardNumber(),
            $loan->getBorrowedAt()->format(\DateTimeInterface::ATOM),
            $loan->getReturnedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
