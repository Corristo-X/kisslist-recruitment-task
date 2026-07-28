<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\BookResponse;
use App\Dto\CreateBookRequest;
use App\Service\LibraryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/books')]
final class BookController extends AbstractController
{
    public function __construct(private readonly LibraryService $library)
    {
    }

    #[Route('', name: 'book_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateBookRequest $request): JsonResponse
    {
        $book = $this->library->addBook($request);

        return $this->json(
            BookResponse::fromBook($book),
            Response::HTTP_CREATED,
            ['Location' => $this->generateUrl(
                'book_show',
                ['serial' => $book->getSerialNumber()],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            )],
        );
    }

    #[Route('', name: 'book_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json([
            'items' => array_map(
                static fn ($book) => BookResponse::fromBook($book),
                $this->library->listBooks(),
            ),
        ]);
    }

    #[Route('/{serial}', name: 'book_show', methods: ['GET'], requirements: ['serial' => '\d{6}'])]
    public function show(string $serial): JsonResponse
    {
        return $this->json(BookResponse::fromBook($this->library->getBook($serial)));
    }

    #[Route('/{serial}', name: 'book_delete', methods: ['DELETE'], requirements: ['serial' => '\d{6}'])]
    public function delete(string $serial): JsonResponse
    {
        $this->library->deleteBook($serial);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
