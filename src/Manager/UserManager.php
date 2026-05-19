<?php

namespace App\Manager;

use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Manager\Avatar\AvatarTrait;
use App\Repository\UserRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class UserManager extends AbstractManager
{
	use AvatarTrait;
	const AVATAR_FOLDER = '/user/avatar';

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly Security $security,
		private readonly FileUploader $fileUp,
		private readonly Filesystem $filesystem,
		private readonly RoleHierarchyInterface $roleHierarchy,
		private readonly UserPasswordHasherInterface $passwordHasher,
	)
	{
	}

	public function getRepository(): UserRepository
	{
		return $this->entityManager->getRepository(User::class);
	}

	public function getCurrentUser(): UserInterface|User|null
	{
		return $this->security->getUser();
	}

	public function hasRole(RoleEnum $role, User|UserInterface $user = null): bool
	{
		if (!$user) $user = $this->getCurrentUser();
		$reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

		if (in_array($role->name, $reachableRoles, true)) return true;

		return false;
	}

	public function updatePassword(User $user, string $password)
	{
		$user->setPassword($this->passwordHasher->hashPassword($user, $password));
	}

	public function getAvatarFolder(): string
	{
		return static::AVATAR_FOLDER;
	}

	protected function getFileUp(): FileUploader
	{
		return $this->fileUp;
	}

	protected function getFilesystem(): Filesystem
	{
		return $this->filesystem;
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}