<?php

namespace App\Manager;

use App\Entity\Store;
use App\Repository\StoreRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StoreManager extends AbstractManager
{
	const AVATAR_FOLDER = '/store/avatar';

	public function __construct(
		EntityManagerInterface        $entityManager,
		private readonly FileUploader $fileUp,
		private readonly Filesystem   $filesystem,
	)
	{

		parent::__construct($entityManager);
	}

	public function getRepository(): StoreRepository
	{
		return $this->entityManager->getRepository(Store::class);
	}

	public function updateAvatar(Store $store, UploadedFile $uploadedFile): void
	{
		$fileUp = $this->fileUp;

		if ($store->getAvatar())
			$oldFile = $fileUp->getFile($fileUp->getImagePath($store->getAvatar(), self::AVATAR_FOLDER));

		$file = $fileUp->upload($uploadedFile, $fileUp->getImageDirectory(self::AVATAR_FOLDER));
		$store->setAvatar($file->getFilename());

		if (isset($oldFile)) $this->filesystem->remove($oldFile->getPathname());
	}

	public function getAvatar(Store $store): ?File
	{
		if (!$store->getAvatar()) return null;
		return $this->fileUp->getFile($this->fileUp->getImagePath($store->getAvatar(), self::AVATAR_FOLDER));
	}
}