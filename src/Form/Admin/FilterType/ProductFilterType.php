<?php

namespace App\Form\Admin\FilterType;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductKindEnum;
use App\Repository\UnitRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
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
	        ->add('unit', EntityType::class, [
		        'class' => Unit::class,
		        'query_builder' => fn(UnitRepository $repository)
		            => $options['store'] instanceof Store
			            ? $repository->findAvailableByStoreQB($options['store'])
			            : $repository->createQueryBuilder('unit'),
		        'choice_label' => 'name',
		        'label' => 'Unit',
		        'required' => false,
		        'mapped' => false,
		        'query_callback' => function (QueryBuilder $qb, mixed $value) {
			        if ($value instanceof Unit) {
				        $rootAlias = current($qb->getRootAliases());

				        $qb->andWhere(sprintf('%s.unit = :unit', $rootAlias))
					        ->setParameter('unit', $value);
			        }
		        },
	        ])
	        ->add('productKind', EnumType::class, [
		        'class' => ProductKindEnum::class,
		        'choice_label' => fn(ProductKindEnum $choice) => $choice->value,
		        'label' => 'Product kind',
		        'required' => false,
		        'mapped' => false,
		        'query_callback' => function (QueryBuilder $qb, mixed $value) {
			        if ($value instanceof ProductKindEnum) {
				        $rootAlias = current($qb->getRootAliases());

				        $qb->andWhere(sprintf('%s.productKind = :productKind', $rootAlias))
					        ->setParameter('productKind', $value);
			        }
		        },
	        ])
	        ->add('canBeSold', ChoiceType::class, [
		        'label' => 'Can be sold',
		        'required' => false,
		        'mapped' => false,
		        'choices' => [
			        'Yes' => true,
			        'No' => false,
		        ],
		        'query_callback' => function (QueryBuilder $qb, mixed $value) {
			        if ($value !== null && $value !== '') {
				        $rootAlias = current($qb->getRootAliases());

				        $qb->andWhere(sprintf('%s.canBeSold = :canBeSold', $rootAlias))
					        ->setParameter('canBeSold', $value);
			        }
		        },
	        ])
	        ->add('canBePurchased', ChoiceType::class, [
		        'label' => 'Can be purchased',
		        'required' => false,
		        'mapped' => false,
		        'choices' => [
			        'Yes' => true,
			        'No' => false,
		        ],
		        'query_callback' => function (QueryBuilder $qb, mixed $value) {
			        if ($value !== null && $value !== '') {
				        $rootAlias = current($qb->getRootAliases());

				        $qb->andWhere(sprintf('%s.canBePurchased = :canBePurchased', $rootAlias))
					        ->setParameter('canBePurchased', $value);
			        }
		        },
	        ])
	        ->add('canBeManufactured', ChoiceType::class, [
		        'label' => 'Can be manufactured',
		        'required' => false,
		        'mapped' => false,
		        'choices' => [
			        'Yes' => true,
			        'No' => false,
		        ],
		        'query_callback' => function (QueryBuilder $qb, mixed $value) {
			        if ($value !== null && $value !== '') {
				        $rootAlias = current($qb->getRootAliases());

				        $qb->andWhere(sprintf('%s.canBeManufactured = :canBeManufactured', $rootAlias))
					        ->setParameter('canBeManufactured', $value);
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
	        ->setMethod('GET')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
	        'csrf_protection' => false,
	        'store' => null,
        ]);

	    $resolver->setAllowedTypes('store', ['null', Store::class]);
    }
}
