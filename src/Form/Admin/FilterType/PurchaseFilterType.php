<?php

namespace App\Form\Admin\FilterType;

use App\Entity\PurchaseStatus;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PurchaseFilterType extends AbstractType
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
						$qb->andWhere(sprintf('%s.number like :purchaseNumber', $rootAlias))
							->setParameter('purchaseNumber', '%' . $value . '%');
					}
				},
			])
			->add('supplier', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.supplierNameSnapshot like :purchaseSupplier', $rootAlias))
							->setParameter('purchaseSupplier', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => PurchaseStatus::class,
				'choice_label' => fn (PurchaseStatus $choice) => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof PurchaseStatus) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.status = :purchaseStatus', $rootAlias))
							->setParameter('purchaseStatus', $value);
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
