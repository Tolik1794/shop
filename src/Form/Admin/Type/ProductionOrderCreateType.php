<?php

namespace App\Form\Admin\Type;

use App\Entity\ProductionRecipe;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Enum\ActiveStatusEnum;
use App\Repository\ProductionRecipeRepository;
use App\Repository\WarehouseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class ProductionOrderCreateType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('recipe', EntityType::class, [
				'class' => ProductionRecipe::class,
				'query_builder' => fn (ProductionRecipeRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])
						->andWhere('productionRecipe.status = :activeStatus')
						->setParameter('activeStatus', ActiveStatusEnum::ACTIVE)
						->orderBy('productionRecipe.name', 'ASC')
					: $repository->createQueryBuilder('productionRecipe')->andWhere('1 = 0'),
				'choice_label' => fn (ProductionRecipe $recipe): string => sprintf('%s - %s', $recipe->getName(), $recipe->getProduct()?->getName()),
				'placeholder' => 'Select recipe',
				'attr' => ['class' => 'select2'],
			])
			->add('warehouse', EntityType::class, [
				'class' => Warehouse::class,
				'query_builder' => fn (WarehouseRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])->orderBy('warehouse.name', 'ASC')
					: $repository->createQueryBuilder('warehouse')->andWhere('1 = 0'),
				'choice_label' => 'name',
				'placeholder' => 'Select warehouse',
				'required' => false,
				'attr' => ['class' => 'select2'],
			])
			->add('plannedQuantity', NumberType::class, [
				'html5' => true,
				'scale' => 4,
				'attr' => [
					'min' => '0.0001',
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThan([
						'value' => 0,
						'message' => 'Planned quantity must be greater than zero.',
					]),
				],
			])
			->add('plannedStartAt', DateTimeType::class, [
				'required' => false,
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('plannedEndAt', DateTimeType::class, [
				'required' => false,
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'store' => null,
			'data_class' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
