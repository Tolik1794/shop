<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\IncomeRecord;
use App\Entity\LegalEntity;
use App\Entity\Store;
use App\Enum\IncomeClassificationEnum;
use App\Repository\LegalEntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IncomeRecordType extends AbstractType
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
				'label' => 'admin.tax_income.fields.legal_entity',
				'attr' => ['class' => 'select2'],
			])
			->add('store', EntityType::class, [
				'class' => Store::class,
				'choice_label' => 'name',
				'required' => false,
				'placeholder' => 'admin.tax_income.fields.store_placeholder',
				'label' => 'admin.tax_income.fields.store',
				'attr' => ['class' => 'select2'],
			])
			->add('recognizedAt', DateType::class, [
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
				'label' => 'admin.tax_income.fields.recognized_at',
			])
			->add('amount', TextType::class, [
				'label' => 'admin.tax_income.fields.amount',
			])
			->add('currency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'label' => 'admin.tax_income.fields.currency',
			])
			->add('classification', EnumType::class, [
				'class' => IncomeClassificationEnum::class,
				'choice_label' => static fn (IncomeClassificationEnum $choice): string => 'admin.tax_income.classification.' . $choice->value,
				'label' => 'admin.tax_income.fields.classification',
			])
			->add('counterparty', TextType::class, [
				'required' => false,
				'label' => 'admin.tax_income.fields.counterparty',
			])
			->add('paymentPurpose', TextareaType::class, [
				'required' => false,
				'label' => 'admin.tax_income.fields.payment_purpose',
			])
			->add('comment', TextareaType::class, [
				'required' => false,
				'label' => 'admin.tax_income.fields.comment',
			])
		;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => IncomeRecord::class,
		]);
	}
}
