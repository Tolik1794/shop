<?php

namespace App\Form\Admin\FilterType;

use App\Entity\ProductionOrderStatus;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductionOrderFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('product', SearchType::class, [
				'label' => 'Product',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$qb->andWhere($qb->expr()->orX(
							'product.name like :productionOrderProduct',
							'product.code like :productionOrderProduct',
						))
							->setParameter('productionOrderProduct', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => ProductionOrderStatus::class,
				'choice_label' => fn (ProductionOrderStatus $choice): string => $choice->value,
				'label' => 'Status',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof ProductionOrderStatus) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.status = :productionOrderStatus', $rootAlias))
							->setParameter('productionOrderStatus', $value);
					}
				},
			])
			->add('warehouse', SearchType::class, [
				'label' => 'Warehouse',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$qb->andWhere('warehouse.name like :productionOrderWarehouse')
							->setParameter('productionOrderWarehouse', '%' . $value . '%');
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
