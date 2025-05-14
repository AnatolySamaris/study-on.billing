<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseType;
use App\Enum\TransactionType;
use App\Exception\NegativeDepositValue;
use App\Exception\NotEnoughBalanceException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function payment(User $user, Course $course): Transaction
    {
        if ($user->getBalance() < $course->getPrice()) {
            throw new NotEnoughBalanceException();
        }

        $transactionTime = (new \DateTimeImmutable());

        $transaction = new Transaction();
        $transaction->setDate($transactionTime);
        $transaction->setType(TransactionType::PAYMENT);
        $transaction->setCourse($course);
        $transaction->setValue($course->getPrice());
        $transaction->setBillingUser($user);

        if ($course->getType() == CourseType::RENT) {
            $transaction->setExpiredAt(
                $transactionTime->modify('+1 week') // Аренда на неделю
            );
        }

        $this->entityManager->wrapInTransaction(function () use ($transaction, $course, $user) {
            $user->setBalance($user->getBalance() - $course->getPrice());
            $this->entityManager->persist($transaction);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        });

        return $transaction;
    }

    public function deposit(User $user, float $value): User
    {
        if ($value < 0) {
            throw new NegativeDepositValue();
        }

        $this->entityManager->wrapInTransaction(function () use ($user, $value) {
            $transaction = new Transaction();
            $transaction->setDate(new \DateTimeImmutable());
            $transaction->setType(TransactionType::DEPOSIT);
            $transaction->setValue($value);
            $transaction->setBillingUser($user);

            $user = $user->setBalance($user->getBalance() + $value);
            $this->entityManager->persist($transaction);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        });

        return $user;
    }
}
