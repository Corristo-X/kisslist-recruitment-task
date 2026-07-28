<?php

declare(strict_types=1);

namespace App\Domain;

enum BookStatus: string
{
    case Available = 'available';
    case Borrowed = 'borrowed';
}
