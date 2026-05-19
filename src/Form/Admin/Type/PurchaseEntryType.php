<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\PurchaseEntry;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Repository\ProductRepository;
use App\Repository\WarehouseRepository;
use App\Validator\Constraints\PurchaseEntryForStore;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class PurchaseEntryType extends AbstractType
{
	public function __construct(private readonly ProductRepository $productRepository)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var PurchaseEntry|null $purchaseEntry */
		$purchaseEntry = $builder->getData();

		$this->addProductField($builder, $options, $purchaseEntry?->getProduct());

		$builder
			->add('warehouse', EntityType::class, [
				'class' => Warehouse::class,
				'query_builder' => fn (WarehouseRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])->orderBy('warehouse.name', 'ASC')
					: $repository->createQueryBuilder('warehouse')->andWhere('1 = 0'),
				'choice_label' => 'name',
				'placeholder' => 'Select warehouse',
				'attr' => ['class' => 'select2'],
				'constraints' => [new NotBlank(['message' => 'Select warehouse.'])],
			])
			->add('unitCost', NumberType::class, [
				'required' => true,
				'empty_data' => '0.0000',
				'html5' => true,
				'scale' => 4,
				'attr' => [
					'min' => 0,
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Unit cost cannot be negative.',
					]),
				],
			])
			->add('salePrice', NumberType::class, [
				'required' => false,
				'html5' => true,
				'scale' => 4,
				'attr' => [
					'min' => 0,
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Sale price cannot be negative.',
					]),
				],
			]);

		$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
			/** @var PurchaseEntry|null $purchaseEntry */
			$purchaseEntry = $event->getData();
			$this->addQuantityField($event->getForm(), $purchaseEntry?->getProduct(), $purchaseEntry);
		});

		$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
			$data = $event->getData();
			/** @var PurchaseEntry|null $purchaseEntry */
			$purchaseEntry = $event->getForm()->getData();

			if (!is_array($data) || !$options['store'] instanceof Store) {
				return;
			}

			if (empty($data['product']) && $purchaseEntry?->getProduct() instanceof Product) {
				$data['product'] = $purchaseEntry->getProduct()->getId();
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
				$this->addProductField($event->getForm(), $options, $product);
				$this->addQuantityField($event->getForm(), $product, $purchaseEntry);
			}
		});

		$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
			/** @var PurchaseEntry|null $purchaseEntry */
			$purchaseEntry = $event->getData();
			$form = $event->getForm();

			if (!$purchaseEntry instanceof PurchaseEntry || !$form->has('quantity')) {
				return;
			}

			$purchaseEntry->setQuantity($this->formatQuantityForModel($form->get('quantity')->getData()));
		});
	}

	private function addQuantityField(FormBuilderInterface|FormInterface $form, ?Product $product = null, ?PurchaseEntry $purchaseEntry = null): void
	{
		$precision = max(0, (int) ($product?->getUnit()?->getPrecision() ?? 4));
		$isIntegerQuantity = $precision === 0;
		$options = [
			'mapped' => false,
			'data' => $this->formatQuantityForForm($purchaseEntry?->getQuantity(), $precision),
			'help' => $product?->getUnit()?->getCode() ?? ' ',
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

	private function addProductField(FormBuilderInterface|FormInterface $form, array $options, ?Product $selectedProduct = null): void
	{
		$form->add('product', EntityType::class, [
			'class' => Product::class,
			'choices' => $selectedProduct ? [$selectedProduct] : [],
			'choice_label' => fn (Product $product) => sprintf('%s (%s)', $product->getName(), $product->getCode()),
			'placeholder' => 'Select product',
			'attr' => [
				'class' => 'select2',
				'data-select2-ajax-url' => $options['product_ajax_url'],
				'data-select2-minimum-input-length' => 3,
			],
			'constraints' => [new NotBlank(['message' => 'Select product.'])],
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
			'data_class' => PurchaseEntry::class,
			'store' => null,
			'product_ajax_url' => null,
			'constraints' => static fn (Options $options): array => $options['store'] instanceof Store
				? [new PurchaseEntryForStore($options['store'])]
				: [],
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('product_ajax_url', ['null', 'string']);
	}
}
