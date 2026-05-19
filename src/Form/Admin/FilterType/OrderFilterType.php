<?php

namespace App\Form\Admin\FilterType;

use App\Entity\OrderStatus;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('number', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.number like :orderNumber', $rootAlias))
							->setParameter('orderNumber', '%' . $value . '%');
					}
				},
			])
			->add('customer', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.customerNameSnapshot like :orderCustomer', $rootAlias))
							->setParameter('orderCustomer', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => OrderStatus::class,
				'choice_label' => fn(OrderStatus $choice) => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof OrderStatus) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.status = :orderStatus', $rootAlias))
							->setParameter('orderStatus', $value);
					}
				},
			])
			->setMethod('GET');
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'csrf_protection' => false,
		]);
	}
}
