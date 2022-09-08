<?php

namespace App\Form\Admin\FilterType;

use App\Entity\Store;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StoreFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', SearchType::class, [
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getAllAliases());

						$qb->andWhere(sprintf('%s.name like :name', $rootAlias))
							->setParameter('name', '%' . $value . '%');
					}
				},
				'mapped' => false
			])
			->setMethod('GET');
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'csrf_protection' => false
		]);
	}
}
