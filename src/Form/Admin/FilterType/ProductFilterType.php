<?php

namespace App\Form\Admin\FilterType;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Store;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', SearchType::class, [
				'label' => 'Name',
	            'required' => false,
	            'mapped' => false,
	            'query_callback' => function (QueryBuilder $qb, mixed $value) {
		            if ($value) {
			            $rootAlias = current($qb->getRootAliases());

			            $qb->andWhere(sprintf('%s.name like :name', $rootAlias))
				            ->setParameter('name', '%' . $value . '%');
		            }
	            },
            ])
            ->add('code', SearchType::class, [
				'label' => 'Code',
	            'required' => false,
	            'mapped' => false,
	            'query_callback' => function (QueryBuilder $qb, mixed $value) {
		            if ($value) {
			            $rootAlias = current($qb->getRootAliases());

			            $qb->andWhere(sprintf('%s.code like :code', $rootAlias))
				            ->setParameter('code', '%' . $value . '%');
		            }
	            },
            ])
            ->add('category', null, [
				'label' => 'Category',
	            'required' => false,
	            'mapped' => false,
	            'query_callback' => function (QueryBuilder $qb, mixed $value) {
		            if ($value instanceof Category) {
			            $rootAlias = current($qb->getRootAliases());

			            $qb->andWhere(sprintf('%s.category = :category', $rootAlias))
				            ->setParameter('category', $value);
		            }
	            },
            ])
            ->add('store', null, [
				'label' => 'Store',
	            'required' => false,
	            'mapped' => false,
	            'query_callback' => function (QueryBuilder $qb, mixed $value) {
		            if ($value instanceof Store) {
			            $rootAlias = current($qb->getRootAliases());

			            $qb->andWhere(sprintf('%s.store = :filterStore', $rootAlias))
				            ->setParameter('filterStore', $value);
		            }
	            },
            ])
	        ->setMethod('GET')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
	        'csrf_protection' => false,
        ]);
    }
}
