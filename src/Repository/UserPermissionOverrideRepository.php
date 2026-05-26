<?php

namespace App\Repository;

use App\Entity\User\UserPermissionOverride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPermissionOverride>
 */
class UserPermissionOverrideRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, UserPermissionOverride::class);
	}
}
