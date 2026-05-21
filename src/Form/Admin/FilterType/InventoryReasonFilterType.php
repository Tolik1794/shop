<?php

namespace App\Form\Admin\FilterType;

use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryReasonType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InventoryReasonFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', SearchType::class, [
				'label' => 'Name',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.name like :inventoryReasonName', $rootAlias))
							->setParameter('inventoryReasonName', '%' . $value . '%');
					}
				},
			])
			->add('type', EnumType::class, [
				'class' => InventoryReasonType::class,
				'choice_label' => fn(InventoryReasonType $choice): string => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof InventoryReasonType) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.type = :inventoryReasonType', $rootAlias))
							->setParameter('inventoryReasonType', $value);
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choice_label' => fn(ActiveStatusEnum $choice): string => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof ActiveStatusEnum) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.status = :inventoryReasonStatus', $rootAlias))
							->setParameter('inventoryReasonStatus', $value);
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
