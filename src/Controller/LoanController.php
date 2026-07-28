<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\BookResponse;
use App\Dto\BorrowBookRequest;
use App\Dto\LoanResponse;
use App\Entity\Loan;
use App\Service\LibraryService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/books/{serial}', requirements: ['serial' => '\d{6}'])]
final class LoanController extends AbstractController
{
    public function __construct(private readonly LibraryService $library)
    {
    }

    #[OA\Response(response: 200, description: 'Książka wypożyczona')]
    #[OA\Response(response: 404, description: 'Książka nie istnieje')]
    #[OA\Response(response: 409, description: 'Książka jest już wypożyczona')]
    #[OA\Response(response: 422, description: 'Niepoprawny numer karty')]
    #[Route('/borrow', name: 'book_borrow', methods: ['POST'])]
    public function borrow(string $serial, #[MapRequestPayload] BorrowBookRequest $request): JsonResponse
    {
        return $this->json(BookResponse::fromBook(
            $this->library->borrow($serial, $request->cardNumber),
        ));
    }

    #[OA\Response(response: 200, description: 'Zwrot przyjęty')]
    #[OA\Response(response: 404, description: 'Książka nie istnieje')]
    #[OA\Response(response: 409, description: 'Książka nie jest wypożyczona')]
    #[Route('/return', name: 'book_return', methods: ['POST'])]
    public function returnBook(string $serial): JsonResponse
    {
        return $this->json(BookResponse::fromBook($this->library->returnBook($serial)));
    }

    #[OA\Response(response: 200, description: 'Historia wypożyczeń książki')]
    #[OA\Response(response: 404, description: 'Książka nie istnieje')]
    #[Route('/loans', name: 'book_loans', methods: ['GET'])]
    public function history(string $serial): JsonResponse
    {
        return $this->json([
            'items' => array_map(
                static fn (Loan $loan) => LoanResponse::fromLoan($loan),
                $this->library->loanHistory($serial),
            ),
        ]);
    }
}
