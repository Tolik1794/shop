<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QueryCallableExtension extends AbstractTypeExtension
{
	public const NAME = 'query_callback';

	public static function getExtendedTypes(): iterable
	{
		return [FormType::class];
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefault(self::NAME, null);
		$resolver->setAllowedTypes(self::NAME, ['null', 'callable']);
	}
}