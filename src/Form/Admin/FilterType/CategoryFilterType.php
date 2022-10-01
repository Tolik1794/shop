<?php

namespace App\Form\Admin\FilterType;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
        ;

		$builder->setMethod('GET');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
	        'csrf_protection' => false
        ]);
    }
}
