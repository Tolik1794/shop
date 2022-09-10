<?php

namespace App\Manager;

use App\Entity\Store;
use App\Entity\User;
use App\Repository\StoreRepository;
use App\Repository\UserRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserManager extends AbstractManager
{
	const AVATAR_FOLDER = '/user/avatar';

	public function __construct(
		EntityManagerInterface        $entityManager,
		private readonly FileUploader $fileUp,
		private readonly Filesystem   $filesystem,
	)
	{

		parent::__construct($entityManager);
	}

	public function getRepository(): UserRepository
	{
		return $this->entityManager->getRepository(User::class);
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
}