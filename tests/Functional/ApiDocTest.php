<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiDocTest extends WebTestCase
{
    public function testOpenApiDocumentListsAllEndpoints(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();

        $spec = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('/api/books', $spec['paths']);
        self::assertArrayHasKey('/api/books/{serial}', $spec['paths']);
        self::assertArrayHasKey('/api/books/{serial}/borrow', $spec['paths']);
        self::assertArrayHasKey('/api/books/{serial}/return', $spec['paths']);
        self::assertArrayHasKey('/api/books/{serial}/loans', $spec['paths']);
    }
}
