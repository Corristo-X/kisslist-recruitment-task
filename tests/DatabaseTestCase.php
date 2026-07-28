<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE loan, book RESTART IDENTITY CASCADE');
    }

    protected function tearDown(): void
    {
        // Guard na wypadek błędu w setUp przed przypisaniem właściwości typowanej.
        if (isset($this->em) && $this->em->isOpen()) {
            $this->em->close();
        }

        parent::tearDown();
    }
}
