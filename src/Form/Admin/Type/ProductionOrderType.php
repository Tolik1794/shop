<?php

namespace App\Form\Admin\Type;

use App\Entity\ProductionOrder;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Repository\WarehouseRepository;
use App\Service\Quantity\QuantityFormatter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class ProductionOrderType extends AbstractType
{
	public function __construct(private readonly QuantityFormatter $quantityFormatter)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var ProductionOrder|null $productionOrder */
		$productionOrder = $builder->getData();

		$this->addPlannedQuantityField($builder, $productionOrder);

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

		$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
			/** @var ProductionOrder|null $productionOrder */
			$productionOrder = $event->getData();
			$form = $event->getForm();

			if (!$productionOrder instanceof ProductionOrder || !$form->has('plannedQuantity')) {
				return;
			}

			$productionOrder->setPlannedQuantity($this->quantityFormatter->formatForStorage($form->get('plannedQuantity')->getData()));
		});
	}

	private function addPlannedQuantityField(FormBuilderInterface|FormInterface $form, ?ProductionOrder $productionOrder = null): void
	{
		$product = $productionOrder?->getProduct();
		$precision = $this->quantityFormatter->precisionForProduct($product);
		$isIntegerQuantity = $precision === 0;
		$step = $this->quantityFormatter->stepForPrecision($precision);

		$options = [
			'mapped' => false,
			'data' => $this->quantityFormatter->formatForForm($productionOrder?->getPlannedQuantity(), $product),
			'help' => $product?->getUnit()?->getCode() ?? ' ',
			'constraints' => [
				new GreaterThan([
					'value' => 0,
					'message' => 'Planned quantity must be greater than zero.',
				]),
			],
			'attr' => [
				'min' => $step,
				'step' => $step,
			],
		];

		if ($isIntegerQuantity) {
			$options['invalid_message'] = 'Planned quantity must be an integer.';
		} else {
			$options['html5'] = true;
			$options['scale'] = $precision;
		}

		$form->add('plannedQuantity', $isIntegerQuantity ? IntegerType::class : NumberType::class, $options);
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
