<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\Product;
use App\Entity\ProductParameter;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
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
		$category = $product->getCategory();

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

        $builder
            ->add('name')
            ->add('code')
	        ->add('category', EntityType::class, [
		        'query_builder' => fn(CategoryRepository $repository)
		            => $repository->findAvailableCategoriesAsListQB($product->getStore()),
		        'class' => Category::class,
		        'choice_label' => 'nameWithParent',
		        'multiple' => false,
		        'required' => true,
		        'attr' => ['class' => 'select2'],
	        ])
	        ->add('productParameters', CollectionType::class, [
				'entry_type' => ProductParameterType::class,
		        'data' => $productParameters,
		        'entry_options' => ['label' => false],
		        'mapped' => true,
		        'allow_delete' => true
	        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
