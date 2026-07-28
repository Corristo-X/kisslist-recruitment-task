<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\LibraryService;

final class BookApiTest extends ApiTestCase
{
    public function testCreatesBook(): void
    {
        $this->post('/api/books', ['serialNumber' => '123456', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('/api/books/123456', $this->client->getResponse()->headers->get('Location'));

        $payload = $this->payload();
        self::assertSame('123456', $payload['serialNumber']);
        self::assertSame('available', $payload['status']);
        self::assertNull($payload['currentLoan']);
    }

    public function testRejectsDuplicateSerialNumberWith409(): void
    {
        $this->post('/api/books', ['serialNumber' => '123456', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);
        $this->post('/api/books', ['serialNumber' => '123456', 'title' => 'Inna', 'author' => 'Autor']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame('/errors/conflict', $this->payload()['type']);
    }

    public function testRejectsInvalidSerialNumberWith422(): void
    {
        $this->post('/api/books', ['serialNumber' => '12ab', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);

        self::assertResponseStatusCodeSame(422);

        $payload = $this->payload();
        self::assertSame('/errors/validation', $payload['type']);
        self::assertSame('serialNumber', $payload['violations'][0]['field']);
    }

    public function testAcceptsSerialNumberWithLeadingZero(): void
    {
        $this->post('/api/books', ['serialNumber' => '012345', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('012345', $this->payload()['serialNumber']);
    }

    public function testListsBooks(): void
    {
        $this->post('/api/books', ['serialNumber' => '222222', 'title' => 'Druga', 'author' => 'Autor B']);
        $this->post('/api/books', ['serialNumber' => '111111', 'title' => 'Pierwsza', 'author' => 'Autor A']);

        $this->client->request('GET', '/api/books');

        self::assertResponseIsSuccessful();

        $items = $this->payload()['items'];
        self::assertCount(2, $items);
        self::assertSame('111111', $items[0]['serialNumber']);
        self::assertSame('available', $items[0]['status']);
    }

    public function testShowsSingleBook(): void
    {
        $this->post('/api/books', ['serialNumber' => '123456', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);

        $this->client->request('GET', '/api/books/123456');

        self::assertResponseIsSuccessful();
        self::assertSame('Lalka', $this->payload()['title']);
    }

    public function testReturns404ForUnknownBook(): void
    {
        $this->client->request('GET', '/api/books/000000');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('/errors/not-found', $this->payload()['type']);
        self::assertSame('Książka o numerze seryjnym 000000 nie istnieje.', $this->payload()['detail']);
    }

    public function testReturns404ForMalformedSerialNumber(): void
    {
        $this->client->request('GET', '/api/books/abc');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
        // Bez wymogu \d{6} w trasie to żądanie i tak dostałoby 404 — ale przez
        // BookNotFoundException, a nie przez routing. "detail" odróżnia oba przypadki.
        self::assertSame('Żądany zasób nie istnieje.', $this->payload()['detail']);
    }

    public function testDeletesBook(): void
    {
        $this->post('/api/books', ['serialNumber' => '123456', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);

        $this->client->request('DELETE', '/api/books/123456');
        self::assertResponseStatusCodeSame(204);
        self::assertEmpty($this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/books/123456');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRejectsMalformedJsonWith400(): void
    {
        $this->client->request(
            'POST',
            '/api/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{nie-json}',
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testDeletesBookWithLoanHistory(): void
    {
        $this->post('/api/books', ['serialNumber' => '123456', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);

        // Wypożyczenia/zwroty nie mają jeszcze endpointu HTTP (Task 7) —
        // przepuszczamy pętlę życia wypożyczenia bezpośrednio przez LibraryService,
        // aby wygenerować historię wypożyczeń, którą kasowanie ma skasować kaskadowo.
        $library = self::getContainer()->get(LibraryService::class);
        $library->borrow('123456', '998877');
        $library->returnBook('123456');

        $this->client->request('DELETE', '/api/books/123456');
        self::assertResponseStatusCodeSame(204);
        self::assertEmpty($this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/books/123456');
        self::assertResponseStatusCodeSame(404);
    }
}
