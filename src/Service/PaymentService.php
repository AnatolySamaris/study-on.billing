<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\CourseType;
use App\Enum\TransactionType;
use App\Exception\NegativeDepositValue;
use App\Exception\NotEnoughBalanceException;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function payment(User $user, Course $course, DateTimeImmutable $createdAt = null): Transaction
    {
        if ($user->getBalance() < $course->getPrice()) {
            throw new NotEnoughBalanceException();
        }

        if ($createdAt === null) {
            $transactionTime = new DateTimeImmutable();
        } else {
            $transactionTime = $createdAt;
        }

        $transaction = new Transaction();
        $transaction->setDate($transactionTime);
        $transaction->setType(TransactionType::PAYMENT);
        $transaction->setCourse($course);
        $transaction->setBillingUser($user);

        if ($course->getType() == CourseType::FREE) {
            $transaction->setValue(0);
        } else {
            $transaction->setValue($course->getPrice());
        }

        if ($course->getType() == CourseType::RENT) {
            $transaction->setExpiredAt(
                $transactionTime->modify('+1 week') // Аренда на неделю
            );
        }

        $this->entityManager->wrapInTransaction(function () use ($transaction, $course, $user) {
            $user->setBalance($user->getBalance() - $transaction->getValue());
            $this->entityManager->persist($transaction);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        });

        return $transaction;
    }

    public function deposit(User $user, float $value, DateTimeImmutable $createdAt = null): User
    {
        if ($value < 0) {
            throw new NegativeDepositValue();
        }

        if ($createdAt === null) {
            $transactionTime = new DateTimeImmutable();
        } else {
            $transactionTime = $createdAt;
        }

        $this->entityManager->wrapInTransaction(function () use ($user, $value, $transactionTime) {
            $transaction = new Transaction();
            $transaction->setDate($transactionTime);
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
