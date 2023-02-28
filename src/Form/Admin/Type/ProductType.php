<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
		$product = $builder->getData();
        $builder
            ->add('name')
            ->add('code')
	        ->add('category', EntityType::class, [
		        'query_builder' => fn(CategoryRepository $repository)
		            => $repository->findAvailableCategoriesQB($product->getStore()),
		        'class' => Category::class,
		        'choice_label' => 'nameWithParent',
		        'multiple' => false,
		        'required' => true,
		        'attr' => ['class' => 'select2'],
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
