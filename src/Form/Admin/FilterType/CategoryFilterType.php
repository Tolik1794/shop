<?php

namespace App\Form\Admin\FilterType;

use App\Entity\Category;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryFilterType extends AbstractType
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

                        $qb->andWhere(sprintf('%s.name like :categoryName', $rootAlias))
                            ->setParameter('categoryName', '%' . $value . '%');
                    }
                },
            ])
            ->setMethod('GET');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
	        'csrf_protection' => false
        ]);
    }
}
