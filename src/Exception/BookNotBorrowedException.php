<?php

declare(strict_types=1);

namespace App\Exception;

final class BookNotBorrowedException extends DomainException
{
    public static function forBook(string $serialNumber): self
    {
        return new self(sprintf('Książka %s nie jest obecnie wypożyczona.', $serialNumber));
    }
}
