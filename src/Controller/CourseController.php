<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'app_api_')]
final class CourseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CourseRepository $courseRepository
    ) {
    }

    #[Route(path: 'v1/courses', name: 'courses_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $courses = $this->courseRepository->findAll();

        $response = [];
        foreach ($courses as $course) {
            $item = [
                "code" => $course->getCode(),
                "type" => $course->getType(),
            ];
            if ($course->getType() != 'free') {
                $item["price"] = $course->getPrice();
            }
            $response[] = $item;
        }

        return new JsonResponse(
            $response,
            Response::HTTP_OK
        );
    }

    #[Route(path: '/v1/courses/{code}', name: 'courses_show', methods: ['GET'])]
    public function show(string $code): JsonResponse
    {
        $course = $this->courseRepository->findByCode($code);

        if (!$course) {
            return new JsonResponse([
                'error' => 'Course not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $response = [
            'code' => $course->getCode(),
            'type' => $course->getType()
        ];

        if ($course->getType() != 'free') {
            $response["price"] = $course->getPrice();
        }

        return new JsonResponse(
            $response,
            Response::HTTP_OK
        );
    }

    #[Route(path: '/v1/courses/{code}/pay', name: 'courses_pay', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function pay(string $code): JsonResponse
    {
        $course = $this->courseRepository->findByCode($code);

        if (!$course) {
            return new JsonResponse([
                'error' => 'Course not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($course->getType() == 'free') {
            return new JsonResponse([
                'success' => true,
                'course_type' => $course->getType(),
            ], Response::HTTP_CREATED);
        }

        try {
            $response = [
                'success' => true,
                'course_type' => $course->getType(),
            ];

            // ...

            // if ($course->getType() == 'rent') {
            //     $response['expired_at'] = $transaction->getExpiredAt()
            // }


            return new JsonResponse(
                $response,
                Response::HTTP_ACCEPTED
            );
        } catch (\Exception $e) {
            return new JsonResponse([
                'code' => 406,
                'message' => 'Not enough money for this operation'
            ]);
        }
    }
}
