<?php

namespace App\Controller;

use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use OpenApi\Attributes as OA;
use TypeError;

#[Route('/api', name: 'app_api_')]
#[OA\Tag(name: 'Transactions')]
final class TransactionController extends AbstractController
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private TokenStorageInterface $tokenStorage,
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    #[Route('/v1/transactions', name: 'transactions_history', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/transactions',
        summary: 'Get user transactions',
        description: 'Get user transactions history through token. Supports query filters',
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "filter[type]",
                description: "Transaction type filter (payment|deposit)",
                in: "query",
                required: false,
            ),
            new OA\Parameter(
                name: "filter[course_code]",
                description: "Course code filter",
                in: "query",
                required: false,
            ),
            new OA\Parameter(
                name: "filter[skip_expired]",
                description: "Boolean flag to skip expired transactions (for rental courses)",
                in: "query",
                required: false,
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: "User transactions history",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "id",
                    type: "integer"
                ),
                new OA\Property(
                    property: "created_at",
                    type: "datetime"
                ),
                new OA\Property(
                    property: "type",
                    type: "string"
                ),
                new OA\Property(
                    property: "amount",
                    type: "string"
                ),
                new OA\Property(
                    property: "course_code",
                    type: "string"
                ),
                new OA\Property(
                    property: "expires_at",
                    type: "datetime"
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Bad request: invalid parameters",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "error",
                    type: "string",
                    example: 'Only "payment" or "deposit" types supported'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Missing or invalid JWT token',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "error",
                    type: "string",
                    example: "Missing JWT token"
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal server error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "error",
                    type: "string",
                    example: "Internal server error"
                ),
            ]
        )
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $token = $this->tokenStorage->getToken();

            if (!$token) {
                return new JsonResponse([
                    'error' => 'Missing JWT token'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Получаем имя юзера для фильтрации по нему
            $decodedJwtToken = $this->jwtManager->decode($token);
            $currentUsername = $decodedJwtToken['username'];
            
            // Получаем дополнительные фильтры
            $filter = [];
            if ($request->query->has('filter')) {
                $filter = array_map('htmlspecialchars', $request->query->all()['filter']);
            }

            $transactionType = $filter['type'] ?? null;
            $transactionCourseCode = $filter['course_code'] ?? null;
            $transactionSkipExpired = $filter['skip_expired'] ?? 'false';

            if ($transactionType) {
                try {
                    $transactionType = TransactionType::getValueFromLabel($transactionType)->getValue();
                } catch (TypeError) {
                    return new JsonResponse([
                        'error' => 'Only "payment" or "deposit" types supported'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            if ($transactionSkipExpired === 'true' || $transactionSkipExpired === 'false') {
                $transactionSkipExpired = true ? $transactionSkipExpired === 'true' : false;
            } else {
                return new JsonResponse([
                    'error' => '"skip_expired" must be a boolean type'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Фильтрация
            $filteredTransactions = $this->transactionRepository
                ->getFilteredTransactions(
                    $currentUsername,
                    $transactionType,
                    $transactionCourseCode,
                    $transactionSkipExpired
                )
            ;
            
            // Составляем ответ
            $response = [];
            foreach ($filteredTransactions as $transaction) {
                $item = [
                    'id' => $transaction->getId(),
                    'created_at' => $transaction->getDate()->format(\DateTimeInterface::ATOM),
                    'type' => $transaction->getType()->getLabel(),
                    'amount' => $transaction->getValue()
                ];
                if ($transaction->getType() == TransactionType::PAYMENT) {
                    $item['course_code'] = $transaction->getCourse()->getCode();
                }
                if ($transaction->getExpiredAt() != null) {
                    $item['expires_at'] = $transaction->getExpiredAt()->format(\DateTimeInterface::ATOM);
                }
                $response[] = $item;
            }

            return new JsonResponse(
                $response,
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
