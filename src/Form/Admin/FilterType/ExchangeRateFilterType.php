<?php

namespace App\Form\Admin\FilterType;

use App\Entity\Currency;
use App\Entity\ExchangeRate;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExchangeRateFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('fromCurrency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'label' => 'From',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof Currency) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.fromCurrency = :fromCurrency', $rootAlias))
							->setParameter('fromCurrency', $value);
					}
				},
			])
			->add('toCurrency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'label' => 'To',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof Currency) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.toCurrency = :toCurrency', $rootAlias))
							->setParameter('toCurrency', $value);
					}
				},
			])
			->add('source', SearchType::class, [
				'label' => 'Source',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.source like :source', $rootAlias))
							->setParameter('source', '%' . $value . '%');
					}
				},
			])
			->setMethod('GET');
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ExchangeRate::class,
			'csrf_protection' => false,
		]);
	}
}
