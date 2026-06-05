<?php

namespace App\Form\Admin\Type;

use App\Entity\ProductParameter;
use App\Entity\ProductParameterName;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductParameterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
	        ->add('productParameterName', EntityType::class, [
		        'class' => ProductParameterName::class,
		        'choices' => $options['parameter_name_choices'],
		        'choice_label' => 'name',
		        'placeholder' => 'admin.product.parameters.select_parameter',
		        'required' => false,
		        'attr' => [
			        'class' => 'select2',
			        'data-product-parameters-target' => 'parameterName',
			        'data-action' => 'change->product-parameters#parameterChanged',
		        ],
	        ])
            ->add('value', null, [
				'label' => 'admin.product.parameters.value',
	            'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductParameter::class,
	        'parameter_name_choices' => [],
        ]);
	    $resolver->setAllowedTypes('parameter_name_choices', ['array']);
    }
}
