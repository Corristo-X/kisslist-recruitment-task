<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\ApiExceptionListener;
use App\Exception\BookAlreadyBorrowedException;
use App\Exception\BookNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionListenerTest extends TestCase
{
    public function testMapsBookNotFoundTo404ProblemJson(): void
    {
        $event = $this->eventFor(BookNotFoundException::withSerialNumber('123456'));

        (new ApiExceptionListener($this->createStub(LoggerInterface::class)))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('/errors/not-found', $payload['type']);
        self::assertSame(404, $payload['status']);
        self::assertStringContainsString('123456', $payload['detail']);
    }

    public function testMapsDomainConflictTo409(): void
    {
        $event = $this->eventFor(BookAlreadyBorrowedException::cannotDelete('123456'));

        (new ApiExceptionListener($this->createStub(LoggerInterface::class)))($event);

        self::assertSame(409, $event->getResponse()?->getStatusCode());
    }

    public function testMapsValidationFailureTo422WithViolations(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Numer seryjny musi składać się dokładnie z 6 cyfr.', null, [], null, 'serialNumber', '12'),
        ]);
        $exception = new UnprocessableEntityHttpException(
            'Validation failed',
            new ValidationFailedException(null, $violations),
        );

        $event = $this->eventFor($exception);

        (new ApiExceptionListener($this->createStub(LoggerInterface::class)))($event);

        $response = $event->getResponse();
        self::assertSame(422, $response?->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('/errors/validation', $payload['type']);
        self::assertSame([['field' => 'serialNumber', 'message' => 'Numer seryjny musi składać się dokładnie z 6 cyfr.']], $payload['violations']);
    }

    public function testMapsUnknownRouteTo404(): void
    {
        $event = $this->eventFor(new NotFoundHttpException('No route found'));

        (new ApiExceptionListener($this->createStub(LoggerInterface::class)))($event);

        $response = $event->getResponse();
        self::assertSame(404, $response?->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('/errors/not-found', $payload['type']);
        self::assertSame('Nie znaleziono zasobu', $payload['title']);
    }

    public function testMapsMethodNotAllowedTo405WithPolishTitle(): void
    {
        $event = $this->eventFor(new MethodNotAllowedHttpException(['GET', 'DELETE'], 'No route found for "PUT /api/books/100001": Method Not Allowed (Allow: GET, DELETE)'));

        (new ApiExceptionListener($this->createStub(LoggerInterface::class)))($event);

        $response = $event->getResponse();
        self::assertSame(405, $response?->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('GET, DELETE', $response->headers->get('Allow'));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('/errors/http', $payload['type']);
        self::assertSame('Niedozwolona metoda HTTP', $payload['title']);
        self::assertSame('Ta metoda HTTP nie jest dozwolona dla tego zasobu.', $payload['detail']);
    }

    public function testLogsUnexpectedExceptionAndReturnsUnchanged500(): void
    {
        $exception = new \RuntimeException('coś poszło nie tak');
        $event = $this->eventFor($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::anything(),
                self::callback(static fn (array $context): bool => ($context['exception'] ?? null) === $exception),
            );

        (new ApiExceptionListener($logger))($event);

        $response = $event->getResponse();
        self::assertSame(500, $response?->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('/errors/server-error', $payload['type']);
        self::assertSame('Błąd serwera', $payload['title']);
        self::assertSame(500, $payload['status']);
        self::assertSame('Wystąpił nieoczekiwany błąd.', $payload['detail']);
    }

    public function testDoesNotLogDomainNotFoundException(): void
    {
        $event = $this->eventFor(BookNotFoundException::withSerialNumber('123456'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        (new ApiExceptionListener($logger))($event);

        self::assertSame(404, $event->getResponse()?->getStatusCode());
    }

    public function testIgnoresRequestsOutsideApiPrefix(): void
    {
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/nie-api'),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('No route found'),
        );

        (new ApiExceptionListener($this->createStub(LoggerInterface::class)))($event);

        self::assertNull($event->getResponse());
    }

    private function eventFor(\Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/api/books/123456'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
