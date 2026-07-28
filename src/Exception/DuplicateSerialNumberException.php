<?php

declare(strict_types=1);

namespace App\Exception;

final class DuplicateSerialNumberException extends DomainException
{
    public static function withSerialNumber(string $serialNumber): self
    {
        return new self(sprintf('Książka o numerze seryjnym %s już istnieje.', $serialNumber));
    }
}
