<?php

namespace App\Form\Admin\FilterType;

use App\Enum\ActiveStatusEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerFilterType extends AbstractType
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

						$qb->andWhere(sprintf('%s.name like :customerName', $rootAlias))
							->setParameter('customerName', '%' . $value . '%');
					}
				},
			])
			->add('phone', SearchType::class, [
				'label' => 'Phone',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.phone like :customerPhone', $rootAlias))
							->setParameter('customerPhone', '%' . $value . '%');
					}
				},
			])
			->add('email', SearchType::class, [
				'label' => 'Email',
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.email like :customerEmail', $rootAlias))
							->setParameter('customerEmail', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choice_label' => fn(ActiveStatusEnum $choice) => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof ActiveStatusEnum) {
						$rootAlias = current($qb->getRootAliases());

						$qb->andWhere(sprintf('%s.status = :customerStatus', $rootAlias))
							->setParameter('customerStatus', $value);
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
