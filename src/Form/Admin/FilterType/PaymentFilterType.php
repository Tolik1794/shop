<?php

namespace App\Form\Admin\FilterType;

use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentTypeEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('direction', EnumType::class, [
				'class' => PaymentDirectionEnum::class,
				'choice_label' => fn (PaymentDirectionEnum $choice): string => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof PaymentDirectionEnum) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.direction = :paymentDirection', $rootAlias))
							->setParameter('paymentDirection', $value);
					}
				},
			])
			->add('type', EnumType::class, [
				'class' => PaymentTypeEnum::class,
				'choice_label' => fn (PaymentTypeEnum $choice): string => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof PaymentTypeEnum) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.type = :paymentType', $rootAlias))
							->setParameter('paymentType', $value);
					}
				},
			])
			->add('correctionState', ChoiceType::class, [
				'label' => 'Correction',
				'required' => false,
				'mapped' => false,
				'choices' => [
					'Normal' => 'normal',
					'Reversal' => 'reversal',
					'Reversed' => 'reversed',
				],
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					$rootAlias = current($qb->getRootAliases());

					if ($value === 'normal') {
						$qb->andWhere(sprintf('%s.reversesPayment IS NULL', $rootAlias))
							->andWhere('reversedByPayment.id IS NULL');
					}

					if ($value === 'reversal') {
						$qb->andWhere(sprintf('%s.reversesPayment IS NOT NULL', $rootAlias));
					}

					if ($value === 'reversed') {
						$qb->andWhere('reversedByPayment.id IS NOT NULL');
					}
				},
			])
			->add('externalReference', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.externalReference like :paymentExternalReference', $rootAlias))
							->setParameter('paymentExternalReference', '%' . $value . '%');
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
