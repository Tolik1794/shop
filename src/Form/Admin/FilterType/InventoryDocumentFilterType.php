<?php

namespace App\Form\Admin\FilterType;

use App\Enum\InventoryDocumentStatus;
use App\Enum\InventoryDocumentType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InventoryDocumentFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('number', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.number like :inventoryDocumentNumber', $rootAlias))
							->setParameter('inventoryDocumentNumber', '%' . $value . '%');
					}
				},
			])
			->add('type', EnumType::class, [
				'class' => InventoryDocumentType::class,
				'choice_label' => fn(InventoryDocumentType $choice): string => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof InventoryDocumentType) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.type = :inventoryDocumentType', $rootAlias))
							->setParameter('inventoryDocumentType', $value);
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => InventoryDocumentStatus::class,
				'choice_label' => fn(InventoryDocumentStatus $choice): string => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof InventoryDocumentStatus) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.status = :inventoryDocumentStatus', $rootAlias))
							->setParameter('inventoryDocumentStatus', $value);
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
