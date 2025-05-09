<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'app_api_')]
final class ApiLoginController extends AbstractController
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    #[Route('/v1/auth', name: 'auth', methods: ['POST'])]
    public function auth(Request $request): JsonResponse
    {
        try {
            $userDto = $this->serializer->deserialize(
                $request->getContent(),
                UserDto::class,
                'json'
            );

            $errors = $this->validator->validate($userDto);
            if (count($errors) > 0) {
                $formattedErrors = [];
                foreach ($errors as $error) {
                    $formattedErrors[$error->getPropertyPath()] = $error->getMessage();
                }
                return new JsonResponse([
                    'error' => $formattedErrors
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $userDto->email])
            ;

            if (!$user) {
                return new JsonResponse([
                    'error' => 'User with given username not found'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$this->passwordHasher->isPasswordValid($user, $userDto->password)) {
                return new JsonResponse([
                    'error' => 'Invalid credentials'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            $token = $this->jwtManager->create($user);

            return new JsonResponse([
                'user' => $user->getUserIdentifier(),
                'token' => $token
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/v1/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $userDto = $this->serializer->deserialize(
                $request->getContent(),
                UserDto::class,
                'json'
            );

            $errors = $this->validator->validate($userDto);
            if (count($errors) > 0) {
                $formattedErrors = [];
                foreach ($errors as $error) {
                    $formattedErrors[$error->getPropertyPath()] = $error->getMessage();
                }
                return new JsonResponse([
                    'error' => $formattedErrors
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $userExists = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $userDto->email])
            ;
            if ($userExists) {
                return new JsonResponse([
                    'error' => 'User with this email already exists',
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = User::fromDto($userDto, $this->passwordHasher);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $token = $this->jwtManager->create($user);

            return new JsonResponse([
                'user' => $user->getUserIdentifier(),
                'token' => $token
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
