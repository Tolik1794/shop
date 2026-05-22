<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\ProductionOrderMaterial;
use App\Entity\Store;
use App\Enum\ProductKindEnum;
use App\Repository\ProductRepository;
use App\Service\Quantity\QuantityFormatter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductionOrderMaterialType extends AbstractType
{
	public function __construct(
		private readonly ProductRepository $productRepository,
		private readonly QuantityFormatter $quantityFormatter,
	)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var ProductionOrderMaterial|null $material */
		$material = $builder->getData();

		$this->addMaterialField($builder, $options);
		$this->addPlannedQuantityField($builder, $material?->getMaterial(), $material);

		$builder
			->add('wastePercent', NumberType::class, [
				'required' => false,
				'html5' => true,
				'scale' => 2,
				'attr' => [
					'min' => 0,
					'step' => '0.01',
				],
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Waste percent cannot be negative.',
					]),
				],
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			]);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
			$data = $event->getData();
			/** @var ProductionOrderMaterial|null $orderMaterial */
			$orderMaterial = $event->getForm()->getData();

			if (!is_array($data) || !$options['store'] instanceof Store) {
				return;
			}

			if (empty($data['material']) && $orderMaterial?->getMaterial() instanceof Product) {
				$data['material'] = $orderMaterial->getMaterial()->getId();
				$event->setData($data);
			}

			if (empty($data['material'])) {
				return;
			}

			$material = $this->productRepository->findOneBy([
				'id' => $data['material'],
				'store' => $options['store'],
				'productKind' => ProductKindEnum::MATERIAL,
			]);

			if ($material instanceof Product) {
				$this->addMaterialField($event->getForm(), $options);
				$this->addPlannedQuantityField($event->getForm(), $material, $orderMaterial);
			}
		});

		$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
			/** @var ProductionOrderMaterial|null $material */
			$material = $event->getData();
			$form = $event->getForm();

			if (!$material instanceof ProductionOrderMaterial || !$form->has('plannedQuantity')) {
				return;
			}

			$material->setPlannedQuantity($this->quantityFormatter->formatForStorage($form->get('plannedQuantity')->getData()));
		});
	}

	private function addMaterialField(FormBuilderInterface|FormInterface $form, array $options): void
	{
		$form->add('material', EntityType::class, [
			'class' => Product::class,
			'query_builder' => fn (ProductRepository $repository) => $options['store'] instanceof Store
				? $repository->findProductionMaterialsByStoreQB($options['store'])->orderBy('product.name', 'ASC')
				: $repository->createQueryBuilder('product')->andWhere('1 = 0'),
			'choice_label' => fn (Product $product): string => sprintf('%s (%s)', $product->getName(), $product->getCode()),
			'placeholder' => 'Select material',
			'attr' => ['class' => 'select2'],
			'constraints' => [new NotBlank(['message' => 'Select material.'])],
		]);
	}

	private function addPlannedQuantityField(FormBuilderInterface|FormInterface $form, ?Product $material = null, ?ProductionOrderMaterial $orderMaterial = null): void
	{
		$precision = $this->quantityFormatter->precisionForProduct($material);
		$isIntegerQuantity = $precision === 0;
		$step = $this->quantityFormatter->stepForPrecision($precision);

		$options = [
			'mapped' => false,
			'data' => $this->quantityFormatter->formatForForm($orderMaterial?->getPlannedQuantity(), $material),
			'help' => $material?->getUnit()?->getCode() ?? ' ',
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
			'data_class' => ProductionOrderMaterial::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
