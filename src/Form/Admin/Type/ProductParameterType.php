<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductParameter;
use App\Entity\ProductParameterName;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductParameterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', null, [
				'label' => $builder->getName(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductParameter::class,
        ]);
    }
}
