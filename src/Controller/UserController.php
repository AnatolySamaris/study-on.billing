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

#[Route('/api', name: 'app_api_')]
final class UserController extends AbstractController
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/v1/users/current', name: 'current_user', methods: ['GET'])]
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

            $responseData = [];
            $responseData['username'] = $user->getUserIdentifier();
            $responseData['roles'] = $user->getRoles();
            $responseData['balance'] = $user->getBalance();

            return new JsonResponse($responseData, Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
