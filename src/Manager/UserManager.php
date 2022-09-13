<?php

namespace App\Manager;

use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Repository\StoreRepository;
use App\Repository\UserRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class UserManager extends AbstractManager
{
	const AVATAR_FOLDER = '/user/avatar';

	public function __construct(
		EntityManagerInterface        $entityManager,
		private readonly FileUploader $fileUp,
		private readonly Filesystem   $filesystem,
		private readonly RoleHierarchyInterface $roleHierarchy,
		private readonly Security $security,
		private readonly UserPasswordHasherInterface $passwordHasher,
	)
	{

		parent::__construct($entityManager);
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
		if (!$user) $user = $this->security->getUser();
		$reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

		if (in_array($role->name, $reachableRoles, true)) return true;

		return false;
	}

	public function updateAvatar(User $user, UploadedFile $uploadedFile): void
	{
		$fileUp = $this->fileUp;

		if ($user->getAvatar())
			$oldFile = $fileUp->getFile($fileUp->getImagePath($user->getAvatar(), self::AVATAR_FOLDER));

		$file = $fileUp->upload($uploadedFile, $fileUp->getImageDirectory(self::AVATAR_FOLDER));
		$user->setAvatar($file->getFilename());

		if (isset($oldFile)) $this->filesystem->remove($oldFile->getPathname());
	}

	public function getAvatar(User $user): ?File
	{
		if (!$user->getAvatar()) return null;
		return $this->fileUp->getFile($this->fileUp->getImagePath($user->getAvatar(), self::AVATAR_FOLDER));
	}

	public function updatePassword(User $user, string $password)
	{
		$user->setPassword($this->passwordHasher->hashPassword($user, $password));
	}
}