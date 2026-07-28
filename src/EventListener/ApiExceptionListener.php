<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\BookAlreadyBorrowedException;
use App\Exception\BookNotBorrowedException;
use App\Exception\BookNotFoundException;
use App\Exception\DuplicateSerialNumberException;
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
            $exception instanceof HttpExceptionInterface => $this->problem(
                Response::HTTP_NOT_FOUND === $exception->getStatusCode() ? '/errors/not-found' : '/errors/http',
                Response::$statusTexts[$exception->getStatusCode()] ?? 'Błąd',
                $exception->getStatusCode(),
                Response::HTTP_NOT_FOUND === $exception->getStatusCode() ? 'Żądany zasób nie istnieje.' : $exception->getMessage(),
            ),
            default => $this->problem(
                '/errors/server-error',
                'Błąd serwera',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Wystąpił nieoczekiwany błąd.',
            ),
        };
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
