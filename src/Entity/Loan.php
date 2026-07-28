<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LoanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoanRepository::class)]
#[ORM\Table(name: 'loan')]
#[ORM\UniqueConstraint(name: 'loan_one_active_per_book', columns: ['book_id'], options: ['where' => '(returned_at IS NULL)'])]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $returnedAt = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'loans')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Book $book,
        #[ORM\Column(length: 6)]
        private string $cardNumber,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $borrowedAt,
    ) {
        $book->addLoan($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): Book
    {
        return $this->book;
    }

    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    public function getBorrowedAt(): \DateTimeImmutable
    {
        return $this->borrowedAt;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isActive(): bool
    {
        return null === $this->returnedAt;
    }

    public function markReturned(\DateTimeImmutable $at): void
    {
        if (null !== $this->returnedAt) {
            throw new \LogicException('Wypożyczenie zostało już zamknięte.');
        }

        $this->returnedAt = $at;
    }
}
