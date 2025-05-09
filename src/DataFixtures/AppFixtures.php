<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER']);
        $user->setEmail('user@mail.ru');
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'password')
        );
        $user->setBalance(1250.99);
        $manager->persist($user);

        $admin = new User();
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setEmail('admin@mail.ru');
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'password')
        );
        $admin->setBalance(99999.99);
        $manager->persist($admin);

        $manager->flush();
    }
}
