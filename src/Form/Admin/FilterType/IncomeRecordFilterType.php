<?php

namespace App\Form\Admin\FilterType;

use App\Entity\LegalEntity;
use App\Entity\Store;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Repository\LegalEntityRepository;
use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IncomeRecordFilterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('legalEntity', EntityType::class, [
				'class' => LegalEntity::class,
				'query_builder' => static fn (LegalEntityRepository $repository)
					=> $repository->createQueryBuilder('legal_entity')
						->andWhere('legal_entity.deletedAt IS NULL')
						->orderBy('legal_entity.name', 'ASC'),
				'choice_label' => static fn (LegalEntity $legalEntity): string => (string) $legalEntity,
				'required' => false,
				'mapped' => false,
				'label' => 'admin.tax_income.filters.legal_entity',
				'query_callback' => static function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof LegalEntity) {
						$qb->andWhere('income_record.legalEntity = :filterLegalEntity')
							->setParameter('filterLegalEntity', $value);
					}
				},
			])
			->add('dateFrom', DateType::class, [
				'widget' => 'single_text',
				'required' => false,
				'mapped' => false,
				'label' => 'admin.tax_income.filters.date_from',
				'query_callback' => static function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof DateTimeInterface) {
						$qb->andWhere('income_record.recognizedAt >= :filterDateFrom')
							->setParameter('filterDateFrom', $value->format('Y-m-d'));
					}
				},
			])
			->add('dateTo', DateType::class, [
				'widget' => 'single_text',
				'required' => false,
				'mapped' => false,
				'label' => 'admin.tax_income.filters.date_to',
				'query_callback' => static function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof DateTimeInterface) {
						$qb->andWhere('income_record.recognizedAt <= :filterDateTo')
							->setParameter('filterDateTo', $value->format('Y-m-d'));
					}
				},
			])
			->add('classification', EnumType::class, [
				'class' => IncomeClassificationEnum::class,
				'choice_label' => static fn (IncomeClassificationEnum $choice): string => 'admin.tax_income.classification.' . $choice->value,
				'required' => false,
				'mapped' => false,
				'label' => 'admin.tax_income.filters.classification',
				'query_callback' => static function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof IncomeClassificationEnum) {
						$qb->andWhere('income_record.classification = :filterClassification')
							->setParameter('filterClassification', $value);
					}
				},
			])
			->add('sourceType', EnumType::class, [
				'class' => IncomeSourceTypeEnum::class,
				'choice_label' => static fn (IncomeSourceTypeEnum $choice): string => 'admin.tax_income.source.' . $choice->value,
				'required' => false,
				'mapped' => false,
				'label' => 'admin.tax_income.filters.source',
				'query_callback' => static function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof IncomeSourceTypeEnum) {
						$qb->andWhere('income_record.sourceType = :filterSourceType')
							->setParameter('filterSourceType', $value);
					}
				},
			])
			->add('store', EntityType::class, [
				'class' => Store::class,
				'choice_label' => 'name',
				'required' => false,
				'mapped' => false,
				'label' => 'admin.tax_income.filters.store',
				'query_callback' => static function (QueryBuilder $qb, mixed $value): void {
					if ($value instanceof Store) {
						$qb->andWhere('income_record.store = :filterStore')
							->setParameter('filterStore', $value);
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
