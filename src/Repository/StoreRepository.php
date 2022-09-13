<?php

namespace App\Repository;

use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Manager\UserManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Store>
 *
 * @method Store|null find($id, $lockMode = null, $lockVersion = null)
 * @method Store|null findOneBy(array $criteria, array $orderBy = null)
 * @method Store[]    findAll()
 * @method Store[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly UserManager $userManager)
    {
        parent::__construct($registry, Store::class);
    }

    public function add(Store $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Store $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

	public function findAvailableStoresQB(User|UserInterface $user): QueryBuilder
	{
		$qb = $this->createQueryBuilder('store')
			->leftJoin('store.managers', 'managers');

		if ($this->userManager->hasRole(RoleEnum::ROLE_SUPER_ADMIN, $user)) return $qb;
		elseif ($this->userManager->hasRole(RoleEnum::ROLE_ADMIN, $user)) $manager = $user;
		elseif ($this->userManager->hasRole(RoleEnum::ROLE_STORE_ADMIN, $user)) $manager = $user;
		elseif ($user->getManagerStores()->count() > 0) $manager = $user;
		else $manager = $user->getParent();

		$qb->where('managers = :manager')
			->setParameter('manager', $manager);

		return $qb;
	}
}
