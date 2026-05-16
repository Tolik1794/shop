<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\OrderEntry;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Repository\ProductRepository;
use App\Repository\WarehouseRepository;
use App\Validator\Constraints\OrderEntryForStore;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderEntryType extends AbstractType
{
	public function __construct(private readonly ProductRepository $productRepository)
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
			->add('discountAmount', NumberType::class, [
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
						'message' => 'Discount cannot be negative.',
					]),
				],
			]);

		$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
			/** @var OrderEntry|null $orderEntry */
			$orderEntry = $event->getData();
			$product = $orderEntry?->getProduct();

			$this->addProductField($event->getForm(), $product);
			$this->addQuantityField($event->getForm(), $product, $orderEntry);
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
			}
		});

		$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
			/** @var OrderEntry|null $orderEntry */
			$orderEntry = $event->getData();
			$form = $event->getForm();

			if (!$orderEntry instanceof OrderEntry || !$form->has('quantity')) {
				return;
			}

			$orderEntry->setQuantity($this->formatQuantityForModel($form->get('quantity')->getData()));
		});
	}

	private function addQuantityField(FormBuilderInterface|FormInterface $form, ?Product $product = null, ?OrderEntry $orderEntry = null): void
	{
		$precision = max(0, (int) ($product?->getUnit()?->getPrecision() ?? 4));
		$isIntegerQuantity = $precision === 0;

		$options = [
			'mapped' => false,
			'data' => $this->formatQuantityForForm($orderEntry?->getQuantity(), $precision),
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
				'min' => 1,
				'step' => 1,
			],
		];

		if ($isIntegerQuantity) {
			$options['invalid_message'] = 'Quantity must be an integer.';
		} else {
			$options['html5'] = true;
			$options['scale'] = $precision;
			$options['attr']['min'] = $this->quantityStep($precision);
			$options['attr']['step'] = $this->quantityStep($precision);
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

	private function quantityStep(int $precision): string
	{
		return '0.' . str_repeat('0', max(0, $precision - 1)) . '1';
	}

	private function formatQuantityForForm(?string $quantity, int $precision): string|int|null
	{
		if ($quantity === null || $quantity === '') {
			return null;
		}

		$value = (float) str_replace(',', '.', $quantity);

		return $precision === 0
			? (int) $value
			: number_format($value, $precision, '.', '');
	}

	private function formatQuantityForModel(string|int|float|null $quantity): string
	{
		if ($quantity === null || $quantity === '') {
			return '0.0000';
		}

		return number_format((float) str_replace(',', '.', (string) $quantity), 4, '.', '');
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => OrderEntry::class,
			'store' => null,
			'currency' => null,
			'constraints' => static fn(Options $options): array => $options['store'] instanceof Store
				? [new OrderEntryForStore($options['store'])]
				: [],
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('currency', ['null', Currency::class]);
	}
}
