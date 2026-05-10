<?php

namespace App\Form\Admin\Type;

use App\Entity\Customer;
use App\Enum\ActiveStatusEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name')
			->add('phone')
			->add('email')
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choice_label' => fn(ActiveStatusEnum $choice) => $choice->value,
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => Customer::class,
		]);
	}
}
