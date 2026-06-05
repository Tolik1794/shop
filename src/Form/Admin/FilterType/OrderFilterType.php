<?php

namespace App\Form\Admin\FilterType;

use App\Entity\OrderStatus;
use App\Enum\PaymentStatusEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('number', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					$value = $this->normalizeSearchValue($value);
					if ($value !== '') {
						$rootAlias = current($qb->getRootAliases());
						$tokens = preg_split('/\s+/', mb_strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

						foreach ($tokens as $index => $token) {
							$parameterName = 'orderNumber' . $index;

							$qb->andWhere(sprintf('LOWER(%s.number) LIKE :%s', $rootAlias, $parameterName))
								->setParameter($parameterName, '%' . $token . '%');
						}
					}
				},
			])
			->add('customer', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.customerNameSnapshot like :orderCustomer', $rootAlias))
							->setParameter('orderCustomer', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => OrderStatus::class,
				'choice_label' => fn(OrderStatus $choice) => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value) {
					if ($value instanceof OrderStatus) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.status = :orderStatus', $rootAlias))
							->setParameter('orderStatus', $value);
					}
				},
			])
			->add('quick', HiddenType::class, [
				'label' => false,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if (!is_string($value) || $value === '') {
						return;
					}

					$rootAlias = current($qb->getRootAliases());

					match ($value) {
						'draft' => $qb
							->andWhere(sprintf('%s.status = :orderQuickDraftStatus', $rootAlias))
							->setParameter('orderQuickDraftStatus', OrderStatus::DRAFT),
						'unpaid' => $qb
							->andWhere(sprintf('%s.paymentStatus IN (:orderQuickUnpaidStatuses)', $rootAlias))
							->setParameter('orderQuickUnpaidStatuses', [PaymentStatusEnum::UNPAID, PaymentStatusEnum::PARTIALLY_PAID]),
						'paid' => $qb
							->andWhere(sprintf('%s.paymentStatus IN (:orderQuickPaidStatuses)', $rootAlias))
							->setParameter('orderQuickPaidStatuses', [PaymentStatusEnum::PAID, PaymentStatusEnum::OVERPAID]),
						'canceled' => $qb
							->andWhere(sprintf('%s.status = :orderQuickCanceledStatus', $rootAlias))
							->setParameter('orderQuickCanceledStatus', OrderStatus::CANCELED),
						default => null,
					};
				},
			])
			->setMethod('GET');
	}

	private function normalizeSearchValue(mixed $value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		return preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'csrf_protection' => false,
		]);
	}
}
