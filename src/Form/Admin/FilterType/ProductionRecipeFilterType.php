<?php

namespace App\Form\Admin\FilterType;

use App\Enum\ActiveStatusEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductionRecipeFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', SearchType::class, [
				'label' => 'Name',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.name like :productionRecipeName', $rootAlias))
							->setParameter('productionRecipeName', '%' . $value . '%');
					}
				},
			])
			->add('product', SearchType::class, [
				'label' => 'Product',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$qb->andWhere($qb->expr()->orX(
							'product.name like :productionRecipeProduct',
							'product.code like :productionRecipeProduct',
						))
							->setParameter('productionRecipeProduct', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choice_label' => fn (ActiveStatusEnum $choice): string => $choice->value,
				'label' => 'Status',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof ActiveStatusEnum) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.status = :productionRecipeStatus', $rootAlias))
							->setParameter('productionRecipeStatus', $value);
					}
				},
			])
			->add('isDefault', ChoiceType::class, [
				'label' => 'Default',
				'required' => false,
				'mapped' => false,
				'choices' => [
					'Yes' => true,
					'No' => false,
				],
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value !== null && $value !== '') {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.isDefault = :productionRecipeDefault', $rootAlias))
							->setParameter('productionRecipeDefault', $value);
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
