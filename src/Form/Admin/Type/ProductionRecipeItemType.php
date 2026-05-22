<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\ProductionRecipeItem;
use App\Entity\Store;
use App\Enum\ProductKindEnum;
use App\Repository\ProductRepository;
use App\Service\Quantity\QuantityFormatter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductionRecipeItemType extends AbstractType
{
	public function __construct(
		private readonly ProductRepository $productRepository,
		private readonly QuantityFormatter $quantityFormatter,
	)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var ProductionRecipeItem|null $item */
		$item = $builder->getData();

		$this->addMaterialField($builder, $options, $item?->getMaterial());
		$this->addQuantityField($builder, $item?->getMaterial(), $item);

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
			]);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
			$data = $event->getData();
			/** @var ProductionRecipeItem|null $item */
			$item = $event->getForm()->getData();

			if (!is_array($data) || !$options['store'] instanceof Store) {
				return;
			}

			if (empty($data['material']) && $item?->getMaterial() instanceof Product) {
				$data['material'] = $item->getMaterial()->getId();
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
				$this->addMaterialField($event->getForm(), $options, $material);
				$this->addQuantityField($event->getForm(), $material, $item);
			}
		});

		$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
			/** @var ProductionRecipeItem|null $item */
			$item = $event->getData();
			$form = $event->getForm();

			if (!$item instanceof ProductionRecipeItem || !$form->has('quantity')) {
				return;
			}

			$item->setQuantity($this->quantityFormatter->formatForStorage($form->get('quantity')->getData()));
		});
	}

	private function addQuantityField(FormBuilderInterface|FormInterface $form, ?Product $material = null, ?ProductionRecipeItem $item = null): void
	{
		$precision = $this->quantityFormatter->precisionForProduct($material);
		$isIntegerQuantity = $precision === 0;
		$step = $this->quantityFormatter->stepForPrecision($precision);

		$options = [
			'mapped' => false,
			'data' => $this->quantityFormatter->formatForForm($item?->getQuantity(), $material),
			'help' => $material?->getUnit()?->getCode() ?? ' ',
			'constraints' => [
				new GreaterThan([
					'value' => 0,
					'message' => 'Quantity must be greater than zero.',
				]),
			],
			'attr' => [
				'min' => $step,
				'step' => $step,
			],
		];

		if ($isIntegerQuantity) {
			$options['invalid_message'] = 'Quantity must be an integer.';
		} else {
			$options['html5'] = true;
			$options['scale'] = $precision;
		}

		$form->add('quantity', $isIntegerQuantity ? IntegerType::class : NumberType::class, $options);
	}

	private function addMaterialField(FormBuilderInterface|FormInterface $form, array $options, ?Product $selectedProduct = null): void
	{
		$form->add('material', EntityType::class, [
			'class' => Product::class,
			'choices' => $selectedProduct ? [$selectedProduct] : [],
			'choice_label' => fn (Product $product): string => sprintf('%s (%s)', $product->getName(), $product->getCode()),
			'placeholder' => 'Select material',
			'attr' => [
				'class' => 'select2',
				'data-select2-ajax-url' => $options['material_ajax_url'],
				'data-select2-minimum-input-length' => 3,
			],
			'constraints' => [new NotBlank(['message' => 'Select material.'])],
		]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ProductionRecipeItem::class,
			'store' => null,
			'material_ajax_url' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('material_ajax_url', ['null', 'string']);
	}
}
