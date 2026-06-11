<?php

namespace App\Form\Admin\Type;

use App\Enum\IncomeClassificationEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class IncomeReclassifyType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('classification', EnumType::class, [
				'class' => IncomeClassificationEnum::class,
				'choice_label' => static fn (IncomeClassificationEnum $choice): string => 'admin.tax_income.classification.' . $choice->value,
				'label' => 'admin.tax_income.fields.classification',
			])
			->add('comment', TextareaType::class, [
				'required' => false,
				'label' => 'admin.tax_income.fields.comment',
			])
		;
	}
}
