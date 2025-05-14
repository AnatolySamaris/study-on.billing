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
