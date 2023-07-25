<?php

namespace App\Repository;

use App\Entity\User;
use App\Utils\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends EntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends EntityRepository
{
    public function filterQb(array $filter = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('user');

        if (isset($filter['fullName'])) {
            $qb
                ->andWhere('user.fullName LIKE :fullName')
                ->setParameter('fullName', "%{$filter['fullName']}%")
            ;
        }

        return $qb;
    }
}
