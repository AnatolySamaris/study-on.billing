<?php

namespace App\Controller;

use App\Repository\TransactionRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api', name: 'app_api_')]
final class TransactionController extends AbstractController
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private TokenStorageInterface $tokenStorage,
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    #[Route('/v1/transactions', name: 'transactions_history', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
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
        $transactionSkipExpired = $filter['skip_expired'] ?? null;

        if ($transactionType && $transactionType != 'payment' && $transactionType != 'deposit') {
            return new JsonResponse([
                'error' => 'Only "payment" or "deposit" types supported'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($transactionSkipExpired && $transactionSkipExpired !== 'true' && $transactionSkipExpired !== 'false') {
            return new JsonResponse([
                'error' => '"skip_expired" must be a boolean type'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Фильтрация
        $filteredTransactions = $this->transactionRepository
            ->getFiltered(
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
                'created_at' => $transaction->getDate(),
                'type' => $transaction->getType(),
                'amount' => $transaction->getValue()
            ];
            if ($transaction->getType() == 'payment') {
                $item['course_code'] = $transaction->getCourse()->getCode();
            }
            if ($transaction->getExpiredAt() != null) {
                $item['expires_at'] = $transaction->getExpiredAt();
            }
        }

        return new JsonResponse(
            $response,
            Response::HTTP_OK
        );
    }
}
