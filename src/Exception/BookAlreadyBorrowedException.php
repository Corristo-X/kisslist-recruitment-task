<?php

declare(strict_types=1);

namespace App\Exception;

final class BookAlreadyBorrowedException extends DomainException
{
    public static function forBook(string $serialNumber, string $cardNumber, \DateTimeImmutable $since): self
    {
        return new self(sprintf(
            'Książka %s jest wypożyczona od %s na kartę %s.',
            $serialNumber,
            $since->format('Y-m-d'),
            $cardNumber,
        ));
    }

    public static function cannotDelete(string $serialNumber): self
    {
        return new self(sprintf(
            'Nie można usunąć książki %s, dopóki jest wypożyczona — najpierw przyjmij zwrot.',
            $serialNumber,
        ));
    }
}
