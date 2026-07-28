<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Book;
use App\Entity\Loan;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-fixtures-if-empty',
    description: 'Ładuje przykładowe książki, jeśli tabela book jest pusta.',
)]
final class LoadFixturesIfEmptyCommand extends Command
{
    private const SAMPLE_BOOKS = [
        ['100001', 'Lalka', 'Bolesław Prus'],
        ['100002', 'Ferdydurke', 'Witold Gombrowicz'],
        ['100003', 'Solaris', 'Stanisław Lem'],
        ['100004', 'Wiedźmin: Ostatnie życzenie', 'Andrzej Sapkowski'],
        ['100005', 'Chłopi', 'Władysław Reymont'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookRepository $books,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ([] !== $this->books->findBy([], null, 1)) {
            $io->info('Baza zawiera już książki — pomijam ładowanie danych przykładowych.');

            return Command::SUCCESS;
        }

        foreach (self::SAMPLE_BOOKS as [$serialNumber, $title, $author]) {
            $this->entityManager->persist(new Book($serialNumber, $title, $author));
        }

        $this->entityManager->flush();

        // Jedna pozycja wypożyczona, żeby oba stany były widoczne od razu po starcie.
        $borrowed = $this->books->findOneBySerialNumber('100003');
        if (null !== $borrowed) {
            $this->entityManager->persist(new Loan($borrowed, '998877', $this->clock->now()));
            $this->entityManager->flush();
        }

        $io->success(sprintf('Załadowano %d przykładowych książek.', count(self::SAMPLE_BOOKS)));

        return Command::SUCCESS;
    }
}
