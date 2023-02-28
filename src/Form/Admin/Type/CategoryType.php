<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
	public function __construct()
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
		/** @var Category $category */
		$category = $builder->getData();
        $builder
            ->add('name')
	        ->add('parent', EntityType::class, [
		        'query_builder' => fn(CategoryRepository $repository)
		            => $repository->findAvailableCategoriesQB($category->getStore(), maxLevel: 1),
		        'class' => Category::class,
		        'choice_label' => 'nameWithParent',
		        'multiple' => false,
		        'required' => false,
		        'attr' => ['class' => 'select2'],
	        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
