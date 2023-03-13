<?php

namespace App\Repository;

use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Manager\UserManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
	public function __construct(ManagerRegistry $registry, private readonly UserManager $userManager)
	{
		parent::__construct($registry, User::class);
	}

	public function add(User $entity, bool $flush = false): void
	{
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function remove(User $entity, bool $flush = false): void
	{
		$this->getEntityManager()->remove($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	/**
	 * Used to upgrade (rehash) the user's password automatically over time.
	 */
	public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
	{
		if (!$user instanceof User) {
			throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
		}

		$user->setPassword($newHashedPassword);

		$this->add($user, true);
	}

	public function findUsersToShowQB(User|UserInterface $user): QueryBuilder
	{
		$qb = $this->createQueryBuilder('user');

		if ($this->userManager->hasRole(RoleEnum::ROLE_SUPER_ADMIN, $user)) return $qb;

		$director = $this->userManager->hasRole(RoleEnum::ROLE_STORE_ADMIN, $user) ? $user : $user->getParent();

		$qb->where('user.parent = :user')
			->setParameter('user', $director);

		return $qb;
	}
}
