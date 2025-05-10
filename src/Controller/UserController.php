<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'app_api_')]
#[OA\Tag(name: 'User')]
final class UserController extends AbstractController
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/v1/users/current', name: 'current_user', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/users/current',
        summary: 'Get current user info',
        description: 'Returns user info via JWT token',
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(
        response: 200,
        description: "Successfully got user info",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(
                    property: "username",
                    type: "string",
                    example: "user@example.com"
                ),
                new OA\Property(
                    property: "roles",
                    type: "array",
                    items: new OA\Items(type: "string"),
                    example: ["ROLE_USER"]
                ),
                new OA\Property(
                    property: "balance",
                    type: "number",
                    format: "float",
                    example: 199.99
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Invalid or missing JWT token",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "code",
                    type: "number",
                    format: "integer",
                    example: "Invalid JWT token"
                ),
                new OA\Property(
                    property: "message",
                    type: "string",
                    example: "Invalid JWT token"
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "User not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "error",
                    type: "string",
                    example: "User not found"
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "Internal server error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "error",
                    type: "string",
                    example: "Error message"
                ),
            ]
        )
    )]
    public function currentUser(): JsonResponse
    {
        try {
            $decodedJwt = $this->jwtManager->decode($this->tokenStorage->getToken());

            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $decodedJwt['username']])
            ;

            if (!$user) {
                return new JsonResponse([
                    'error' => 'User not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse([
                'username' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'balance' => $user->getBalance()
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
