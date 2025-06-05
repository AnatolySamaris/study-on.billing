<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\User;
use App\Enum\CourseType;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private PaymentService $paymentService
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Создание юзеров
        $user = new User();
        $user->setRoles(['ROLE_USER']);
        $user->setEmail('user@mail.ru');
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'password')
        );
        $user->setBalance(0.00);
        $manager->persist($user);

        $admin = new User();
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setEmail('admin@mail.ru');
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'password')
        );
        $admin->setBalance(0.00);
        $manager->persist($admin);

        $manager->flush();

        // Создание курсов
        $coursesData = [
            [
                "title" => "Python Junior",
                "code" => "python-junior",
                "type" => CourseType::RENT,
                "price" => 299.99
            ],
            [
                "title" => "Introduction to Neural Networks",
                "code" => "introduction-to-neural-networks",
                "type" => CourseType::RENT,
                "price" => 500.00
            ],
            [
                "title" => "Industrial WEB-development",
                "code" => "industrial-web-development",
                "type" => CourseType::PAY,
                "price" => 850.00
            ],
            [
                "title" => "Basics of Computer Vision",
                "code" => "basics-of-computer-vision",
                "type" => CourseType::PAY,
                "price" => 350.99
            ],
            [
                "title" => "ROS2 Course",
                "code" => "ros2-course",
                "type" => CourseType::FREE,
                "price" => 0.00
            ],
        ];
        foreach ($coursesData as $course) {
            $billingCourse = new Course();
            $billingCourse->setTitle($course['title']);
            $billingCourse->setCode($course['code']);
            $billingCourse->setType($course['type']);
            $billingCourse->setPrice($course['price']);
            $manager->persist($billingCourse);
        }
        $manager->flush();

        // Начисляем юзерам деньги на баланс
        $this->paymentService->deposit($user, 1250.99);
        $this->paymentService->deposit($admin, 99999.99);
    }
}
