<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

#[Route('/api', name: 'app_api_')]
#[OA\Tag(name: 'Registration & Authentication')]
final class ApiLoginController extends AbstractController
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private RefreshTokenManagerInterface $refreshTokenManager
    ) {
    }

    #[Route('/v1/auth', name: 'auth', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth',
        summary: 'Authenticate user',
        description: 'Takes user email and password to authenticate them. Returns JWT token if successful.',
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Authentication credentials',
            content: new OA\JsonContent(ref: new Model(type: UserDto::class))
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Authentication successful',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'user',
                    type: 'string',
                    example: 'username@mail.com'
                ),
                new OA\Property(
                    property: 'token',
                    type: 'string'
                ),
                new OA\Property(
                    property: 'refreshToken',
                    type: 'string'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'string',
                    example: 'Invalid credentials'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid password',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'string',
                    example: 'Invalid password'
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
                $errorsString = "";
                foreach ($errors as $error) {
                    $errorsString = $error->getPropertyPath() . ": " . $error->getMessage() . ";";
                }
                return new JsonResponse([
                    'error' => $errorsString
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
                    'error' => 'Invalid password'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            $token = $this->jwtManager->create($user);

            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
                $user,
                (new \DateTime())->modify('+1 month')->getTimestamp()
            );
            $this->refreshTokenManager->save($refreshToken);

            return new JsonResponse([
                'user' => $user->getUserIdentifier(),
                'token' => $token,
                'refresh_token' => $refreshToken->getRefreshToken()
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/v1/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/register',
        summary: 'Register user',
        description: 'Takes user email and password to register them. Returns JWT token if successful.',
        requestBody: new OA\RequestBody(
            required: true,
            description: 'User registration data',
            content: new OA\JsonContent(ref: new Model(type: UserDto::class))
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Registration successful',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'user',
                    type: 'string',
                    example: 'username@mail.com'
                ),
                new OA\Property(
                    property: 'token',
                    type: 'string'
                ),
                new OA\Property(
                    property: 'refreshToken',
                    type: 'string'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'string',
                    example: 'Invalid credentials'
                )
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
                $errorsString = "";
                foreach ($errors as $error) {
                    $errorsString = $error->getPropertyPath() . ": " . $error->getMessage() . ";";
                }
                return new JsonResponse([
                    'error' => $errorsString
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

            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl(
                $user,
                (new \DateTime())->modify('+1 month')->getTimestamp()
            );
            $this->refreshTokenManager->save($refreshToken);

            return new JsonResponse([
                'user' => $user->getUserIdentifier(),
                'token' => $token,
                'refresh_token' => $refreshToken->getRefreshToken()
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
