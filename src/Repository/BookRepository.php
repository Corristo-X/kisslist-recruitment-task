<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Book> */
final class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function findOneBySerialNumber(string $serialNumber): ?Book
    {
        return $this->findOneBy(['serialNumber' => $serialNumber]);
    }

    /**
     * Dociąga wyłącznie aktywne wypożyczenia jednym zapytaniem — bez tego
     * listowanie N książek generuje N dodatkowych zapytań.
     *
     * @return Book[]
     */
    public function findAllWithActiveLoan(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.loans', 'l', Join::WITH, 'l.returnedAt IS NULL')
            ->addSelect('l')
            ->orderBy('b.serialNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
