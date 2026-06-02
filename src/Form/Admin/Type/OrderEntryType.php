<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\ProductDiscountRule;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Entity\WarehouseStockBatch;
use App\Enum\OrderDiscountModeEnum;
use App\Repository\ProductRepository;
use App\Repository\WarehouseStockBatchRepository;
use App\Repository\WarehouseRepository;
use App\Service\Discount\ProductDiscountResolver;
use App\Service\Quantity\QuantityFormatter;
use App\Validator\Constraints\OrderEntryForStore;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderEntryType extends AbstractType
{
	public function __construct(
		private readonly ProductRepository $productRepository,
		private readonly WarehouseStockBatchRepository $warehouseStockBatchRepository,
		private readonly QuantityFormatter $quantityFormatter,
		private readonly ProductDiscountResolver $productDiscountResolver,
	)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$currencyCode = $options['currency'] instanceof Currency ? $options['currency']->getCode() : '';

		$builder
			->add('warehouse', EntityType::class, [
				'class' => Warehouse::class,
				'query_builder' => fn(WarehouseRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])->orderBy('warehouse.name', 'ASC')
					: $repository->createQueryBuilder('warehouse')->andWhere('1 = 0'),
				'choice_label' => 'name',
				'required' => false,
				'attr' => ['class' => 'select2'],
			])
			->add('unitPrice', NumberType::class, [
				'required' => false,
				'empty_data' => '0.0000',
				'html5' => true,
				'scale' => 4,
				'help' => $currencyCode,
				'help_attr' => [
					'data-order-currency-label' => '',
				],
				'attr' => [
					'min' => 0,
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Unit price cannot be negative.',
					]),
				],
			])
			;

		$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
			/** @var OrderEntry|null $orderEntry */
			$orderEntry = $event->getData();
			$product = $orderEntry?->getProduct();

			$this->addWarehouseStockBatchField($event->getForm(), $orderEntry);
			$this->addProductField($event->getForm(), $product);
			$this->addQuantityField($event->getForm(), $product, $orderEntry);
			$this->addDiscountFields($event->getForm(), $product, $orderEntry, $options['store']);
		});

		$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
			$data = $event->getData();
			/** @var OrderEntry|null $orderEntry */
			$orderEntry = $event->getForm()->getData();

			if (!is_array($data) || !$options['store'] instanceof Store) {
				return;
			}

			if (empty($data['product']) && $orderEntry?->getProduct() instanceof Product) {
				$data['product'] = $orderEntry->getProduct()->getId();
				$event->setData($data);
			}

			if (empty($data['product'])) {
				return;
			}

			$product = $this->productRepository->findOneBy([
				'id' => $data['product'],
				'store' => $options['store'],
			]);

			if ($product instanceof Product) {
				$this->addProductField($event->getForm(), $product);
				$this->addQuantityField($event->getForm(), $product, $orderEntry);
				$this->addDiscountFields($event->getForm(), $product, $orderEntry, $options['store']);
			}
		});

		$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
			/** @var OrderEntry|null $orderEntry */
			$orderEntry = $event->getData();
			$form = $event->getForm();

			if (!$orderEntry instanceof OrderEntry || !$form->has('quantity')) {
				return;
			}

			$orderEntry->setQuantity($this->quantityFormatter->formatForStorage($form->get('quantity')->getData()));

			$batchId = $form->has('warehouseStockBatchId') ? $form->get('warehouseStockBatchId')->getData() : null;
			$batch = $batchId ? $this->warehouseStockBatchRepository->find($batchId) : null;
			$orderEntry->setWarehouseStockBatch($batch instanceof WarehouseStockBatch ? $batch : null);
		});
	}

	private function addWarehouseStockBatchField(FormBuilderInterface|FormInterface $form, ?OrderEntry $orderEntry = null): void
	{
		$form->add('warehouseStockBatchId', HiddenType::class, [
			'mapped' => false,
			'required' => false,
			'data' => $orderEntry?->getWarehouseStockBatch()?->getId(),
			'attr' => [
				'data-order-entry-target' => 'warehouseStockBatch',
			],
		]);
	}

	private function addQuantityField(FormBuilderInterface|FormInterface $form, ?Product $product = null, ?OrderEntry $orderEntry = null): void
	{
		$precision = $this->quantityFormatter->precisionForProduct($product);
		$isIntegerQuantity = $precision === 0;
		$step = $this->quantityFormatter->stepForPrecision($precision);

		$options = [
			'mapped' => false,
			'data' => $this->quantityFormatter->formatForForm($orderEntry?->getQuantity(), $product),
			'help' => $product?->getUnit()?->getCode() ?? ' ',
			'help_attr' => [
				'data-order-entry-target' => 'unitLabel',
			],
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

	private function addProductField(FormBuilderInterface|FormInterface $form, ?Product $selectedProduct = null): void
	{
		$form->add('product', EntityType::class, [
			'class' => Product::class,
			'choices' => $selectedProduct ? [$selectedProduct] : [],
			'choice_label' => fn(Product $product) => sprintf('%s (%s)', $product->getName(), $product->getCode()),
			'choice_attr' => fn(Product $product) => [
				'data-code' => $product->getCode(),
				'data-price' => $product->getBaseSalePrice(),
			],
			'placeholder' => 'Select product',
			'required' => false,
			'constraints' => [
				new NotBlank(['message' => 'Select product.']),
			],
			'attr' => [
				'class' => 'order-entry-product',
			],
		]);
	}

	private function addDiscountFields(FormBuilderInterface|FormInterface $form, ?Product $product = null, ?OrderEntry $orderEntry = null, ?Store $store = null): void
	{
		$rules = $this->discountRuleChoices($product, $orderEntry, $store);

		$form
			->add('discountMode', ChoiceType::class, [
				'choices' => [
					'No discount' => OrderDiscountModeEnum::NONE,
					'Allowed discount' => OrderDiscountModeEnum::RULE,
					'Manual override' => OrderDiscountModeEnum::MANUAL_OVERRIDE,
				],
				'choice_value' => static fn (?OrderDiscountModeEnum $choice): string => $choice?->value ?? '',
				'required' => false,
				'placeholder' => false,
			])
			->add('discountRule', EntityType::class, [
				'class' => ProductDiscountRule::class,
				'choices' => $rules,
				'choice_label' => static fn (ProductDiscountRule $rule): string => sprintf('%s (%s%%)', $rule->getName(), $rule->getPercent()),
				'choice_attr' => static fn (ProductDiscountRule $rule): array => [
					'data-percent' => $rule->getPercent(),
					'data-name' => $rule->getName(),
				],
				'placeholder' => 'No discount',
				'required' => false,
			])
			->add('discountPercent', NumberType::class, [
				'required' => false,
				'html5' => true,
				'scale' => 4,
				'attr' => [
					'min' => 0,
					'max' => 100,
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Discount cannot be negative.',
					]),
					new LessThanOrEqual([
						'value' => 100,
						'message' => 'Discount percent cannot exceed 100.',
					]),
				],
			]);
	}

	/**
	 * @return ProductDiscountRule[]
	 */
	private function discountRuleChoices(?Product $product, ?OrderEntry $orderEntry, ?Store $store = null): array
	{
		$store ??= $orderEntry?->getOrder()?->getStore();
		$rules = $product instanceof Product && $store instanceof Store
			? $this->productDiscountResolver->allowedRules($product, $store)
			: [];
		$currentRule = $orderEntry?->getDiscountRule();

		if ($currentRule instanceof ProductDiscountRule && !in_array($currentRule, $rules, true)) {
			array_unshift($rules, $currentRule);
		}

		return $rules;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => OrderEntry::class,
			'store' => null,
			'currency' => null,
			'can_discount_override' => false,
			'constraints' => static fn(Options $options): array => $options['store'] instanceof Store
				? [new OrderEntryForStore($options['store'])]
				: [],
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('currency', ['null', Currency::class]);
		$resolver->setAllowedTypes('can_discount_override', ['bool']);
	}
}
