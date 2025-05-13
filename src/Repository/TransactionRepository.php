<?php

namespace App\Repository;

use App\Entity\Transaction;
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

    public function getUserFilteredTransactions(
        string $username,
        int $type = null,
        string $courseCode = null,
        bool $skipExpired = false
    ) {
        $request = $this->createQueryBuilder('t')
            ->select(
                't.id',
                't.date AS created_at',
                't.expired_at AS expires_at',
                't.type',
                'c.character_code AS course_code',
                't.value as amount'
            )
            ->leftJoin('t.course', 'c')
            ->innerJoin('t.billing_user', 'u', Join::WITH, 'u.email = :username')
            ->setParameter('username', $username);

        if ($type) {
            $request->andWhere('t.type = :transactionType')
                ->setParameter('transactionType', $type, Types::SMALLINT);
        }
        if ($courseCode) {
            $request->andWhere('c.character_code = :code')
                ->setParameter('code', $courseCode);
        }
        if ($skipExpired) {
            return $request
                ->andWhere('t.expired_at > :now OR t.expired_at is null')
                ->setParameter('now', new DateTime(), Types::DATETIME_MUTABLE)
                ->getQuery()
                ->getArrayResult();
        }

        return $request
            ->getQuery()
            ->getArrayResult();
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
