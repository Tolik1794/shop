<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\Store;
use App\Entity\WarehouseStock;
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
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class WarehouseStockType extends AbstractType
{
	public function __construct(
		private readonly ProductRepository $productRepository,
		private readonly QuantityFormatter $quantityFormatter,
	)
	{
	}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
	    /** @var WarehouseStock|null $warehouseStock */
	    $warehouseStock = $builder->getData();

	    $this->addProductField($builder, $options, $warehouseStock?->getProduct());
	    $this->addQuantityField($builder, 'quantityOnHand', 'Quantity on hand cannot be negative.', $warehouseStock?->getProduct(), $warehouseStock?->getQuantityOnHand());
	    $this->addQuantityField($builder, 'reservedQuantity', 'Reserved quantity cannot be negative.', $warehouseStock?->getProduct(), $warehouseStock?->getReservedQuantity());

        $builder
	        ->add('averageCost', null, [
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Average cost cannot be negative.',
					]),
				],
            ])
        ;

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
		    $data = $event->getData();
		    /** @var WarehouseStock|null $warehouseStock */
		    $warehouseStock = $event->getForm()->getData();

		    if (!is_array($data) || !$options['store'] instanceof Store) {
			    return;
		    }

		    if (empty($data['product']) && $warehouseStock?->getProduct() instanceof Product) {
			    $data['product'] = $warehouseStock->getProduct()->getId();
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
			    $this->addQuantityField($event->getForm(), 'quantityOnHand', 'Quantity on hand cannot be negative.', $product, $warehouseStock?->getQuantityOnHand());
			    $this->addQuantityField($event->getForm(), 'reservedQuantity', 'Reserved quantity cannot be negative.', $product, $warehouseStock?->getReservedQuantity());
		    }
	    });

	    $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
		    /** @var WarehouseStock|null $warehouseStock */
		    $warehouseStock = $event->getData();
		    $form = $event->getForm();

		    if (!$warehouseStock instanceof WarehouseStock) {
			    return;
		    }

		    if ($form->has('quantityOnHand')) {
			    $warehouseStock->setQuantityOnHand($this->quantityFormatter->formatForStorage($form->get('quantityOnHand')->getData()));
		    }

		    if ($form->has('reservedQuantity')) {
			    $warehouseStock->setReservedQuantity($this->quantityFormatter->formatForStorage($form->get('reservedQuantity')->getData()));
		    }
	    });
    }

	private function addQuantityField(
		FormBuilderInterface|FormInterface $form,
		string $name,
		string $negativeMessage,
		?Product $product = null,
		?string $quantity = null,
	): void
	{
		$precision = $this->quantityFormatter->precisionForProduct($product);
		$isIntegerQuantity = $precision === 0;
		$step = $this->quantityFormatter->stepForPrecision($precision);

		$options = [
			'mapped' => false,
			'data' => $this->quantityFormatter->formatForForm($quantity, $product),
			'help' => $product?->getUnit()?->getCode() ?? ' ',
			'constraints' => [
				new GreaterThanOrEqual([
					'value' => 0,
					'message' => $negativeMessage,
				]),
			],
			'attr' => [
				'min' => 0,
				'step' => $step,
			],
		];

		if ($isIntegerQuantity) {
			$options['invalid_message'] = 'Quantity must be an integer.';
		} else {
			$options['html5'] = true;
			$options['scale'] = $precision;
			$options['attr']['min'] = '0';
		}

		$form->add($name, $isIntegerQuantity ? IntegerType::class : NumberType::class, $options);
	}

	private function addProductField(FormBuilderInterface|FormInterface $form, array $options, ?Product $selectedProduct = null): void
	{
		$form->add('product', EntityType::class, [
			'class' => Product::class,
			'choices' => $selectedProduct ? [$selectedProduct] : [],
			'choice_label' => fn(Product $product) => sprintf('%s (%s)', $product->getName(), $product->getCode()),
			'placeholder' => 'Select product',
			'required' => true,
			'attr' => [
				'class' => 'select2',
				'data-select2-ajax-url' => $options['product_ajax_url'],
				'data-select2-minimum-input-length' => 3,
			],
		]);
	}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WarehouseStock::class,
	        'product_ajax_url' => null,
	        'store' => null,
        ]);

	    $resolver->setAllowedTypes('product_ajax_url', ['null', 'string']);
	    $resolver->setAllowedTypes('store', ['null', Store::class]);
    }
}
