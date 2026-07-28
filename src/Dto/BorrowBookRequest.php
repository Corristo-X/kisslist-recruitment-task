<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class BorrowBookRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Numer karty bibliotecznej jest wymagany.')]
        #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Numer karty bibliotecznej musi składać się dokładnie z 6 cyfr.')]
        public string $cardNumber = '',
    ) {
    }
}
