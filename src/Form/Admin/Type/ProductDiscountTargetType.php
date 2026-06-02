<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductDiscountTarget;
use App\Entity\Store;
use App\Enum\ProductDiscountTargetTypeEnum;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductDiscountTargetType extends AbstractType
{
	public function __construct(private readonly ProductRepository $productRepository)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var ProductDiscountTarget|null $target */
		$target = $builder->getData();
		/** @var Store|null $store */
		$store = $options['store'];

		$builder
			->add('targetType', EnumType::class, [
				'class' => ProductDiscountTargetTypeEnum::class,
				'choice_label' => static fn (ProductDiscountTargetTypeEnum $choice): string => 'admin.discount.target.' . $choice->value,
			])
			->add('category', EntityType::class, [
				'class' => Category::class,
				'query_builder' => fn (CategoryRepository $repository) => $store instanceof Store
					? $repository->findAvailableCategoriesAsListQB($store)
					: $repository->createQueryBuilder('category')->andWhere('1 = 0'),
				'choice_label' => 'nameWithParent',
				'required' => false,
				'placeholder' => 'Select category',
				'attr' => ['class' => 'select2'],
			])
			->add('includeDescendants', CheckboxType::class, [
				'required' => false,
			]);

		$this->addProductField($builder, $options, $target?->getProduct());

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
			'choices' => $selectedProduct instanceof Product ? [$selectedProduct] : [],
			'choice_label' => static fn (Product $product): string => sprintf('%s (%s)', $product->getName(), $product->getCode()),
			'required' => false,
			'placeholder' => 'Select product',
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
			'data_class' => ProductDiscountTarget::class,
			'store' => null,
			'product_ajax_url' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('product_ajax_url', ['null', 'string']);
	}
}
