<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\BookStatus;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\Table(name: 'book')]
#[ORM\UniqueConstraint(name: 'book_serial_number_unique', columns: ['serial_number'])]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Loan> */
    #[ORM\OneToMany(mappedBy: 'book', targetEntity: Loan::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['borrowedAt' => 'DESC'])]
    private Collection $loans;

    public function __construct(
        #[ORM\Column(length: 6)]
        private string $serialNumber,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column(length: 255)]
        private string $author,
    ) {
        $this->loans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    /** @return Collection<int, Loan> */
    public function getLoans(): Collection
    {
        return $this->loans;
    }

    public function addLoan(Loan $loan): void
    {
        if (!$this->loans->contains($loan)) {
            $this->loans->add($loan);
        }
    }

    public function getActiveLoan(): ?Loan
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->isNull('returnedAt'))
            ->setMaxResults(1);

        return $this->loans->matching($criteria)->first() ?: null;
    }

    public function isBorrowed(): bool
    {
        return null !== $this->getActiveLoan();
    }

    public function status(): BookStatus
    {
        return $this->isBorrowed() ? BookStatus::Borrowed : BookStatus::Available;
    }
}
