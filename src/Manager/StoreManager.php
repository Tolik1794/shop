<?php

namespace App\Manager;

use App\Entity\Store;
use App\Manager\Avatar\AvatarEntityInterface;
use App\Manager\Avatar\AvatarTrait;
use App\Repository\StoreRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StoreManager extends AbstractManager
{
	use AvatarTrait;

	const AVATAR_FOLDER = '/store/avatar';

	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly FileUploader $fileUp,
		private readonly Filesystem $filesystem
	)
	{
	}

	public function getRepository(): StoreRepository
	{
		return $this->entityManager->getRepository(Store::class);
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