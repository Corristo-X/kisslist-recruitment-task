<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class LoanApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/api/books', ['serialNumber' => '123456', 'title' => 'Lalka', 'author' => 'Bolesław Prus']);
    }

    public function testBorrowsBook(): void
    {
        $this->post('/api/books/123456/borrow', ['cardNumber' => '998877']);

        self::assertResponseIsSuccessful();

        $payload = $this->payload();
        self::assertSame('borrowed', $payload['status']);
        self::assertSame('998877', $payload['currentLoan']['cardNumber']);
        self::assertSame('Lalka', $payload['title']);
        self::assertNotEmpty($payload['currentLoan']['borrowedAt']);
    }

    public function testCannotBorrowTwice(): void
    {
        $this->post('/api/books/123456/borrow', ['cardNumber' => '998877']);
        $this->post('/api/books/123456/borrow', ['cardNumber' => '111111']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('/errors/conflict', $this->payload()['type']);
    }

    public function testRejectsInvalidCardNumber(): void
    {
        $this->post('/api/books/123456/borrow', ['cardNumber' => '99']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('cardNumber', $this->payload()['violations'][0]['field']);
    }

    public function testReturnsBook(): void
    {
        $this->post('/api/books/123456/borrow', ['cardNumber' => '998877']);

        $this->client->request('POST', '/api/books/123456/return');

        self::assertResponseIsSuccessful();

        $payload = $this->payload();
        self::assertSame('available', $payload['status']);
        self::assertNull($payload['currentLoan']);
    }

    public function testCannotReturnTwice(): void
    {
        $this->post('/api/books/123456/borrow', ['cardNumber' => '998877']);
        $this->client->request('POST', '/api/books/123456/return');
        $this->client->request('POST', '/api/books/123456/return');

        self::assertResponseStatusCodeSame(409);
    }

    public function testCannotDeleteBorrowedBook(): void
    {
        $this->post('/api/books/123456/borrow', ['cardNumber' => '998877']);

        $this->client->request('DELETE', '/api/books/123456');

        self::assertResponseStatusCodeSame(409);
        self::assertSame('/errors/conflict', $this->payload()['type']);
    }

    public function testReturnsLoanHistory(): void
    {
        $this->post('/api/books/123456/borrow', ['cardNumber' => '998877']);
        $this->client->request('POST', '/api/books/123456/return');
        $this->post('/api/books/123456/borrow', ['cardNumber' => '111111']);

        $this->client->request('GET', '/api/books/123456/loans');

        self::assertResponseIsSuccessful();

        $items = $this->payload()['items'];
        self::assertCount(2, $items);
        self::assertSame('111111', $items[0]['cardNumber']);
        self::assertNull($items[0]['returnedAt']);
        self::assertNotNull($items[1]['returnedAt']);
    }

    public function testBorrowingUnknownBookReturns404(): void
    {
        $this->post('/api/books/000000/borrow', ['cardNumber' => '998877']);

        self::assertResponseStatusCodeSame(404);
    }
}
