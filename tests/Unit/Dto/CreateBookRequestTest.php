<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\CreateBookRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreateBookRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidPayloadHasNoViolations(): void
    {
        $violations = $this->validator->validate(
            new CreateBookRequest('123456', 'Lalka', 'Bolesław Prus'),
        );

        self::assertCount(0, $violations);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSerialNumbers(): iterable
    {
        yield 'za krótki' => ['12345'];
        yield 'za długi' => ['1234567'];
        yield 'litery' => ['12ab56'];
        yield 'pusty' => [''];
        yield 'spacje' => ['12 456'];
    }

    #[DataProvider('invalidSerialNumbers')]
    public function testRejectsInvalidSerialNumber(string $serialNumber): void
    {
        $violations = $this->validator->validate(
            new CreateBookRequest($serialNumber, 'Lalka', 'Bolesław Prus'),
        );

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('serialNumber', $violations->get(0)->getPropertyPath());
    }

    public function testAcceptsSerialNumberWithLeadingZero(): void
    {
        $violations = $this->validator->validate(
            new CreateBookRequest('012345', 'Lalka', 'Bolesław Prus'),
        );

        self::assertCount(0, $violations);
    }

    public function testRejectsEmptyTitleAndAuthor(): void
    {
        $violations = $this->validator->validate(new CreateBookRequest('123456', '', ''));

        self::assertSame(2, $violations->count());
    }
}
