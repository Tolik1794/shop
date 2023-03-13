<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\CategoryProductParameterName;
use App\Entity\ProductParameterName;
use App\Repository\CategoryRepository;
use App\Repository\ProductParameterNameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryProductParameterNameType extends AbstractType
{
	public function __construct(private readonly EntityManagerInterface $em)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
		/** @var CategoryProductParameterName $object */
		$object = $builder->getData();
        $builder
            ->add('isRequired')
            ->add('isFilter')
            ->add('productParameterName', EntityType::class, [
				'class' => ProductParameterName::class,
	            'query_builder' => function (ProductParameterNameRepository $repository) use ($object) {
		            $productParameterName = $this->em->getRepository(ProductParameterName::class)
			            ->findUsedByCategory($object->getCategory());

		            $qb = $repository->createQueryBuilder('productParameterName');

					if (empty($productParameterName)) return $qb;

		            $qb->andWhere('productParameterName not in (:product_parameter_name)')
			            ->setParameter(
				            'product_parameter_name',
				            $productParameterName
			            );

					return $qb;
	            }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CategoryProductParameterName::class,
        ]);
    }
}
