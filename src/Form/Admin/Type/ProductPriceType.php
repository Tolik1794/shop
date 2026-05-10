<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\ProductPrice;
use App\Enum\ProductPriceTypeEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductPriceType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('type', EnumType::class, [
				'class' => ProductPriceTypeEnum::class,
				'choice_label' => fn(ProductPriceTypeEnum $choice) => $choice->value,
			])
			->add('currency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'required' => true,
				'attr' => ['class' => 'select2'],
			])
			->add('price')
			->add('validFrom', DateTimeType::class, [
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ProductPrice::class,
		]);
	}
}
