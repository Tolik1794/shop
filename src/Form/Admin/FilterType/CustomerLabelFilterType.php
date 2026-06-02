<?php

namespace App\Form\Admin\FilterType;

use App\Enum\ActiveStatusEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerLabelFilterType extends AbstractType
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

						$qb->andWhere(sprintf('%s.name like :customerLabelName OR %s.code like :customerLabelName', $rootAlias, $rootAlias))
							->setParameter('customerLabelName', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choices' => ActiveStatusEnum::userSelectableCases(),
				'choice_label' => fn(ActiveStatusEnum $choice): string => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof ActiveStatusEnum) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.status = :customerLabelStatus', $rootAlias))
							->setParameter('customerLabelStatus', $value);
					}
				},
			])
			->setMethod('GET');
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'csrf_protection' => false,
		]);
	}
}
