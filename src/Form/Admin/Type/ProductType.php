<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Product;
use App\Entity\ProductParameter;
use App\Entity\Unit;
use App\Enum\ProductKindEnum;
use App\Repository\CategoryRepository;
use App\Repository\UnitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
	public function __construct(private readonly EntityManagerInterface $em)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
		/** @var Product $product */
		$product = $builder->getData();

        $builder
            ->add('name')
            ->add('code')
	        ->add('unit', EntityType::class, [
		        'query_builder' => fn(UnitRepository $repository)
		            => $repository->findAvailableByStoreQB($product->getStore()),
		        'class' => Unit::class,
		        'choice_label' => 'name',
		        'multiple' => false,
		        'required' => true,
		        'attr' => ['class' => 'select2'],
	        ])
	        ->add('baseSalePrice')
	        ->add('canBeSold')
	        ->add('canBePurchased')
	        ->add('canBeManufactured')
	        ->add('productKind', EnumType::class, [
		        'class' => ProductKindEnum::class,
		        'choice_label' => fn(ProductKindEnum $choice) => $choice->value,
	        ])
	        ->add('category', EntityType::class, [
		        'query_builder' => fn(CategoryRepository $repository)
		            => $repository->findAvailableCategoriesAsListQB($product->getStore()),
		        'class' => Category::class,
		        'choice_label' => 'nameWithParent',
		        'multiple' => false,
		        'required' => true,
		        'attr' => ['class' => 'select2'],
	        ])
	        ->add('productRelations', CollectionType::class, [
		        'entry_type' => ProductRelationType::class,
		        'entry_options' => [
			        'label' => false,
			        'store' => $product->getStore(),
			        'exclude_product' => $product,
		        ],
		        'allow_add' => true,
		        'allow_delete' => true,
		        'by_reference' => false,
		        'label' => 'admin.product.relations.title',
	        ])
        ;

		$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
			/** @var Product|null $product */
			$product = $event->getData();
			if (!$product instanceof Product) return;

			$this->addProductParametersField(
				$event->getForm(),
				$product,
				$product->getCategory()
			);
		});

		$builder->get('category')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
			$category = $event->getForm()->getData();
			if (!$category instanceof Category) return;

			$parent = $event->getForm()->getParent();
			$product = $parent?->getData();
			if (!$product instanceof Product) return;

			$this->addProductParametersField(
				$parent,
				$product,
				$category
			);
		});
    }

	private function addProductParametersField(?FormInterface $form, Product $product, ?Category $category): void
	{
		if (!$form || !$category) return;

		$form->add('productParameters', CollectionType::class, [
			'entry_type' => ProductParameterType::class,
			'data' => $this->buildProductParameters($product, $category),
			'entry_options' => ['label' => false],
			'mapped' => true,
			'by_reference' => false,
			'allow_delete' => true,
		]);
	}

	private function buildProductParameters(Product $product, Category $category): Collection
	{
		$productParameters = new ArrayCollection();
		foreach ($product->getProductParameters() as $productParameter) {
			$productParameters->set($productParameter->getProductParameterName()->getName(), $productParameter);
		}

		$categoryProductParameterNames = $this->em->getRepository(CategoryProductParameterName::class)
			->findAllByCategory($category);

		foreach ($categoryProductParameterNames as $categoryProductParameterName) {
			$productParameterName = $categoryProductParameterName->getProductParameterName();
			if ($productParameters->get($productParameterName->getName())) continue;

			$productParameter = new ProductParameter();
			$productParameter->setProductParameterName($productParameterName)
				->setProduct($product);

			$productParameters->set($productParameterName->getName(), $productParameter);
		}

		return $productParameters;
	}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
