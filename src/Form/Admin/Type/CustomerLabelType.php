<?php

namespace App\Form\Admin\Type;

use App\Entity\CustomerLabel;
use App\Enum\ActiveStatusEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerLabelType extends AbstractType
{
	/**
	 * Fixed palette of bootstrap badge classes, scoped to avoid arbitrary CSS.
	 */
	private const array COLOR_CHOICES = [
		'admin.customer_label.color.secondary' => 'bg-secondary',
		'admin.customer_label.color.primary' => 'bg-primary',
		'admin.customer_label.color.success' => 'bg-success',
		'admin.customer_label.color.info' => 'bg-info text-dark',
		'admin.customer_label.color.warning' => 'bg-warning text-dark',
		'admin.customer_label.color.danger' => 'bg-danger',
		'admin.customer_label.color.dark' => 'bg-dark',
	];

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('code')
			->add('name')
			->add('color', ChoiceType::class, [
				'required' => false,
				'placeholder' => 'admin.customer_label.color.default',
				'choices' => self::COLOR_CHOICES,
			])
			->add('sortOrder', IntegerType::class, [
				'attr' => [
					'min' => 0,
				],
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choices' => ActiveStatusEnum::userSelectableCases(),
				'choice_label' => fn(ActiveStatusEnum $choice): string => $choice->value,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => CustomerLabel::class,
		]);
	}
}
