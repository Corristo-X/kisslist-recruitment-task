<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\BookAlreadyBorrowedException;
use App\Exception\BookNotBorrowedException;
use App\Exception\BookNotFoundException;
use App\Exception\DuplicateSerialNumberException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $event->setResponse($this->toProblemResponse($event->getThrowable()));
    }

    private function toProblemResponse(\Throwable $exception): JsonResponse
    {
        $validationFailure = $this->findValidationFailure($exception);
        if (null !== $validationFailure) {
            return $this->problem(
                '/errors/validation',
                'Walidacja nie powiodła się',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                null,
                ['violations' => $this->violations($validationFailure)],
            );
        }

        return match (true) {
            $exception instanceof BookNotFoundException => $this->problem(
                '/errors/not-found',
                'Nie znaleziono zasobu',
                Response::HTTP_NOT_FOUND,
                $exception->getMessage(),
            ),
            $exception instanceof DuplicateSerialNumberException,
            $exception instanceof BookAlreadyBorrowedException,
            $exception instanceof BookNotBorrowedException => $this->problem(
                '/errors/conflict',
                'Operacja sprzeczna ze stanem zasobu',
                Response::HTTP_CONFLICT,
                $exception->getMessage(),
            ),
            $exception instanceof HttpExceptionInterface => $this->httpProblem($exception),
            default => $this->unexpectedProblem($exception),
        };
    }

    private function unexpectedProblem(\Throwable $exception): JsonResponse
    {
        $this->logger->error('Nieoczekiwany błąd podczas obsługi żądania API.', ['exception' => $exception]);

        return $this->problem(
            '/errors/server-error',
            'Błąd serwera',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'Wystąpił nieoczekiwany błąd.',
        );
    }

    private function httpProblem(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        $isNotFound = Response::HTTP_NOT_FOUND === $status;

        return $this->problem(
            $isNotFound ? '/errors/not-found' : '/errors/http',
            // Response::$statusTexts zawiera angielskie frazy Symfony — API mówi po polsku.
            match ($status) {
                Response::HTTP_BAD_REQUEST => 'Nieprawidłowe żądanie',
                Response::HTTP_NOT_FOUND => 'Nie znaleziono zasobu',
                Response::HTTP_METHOD_NOT_ALLOWED => 'Niedozwolona metoda HTTP',
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Nieobsługiwany format danych',
                default => $status >= 500 ? 'Błąd serwera' : 'Błąd żądania',
            },
            $status,
            $isNotFound ? 'Żądany zasób nie istnieje.' : $exception->getMessage(),
        );
    }

    private function findValidationFailure(\Throwable $exception): ?ValidationFailedException
    {
        for ($current = $exception; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }
        }

        return null;
    }

    /** @return list<array{field: string, message: string}> */
    private function violations(ValidationFailedException $exception): array
    {
        $violations = [];
        foreach ($exception->getViolations() as $violation) {
            $violations[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $violations;
    }

    /** @param array<string, mixed> $extra */
    private function problem(string $type, string $title, int $status, ?string $detail = null, array $extra = []): JsonResponse
    {
        $payload = ['type' => $type, 'title' => $title, 'status' => $status];
        if (null !== $detail) {
            $payload['detail'] = $detail;
        }

        return new JsonResponse($payload + $extra, $status, ['Content-Type' => 'application/problem+json']);
    }
}
