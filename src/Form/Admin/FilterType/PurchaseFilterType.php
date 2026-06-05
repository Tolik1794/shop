<?php

namespace App\Form\Admin\FilterType;

use App\Entity\PurchaseStatus;
use App\Enum\PaymentStatusEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PurchaseFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('number', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					$value = $this->normalizeSearchValue($value);
					if ($value !== '') {
						$rootAlias = current($qb->getRootAliases());
						$tokens = preg_split('/\s+/', mb_strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

						foreach ($tokens as $index => $token) {
							$parameterName = 'purchaseSearch' . $index;

							$qb->andWhere(sprintf(
								'(LOWER(%s.number) LIKE :%s OR LOWER(%s.supplierNameSnapshot) LIKE :%s)',
								$rootAlias,
								$parameterName,
								$rootAlias,
								$parameterName,
							))->setParameter($parameterName, '%' . $token . '%');
						}
					}
				},
			])
			->add('supplier', SearchType::class, [
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.supplierNameSnapshot like :purchaseSupplier', $rootAlias))
							->setParameter('purchaseSupplier', '%' . $value . '%');
					}
				},
			])
			->add('status', EnumType::class, [
				'class' => PurchaseStatus::class,
				'choice_label' => fn (PurchaseStatus $choice) => $choice->value,
				'required' => false,
				'mapped' => false,
				'query_callback' => function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof PurchaseStatus) {
						$rootAlias = current($qb->getRootAliases());
						$qb->andWhere(sprintf('%s.status = :purchaseStatus', $rootAlias))
							->setParameter('purchaseStatus', $value);
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
							->andWhere(sprintf('%s.status = :purchaseQuickDraftStatus', $rootAlias))
							->setParameter('purchaseQuickDraftStatus', PurchaseStatus::DRAFT),
						'unpaid' => $qb
							->andWhere(sprintf('%s.paymentStatus IN (:purchaseQuickUnpaidStatuses)', $rootAlias))
							->setParameter('purchaseQuickUnpaidStatuses', [PaymentStatusEnum::UNPAID, PaymentStatusEnum::PARTIALLY_PAID]),
						'paid' => $qb
							->andWhere(sprintf('%s.paymentStatus IN (:purchaseQuickPaidStatuses)', $rootAlias))
							->setParameter('purchaseQuickPaidStatuses', [PaymentStatusEnum::PAID, PaymentStatusEnum::OVERPAID]),
						'canceled' => $qb
							->andWhere(sprintf('%s.status = :purchaseQuickCanceledStatus', $rootAlias))
							->setParameter('purchaseQuickCanceledStatus', PurchaseStatus::CANCELED),
						'returned' => $qb
							->andWhere(sprintf('%s.status IN (:purchaseQuickReturnedStatuses)', $rootAlias))
							->setParameter('purchaseQuickReturnedStatuses', [PurchaseStatus::RETURNED, PurchaseStatus::PARTIALLY_RETURNED]),
						'completed' => $qb
							->andWhere(sprintf('%s.status = :purchaseQuickCompletedStatus', $rootAlias))
							->setParameter('purchaseQuickCompletedStatus', PurchaseStatus::COMPLETED),
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
