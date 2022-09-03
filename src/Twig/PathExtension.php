<?php

namespace App\Twig;

use App\Enum\ActiveStatusEnum;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PathExtension extends AbstractExtension
{
	public function __construct(private readonly ParameterBagInterface $parameterBag)
	{
	}

	public function getFilters()
	{
		return [
			new TwigFilter('trim_full_path', [$this, 'trimFullPath']),
		];
	}

	public function trimFullPath(string $path): string
	{
		return str_replace($this->parameterBag->get('kernel.project_dir') . '/public', '', $path);
	}
}