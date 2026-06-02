<?php

namespace App\Form\Admin\FilterType;

use App\Entity\CustomerLabel;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Repository\CustomerLabelRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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

						$qb->andWhere(sprintf('%s.name like :customerName OR %s.lastName like :customerName', $rootAlias, $rootAlias))
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
				'choices' => ActiveStatusEnum::userSelectableCases(),
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
			->add('labels', EntityType::class, [
				'class' => CustomerLabel::class,
				'choice_label' => 'name',
				'multiple' => true,
				'required' => false,
				'label' => 'admin.customer.fields.labels',
				'attr' => ['class' => 'select2'],
				'mapped' => false,
				'query_builder' => static fn(CustomerLabelRepository $repository) => $repository->activeByStoreQB($options['store']),
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof Collection) {
						$value = $value->toArray();
					}

					if (is_array($value) && $value !== []) {
						$rootAlias = current($qb->getRootAliases());

						$qb->distinct()
							->join(sprintf('%s.labels', $rootAlias), 'filterLabel')
							->andWhere('filterLabel IN (:filterLabels)')
							->setParameter('filterLabels', $value);
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

		$resolver->setRequired('store');
		$resolver->setAllowedTypes('store', Store::class);
	}
}
