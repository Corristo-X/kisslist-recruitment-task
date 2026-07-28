<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateBookRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Numer seryjny jest wymagany.')]
        #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Numer seryjny musi składać się dokładnie z 6 cyfr.')]
        public string $serialNumber = '',
        #[Assert\NotBlank(message: 'Tytuł jest wymagany.')]
        #[Assert\Length(max: 255, maxMessage: 'Tytuł może mieć najwyżej {{ limit }} znaków.')]
        public string $title = '',
        #[Assert\NotBlank(message: 'Autor jest wymagany.')]
        #[Assert\Length(max: 255, maxMessage: 'Autor może mieć najwyżej {{ limit }} znaków.')]
        public string $author = '',
    ) {
    }
}
