<?php

namespace App\Manager\Avatar;

use App\Entity\Store;
use App\Service\FileUploader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait AvatarTrait
{
	abstract public function getAvatarFolder(): string;
	abstract protected function getFileUp(): FileUploader;
	abstract protected function getFilesystem(): Filesystem;

	public function updateAvatar(AvatarEntityInterface $avatarEntity, UploadedFile $uploadedFile): void
	{
		$fileUp = $this->getFileUp();
		$avatarFolder = $this->getAvatarFolder();

		if ($avatar = $avatarEntity->getAvatar())
			$oldFile = $fileUp->getFile($fileUp->getImagePath($avatar, $avatarFolder));

		$file = $fileUp->upload($uploadedFile, $fileUp->getImageDirectory($avatarFolder));
		$avatarEntity->setAvatar($file->getFilename());

		if (isset($oldFile)) $this->getFilesystem()->remove($oldFile->getPathname());
	}

	public function getAvatar(AvatarEntityInterface $store): ?File
	{
		if (!$store->getAvatar()) return null;

		$fileUp = $this->getFileUp();

		return $fileUp->getFile($fileUp->getImagePath($store->getAvatar(), $this->getAvatarFolder()));
	}
}