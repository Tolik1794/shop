<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
	private string $imageDirectory;
	private string $appImageDirectory;

	public function __construct(
		private readonly SluggerInterface $slugger,
		ParameterBagInterface             $parameterBag,
	)
	{
		$this->imageDirectory = $parameterBag->get('image_directory');
		$this->appImageDirectory = $parameterBag->get('app_image_directory');
	}

	public function upload(UploadedFile $file, string $directory): File
	{
		$originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
		$safeFilename = $this->slugger->slug($originalFilename);
		$fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

		return $file->move($directory, $fileName);
	}

	public function getFile(string $filePath): File
	{
		return new File($filePath, false);
	}

	public function getAppImagePath(string $imageName, string $folder = '/'): string
	{
		return $this->appImageDirectory . $folder . '/' . $imageName;
	}

	public function getImagePath(string $imageName, string $folder = ''): string
	{
		return $this->imageDirectory . $folder . '/' . $imageName;
	}

	public function getImageDirectory(string $folder = ''): string
	{
		return $this->imageDirectory . $folder;
	}

	public function getAppImageDirectory(string $folder = ''): string
	{
		return $this->appImageDirectory . $folder;
	}
}