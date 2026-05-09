<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\ExchangeRate;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExchangeRateType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('fromCurrency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'required' => true,
				'attr' => ['class' => 'select2'],
			])
			->add('toCurrency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'required' => true,
				'attr' => ['class' => 'select2'],
			])
			->add('rate')
			->add('validFrom', DateTimeType::class, [
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('source')
			->add('comment', TextareaType::class, [
				'required' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ExchangeRate::class,
		]);
	}
}
