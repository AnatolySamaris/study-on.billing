<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Enum\TransactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function getFilteredTransactions(
        string $username,
        int $type = null,
        string $courseCode = null,
        bool $skipExpired = false
    ) {
        $request = $this->createQueryBuilder('t')
            ->leftJoin('t.course', 'c')
            ->innerJoin('t.billingUser', 'u', Join::WITH, 'u.email = :username')
            ->setParameter('username', $username);

        if ($type) {
            $request->andWhere('t.type = :transactionType')
                ->setParameter('transactionType', $type, Types::SMALLINT);
        }
        if ($courseCode) {
            $request->andWhere('c.code = :code')
                ->setParameter('code', $courseCode);
        }
        if ($skipExpired) {
            return $request
                ->andWhere('t.expiredAt > :now OR t.expiredAt is null')
                ->setParameter('now', new DateTime(), Types::DATETIME_MUTABLE)
                ->getQuery()
                ->getResult();
        }

        return $request
            ->getQuery()
            ->getResult();
    }

    public function findCoursesEndingSoon(): array
    {
        $start = new DateTime();
        $end = (clone $start)->modify('+1 day');
        
        return $this->createQueryBuilder('t')
            ->andWhere('t.type = :type')
            ->andWhere('t.expiredAt BETWEEN :start AND :end')
            ->setParameter('type', TransactionType::PAYMENT)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    public function getMonthlyReportData(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->select([
                'c.title as course_title',
                't.type',
                'COUNT(t.id) as count',
                'SUM(t.value) as amount',
            ])
            ->join('t.course', 'c')
            ->where('t.date BETWEEN :start AND :end')
            ->andWhere('t.type = :type')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('type', TransactionType::PAYMENT->value)
            ->groupBy('c.id', 't.type')
            ->getQuery()
            ->getArrayResult();
    }
}
