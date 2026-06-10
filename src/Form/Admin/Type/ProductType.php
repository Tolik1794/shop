<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Product;
use App\Entity\ProductParameter;
use App\Entity\ProductParameterName;
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
use Symfony\Component\Form\FormError;
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

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
		    $data = $event->getData();

		    if (!is_array($data) || !isset($data['productParameters']) || !is_array($data['productParameters'])) {
			    return;
		    }

		    $requiredIds = [];
		    $categoryId = isset($data['category']) ? (int) $data['category'] : null;
		    if ($categoryId) {
			    $category = $this->em->find(Category::class, $categoryId);
			    if ($category instanceof Category) {
				    $requiredIds = array_keys($this->buildRequiredParameterNameIds($category));
			    }
		    }

		    foreach ($data['productParameters'] as $key => $parameterData) {
			    if (!is_array($parameterData)) {
				    unset($data['productParameters'][$key]);
				    continue;
			    }

			    $parameterName = trim((string) ($parameterData['productParameterName'] ?? ''));
			    $value = trim((string) ($parameterData['value'] ?? ''));

			    if ($parameterName === '') {
				    unset($data['productParameters'][$key]);
				    continue;
			    }

			    if ($value === '' && !in_array((int) $parameterName, $requiredIds, true)) {
				    unset($data['productParameters'][$key]);
			    }
		    }

		    $event->setData($data);
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

	    $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
		    $form = $event->getForm();
		    /** @var Product|null $product */
		    $product = $event->getData();

		    if (!$product instanceof Product || !$form->has('productParameters')) {
			    return;
		    }

		    $category = $product->getCategory();
		    $allowedParameterNameIds = $category instanceof Category
			    ? array_fill_keys(array_map(
				    static fn (ProductParameterName $parameterName): int => (int) $parameterName->getId(),
				    $this->findAllowedParameterNames($category)
			    ), true)
			    : [];
		    $usedParameterNameIds = [];

		    foreach ($form->get('productParameters') as $parameterForm) {
			    /** @var ProductParameter|null $parameter */
			    $parameter = $parameterForm->getData();
			    $parameterName = $parameter instanceof ProductParameter ? $parameter->getProductParameterName() : null;

			    if (!$parameterName instanceof ProductParameterName || !$parameterName->getId()) {
				    continue;
			    }

			    $parameterNameId = (int) $parameterName->getId();
			    if (!isset($allowedParameterNameIds[$parameterNameId])) {
				    $parameterForm->get('productParameterName')->addError(new FormError('This product parameter is not available for the selected category.'));
			    }

			    if (isset($usedParameterNameIds[$parameterNameId])) {
				    $parameterForm->get('productParameterName')->addError(new FormError('This product parameter is already added.'));
				    continue;
			    }

			    $usedParameterNameIds[$parameterNameId] = true;
		    }

		    $requiredParameterNameIds = $category instanceof Category
			    ? $this->buildRequiredParameterNameIds($category)
			    : [];

		    foreach ($form->get('productParameters') as $parameterForm) {
			    /** @var ProductParameter|null $parameter */
			    $parameter = $parameterForm->getData();
			    $parameterName = $parameter instanceof ProductParameter
				    ? $parameter->getProductParameterName()
				    : null;
			    if (!$parameterName instanceof ProductParameterName || !$parameterName->getId()) {
				    continue;
			    }
			    $parameterNameId = (int) $parameterName->getId();
			    if (isset($requiredParameterNameIds[$parameterNameId])
				    && trim((string) ($parameter->getValue() ?? '')) === ''
			    ) {
				    $parameterForm->get('value')->addError(new FormError('This field is required.'));
			    }
		    }
	    });
    }

	private function addProductParametersField(?FormInterface $form, Product $product, ?Category $category): void
	{
		if (!$form) return;

		$form->add('productParameters', CollectionType::class, [
			'entry_type' => ProductParameterType::class,
			'data' => $this->buildProductParameters($product, $category),
			'entry_options' => [
				'label' => false,
				'parameter_name_choices' => $this->buildParameterNameChoices($product, $category),
			],
			'mapped' => true,
			'by_reference' => false,
			'allow_add' => true,
			'allow_delete' => true,
			'prototype' => true,
			'prototype_options' => [
				'label' => false,
				'parameter_name_choices' => $category instanceof Category ? $this->findAllowedParameterNames($category) : [],
			],
			'label' => 'admin.product.parameters.title',
		]);
	}

	private function buildProductParameters(Product $product, ?Category $category): Collection
	{
		$productParameters = new ArrayCollection();
		foreach ($product->getProductParameters() as $productParameter) {
			$productParameterName = $productParameter->getProductParameterName();
			if (!$productParameterName instanceof ProductParameterName) {
				continue;
			}

			$productParameters->set((string) $productParameterName->getId(), $productParameter);
		}

		if (!$category instanceof Category) {
			return $productParameters;
		}

		foreach ($this->findAllowedParameterNames($category) as $productParameterName) {
			if ($productParameters->get((string) $productParameterName->getId())) continue;
			$productParameter = new ProductParameter();
			$productParameter->setProductParameterName($productParameterName)
				->setProduct($product);

			$productParameters->set((string) $productParameterName->getId(), $productParameter);
		}

		return $productParameters;
	}

	/**
	 * @return ProductParameterName[]
	 */
	private function buildParameterNameChoices(Product $product, ?Category $category): array
	{
		$choices = [];

		if ($category instanceof Category) {
			foreach ($this->findAllowedParameterNames($category) as $parameterName) {
				if ($parameterName->getId()) {
					$choices[$parameterName->getId()] = $parameterName;
				}
			}
		}

		foreach ($product->getProductParameters() as $productParameter) {
			$parameterName = $productParameter->getProductParameterName();
			if ($parameterName instanceof ProductParameterName && $parameterName->getId()) {
				$choices[$parameterName->getId()] = $parameterName;
			}
		}

		return array_values($choices);
	}

	/**
	 * @return ProductParameterName[]
	 */
	private function findAllowedParameterNames(Category $category): array
	{
		$parameterNames = [];
		$categoryProductParameterNames = $this->em->getRepository(CategoryProductParameterName::class)
			->findAllByCategory($category);

		foreach ($categoryProductParameterNames as $categoryProductParameterName) {
			$productParameterName = $categoryProductParameterName->getProductParameterName();
			if ($productParameterName instanceof ProductParameterName && $productParameterName->getId()) {
				$parameterNames[$productParameterName->getId()] = $productParameterName;
			}
		}

		uasort(
			$parameterNames,
			static fn (ProductParameterName $left, ProductParameterName $right): int => strcmp($left->getName(), $right->getName())
		);

		return array_values($parameterNames);
	}

	/**
	 * @return array<int, string> parameterNameId => parameterName for required parameters
	 */
	private function buildRequiredParameterNameIds(Category $category): array
	{
		$required = [];
		$records = $this->em->getRepository(CategoryProductParameterName::class)
			->findAllByCategory($category);

		foreach ($records as $record) {
			if (!$record->isIsRequired()) {
				continue;
			}
			$pn = $record->getProductParameterName();
			if ($pn instanceof ProductParameterName && $pn->getId()) {
				$required[(int) $pn->getId()] = $pn->getName();
			}
		}

		return $required;
	}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
