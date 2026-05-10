<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\Store;
use App\Entity\WarehouseStock;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class WarehouseStockType extends AbstractType
{
	public function __construct(private readonly ProductRepository $productRepository)
	{
	}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
	    /** @var WarehouseStock|null $warehouseStock */
	    $warehouseStock = $builder->getData();

	    $this->addProductField($builder, $options, $warehouseStock?->getProduct());

        $builder
            ->add('quantityOnHand', null, [
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Quantity on hand cannot be negative.',
					]),
				],
            ])
            ->add('reservedQuantity', null, [
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Reserved quantity cannot be negative.',
					]),
				],
            ])
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

		    if (!is_array($data) || empty($data['product']) || !$options['store'] instanceof Store) {
			    return;
		    }

		    $product = $this->productRepository->findOneBy([
			    'id' => $data['product'],
			    'store' => $options['store'],
		    ]);

		    if ($product instanceof Product) {
			    $this->addProductField($event->getForm(), $options, $product);
		    }
	    });
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
