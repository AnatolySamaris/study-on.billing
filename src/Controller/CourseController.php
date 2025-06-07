<?php

namespace App\Controller;

use App\Dto\CourseDto;
use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseType;
use App\Exception\NotEnoughBalanceException;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

#[Route('/api', name: 'app_api_')]
#[OA\Tag(name: 'Courses')]
final class CourseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CourseRepository $courseRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route(path: '/v1/courses', name: 'courses_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses',
        summary: 'Get courses list',
        description: 'Get courses list in JSON format',
    )]
    #[OA\Response(
        response: 200,
        description: "Courses list. If any course is free, the field 'price' is unset",
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "code", type: "string"),
                    new OA\Property(property: "type", type: "string"),
                    new OA\Property(property: "price", type: "float"),
                ]
            ),
            example: [
                [
                    "code" => "python-junior",
                    "type" => "rent",
                    "price" => 159.99
                ],
                [
                    "code" => "ros2-course",
                    "type" => "free"
                ],
                [
                    'code' => "basics-of-computer-vision",
                    "type" => "buy",
                    "price" => 799.99
                ]
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
    public function index(): JsonResponse
    {
        try {
            $courses = $this->courseRepository->findAll();

            $response = [];
            foreach ($courses as $course) {
                $item = [
                    "code" => $course->getCode(),
                    "type" => $course->getType()->getLabel(),
                ];
                if ($course->getType() != CourseType::FREE) {
                    $item["price"] = $course->getPrice();
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

    #[Route(path: '/v1/courses/{code}', name: 'courses_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/courses/{code}',
        summary: 'Get course info by its code',
        description: 'Get course info in JSON format by its code',
    )]
    #[OA\Response(
        response: 200,
        description: "Course info. If the course is free, the field 'price' is unset",
        content: new OA\JsonContent(
            properties: [
                    new OA\Property(property: "code", type: "string"),
                    new OA\Property(property: "type", type: "string"),
                    new OA\Property(property: "price", type: "float"),
            ],
            example: [
                "code" => "python-junior",
                "type" => "rent",
                "price" => 159.99
            ],
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Course not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'string',
                    example: 'Course not found'
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
    public function show(string $code): JsonResponse
    {
        try {
            $course = $this->courseRepository->findByCode($code);

            if (!$course) {
                return new JsonResponse([
                    'error' => 'Course not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $response = [
                'code' => $course->getCode(),
                'type' => $course->getType()->getLabel()
            ];

            if ($course->getType() != CourseType::FREE) {
                $response["price"] = $course->getPrice();
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

    #[Route(path: '/v1/courses/{code}/pay', name: 'courses_pay', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    #[OA\Response(
        response: 201,
        description: "Payment successful",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "success",
                    type: "boolean",
                ),
                new OA\Property(
                    property: "course_type",
                    type: "string",
                ),
                new OA\Property(
                    property: "expired_at",
                    type: "date-time",
                ),
            ],
            examples: [
                "Example 1" => new OA\Examples(
                    example: "example1",
                    summary: "Example for 'buy' course type",
                    value: [
                        "success" => true,
                        "course_type" => "buy",
                    ],
                ),
                "Example 2" => new OA\Examples(
                    example: "example2",
                    summary: "Example for 'free' course type",
                    value: [
                        "success" => true,
                        "course_type" => "free",
                    ],
                ),
                "Example 3" => new OA\Examples(
                    example: "example3",
                    summary: "Example for 'rent' course type",
                    value: [
                        "success" => true,
                        "course_type" => "rent",
                        "expired_at" => "2025-07-20T12:00:00.000000"
                    ],
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Missing or invalid JWT token",
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
        response: 404,
        description: "Course or user not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "error",
                    type: "string",
                    example: "Course not found"
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 406,
        description: "Not acceptable: not enough money",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "error",
                    type: "string",
                    example: "Not enough money for this operation"
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
    public function pay(
        string $code,
        PaymentService $paymentService,
        TokenStorageInterface $tokenStorage,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        try {
            $token = $tokenStorage->getToken();

            if (!$token) {
                return new JsonResponse([
                    'error' => 'Missing JWT token'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $decodedJwtToken = $jwtManager->decode($token);

            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy([
                    'email' => $decodedJwtToken['username']
                ])
            ;

            if (!$user) {
                return new JsonResponse([
                    'error' => 'User not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $course = $this->courseRepository->findByCode($code);

            if (!$course) {
                return new JsonResponse([
                    'error' => 'Course not found'
                ], Response::HTTP_NOT_FOUND);
            }

            try {
                $response = [
                    'success' => true,
                    'course_type' => $course->getType()->getLabel(),
                ];

                $transaction = $paymentService->payment($user, $course);

                if ($course->getType() == CourseType::RENT) {
                    $response['expired_at'] = $transaction->getExpiredAt()->format(\DateTimeInterface::ATOM);
                }

                return new JsonResponse(
                    $response,
                    Response::HTTP_CREATED
                );
            } catch (NotEnoughBalanceException) {
                return new JsonResponse([
                    'error' => 'Not enough money for this operation'
                ], Response::HTTP_NOT_ACCEPTABLE);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/v1/courses/new', name: 'courses_new', methods: ['POST'])]
    #[IsGranted("ROLE_SUPER_ADMIN")]
    #[OA\Post(
        path: '/api/v1/courses/new',
        summary: 'Create course',
        description: 'Creates course on Billing from json',
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'New course data',
            content: new OA\JsonContent(ref: new Model(type: CourseDto::class))
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Course created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'bool',
                    example: 'true'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid course data',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'string',
                    example: 'Course with given code already exists'
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
    public function new(Request $request): JsonResponse
    {
        try {
            $courseDto = $this->serializer->deserialize(
                $request->getContent(),
                CourseDto::class,
                'json'
            );

            $errors = $this->validator->validate($courseDto);
            if (count($errors) > 0) {
                $errorsString = "";
                foreach ($errors as $error) {
                    $errorsString = $error->getPropertyPath() . ": " . $error->getMessage() . ";";
                }
                return new JsonResponse([
                    'error' => $errorsString
                ], Response::HTTP_BAD_REQUEST);
            }

            $existingCourse = $this->entityManager->getRepository(Course::class)
                ->findOneBy(['code' => $courseDto->code]);

            if ($existingCourse) {
                return new JsonResponse([
                    'error' => "Course with given code already exists"
                ], Response::HTTP_BAD_REQUEST);
            }

            $course = new Course();
            $course
                ->setTitle($courseDto->title)
                ->setType(CourseType::from($courseDto->type))
                ->setCode($courseDto->code)
                ->setPrice($courseDto->price)
            ;
            $this->entityManager->persist($course);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/v1/courses/{code}', name: 'courses_edit', methods: ['POST'])]
    #[IsGranted("ROLE_SUPER_ADMIN")]
    #[OA\Post(
        path: '/api/v1/courses/{code}',
        summary: 'Edit course',
        description: 'Edits course on Billing througth json',
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Course data',
            content: new OA\JsonContent(ref: new Model(type: CourseDto::class))
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Course edited successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'bool',
                    example: 'true'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid course data',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'string',
                    example: 'Course with given code does not exist'
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
    public function edit(string $code, Request $request): JsonResponse
    {
        try {
            $courseDto = $this->serializer->deserialize(
                $request->getContent(),
                CourseDto::class,
                'json'
            );

            $errors = $this->validator->validate($courseDto);
            if (count($errors) > 0) {
                $errorsString = "";
                foreach ($errors as $error) {
                    $errorsString = $error->getPropertyPath() . ": " . $error->getMessage() . ";";
                }
                return new JsonResponse([
                    'error' => $errorsString
                ], Response::HTTP_BAD_REQUEST);
            }

            $course = $this->entityManager->getRepository(Course::class)
                ->findOneBy(['code' => $code]);

            if (!$course) {
                return new JsonResponse([
                    'error' => "Course with given code does not exist"
                ], Response::HTTP_BAD_REQUEST);
            }

            $existingCourse = $this->entityManager->getRepository(Course::class)
                ->findOneBy(['code' => $courseDto->code]);

            if ($existingCourse && ($existingCourse->getCode() !== $course->getCode())) {
                return new JsonResponse([
                    'error' => "Course with given code already exists"
                ], Response::HTTP_BAD_REQUEST);
            }

            $course
                ->setTitle($courseDto->title)
                ->setType(CourseType::from($courseDto->type))
                ->setCode($courseDto->code)
                ->setPrice($courseDto->price)
            ;
            $this->entityManager->persist($course);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
