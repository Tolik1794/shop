<?php

namespace App\Form\Admin\FilterType;

use App\Entity\WarehouseStock;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WarehouseStockFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
	        ->add('product', SearchType::class, [
		        'label' => 'Product',
		        'required' => false,
		        'mapped' => false,
		        'query_callback' => function (QueryBuilder $qb, mixed $value) {
			        if ($value) {
				        $qb->andWhere('product.name like :productSearch OR product.code like :productSearch')
					        ->setParameter('productSearch', '%' . $value . '%');
			        }
		        },
	        ])
	        ->setMethod('GET')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WarehouseStock::class,
	        'csrf_protection' => false,
        ]);
    }
}
