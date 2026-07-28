<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Loan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Loan> */
final class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /** @return Loan[] */
    public function findHistoryForBook(Book $book): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.book = :book')
            ->setParameter('book', $book)
            ->orderBy('l.borrowedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
