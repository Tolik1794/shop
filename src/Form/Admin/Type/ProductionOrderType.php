<?php

namespace App\Form\Admin\Type;

use App\Entity\ProductionOrder;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Repository\WarehouseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class ProductionOrderType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
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
			])
			->add('materials', CollectionType::class, [
				'entry_type' => ProductionOrderMaterialType::class,
				'entry_options' => [
					'label' => false,
					'store' => $options['store'],
				],
				'allow_add' => true,
				'allow_delete' => true,
				'by_reference' => false,
				'label' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ProductionOrder::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
