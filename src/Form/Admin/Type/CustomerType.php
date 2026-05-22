<?php

namespace App\Form\Admin\Type;

use App\Entity\Customer;
use App\Enum\ActiveStatusEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CustomerType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', null, [
				'label' => 'First name',
			])
			->add('lastName', null, [
				'label' => 'Last name',
				'required' => true,
			])
			->add('phone', TelType::class, [
				'required' => true,
				'attr' => [
					'inputmode' => 'tel',
					'placeholder' => '+380XXXXXXXXX',
					'pattern' => '(\\+?380|0)[\\s\\-\\(\\)]*\\d{2}[\\s\\-\\(\\)]*\\d{3}[\\s\\-\\(\\)]*\\d{2}[\\s\\-\\(\\)]*\\d{2}',
				],
				'constraints' => [
					new NotBlank(message: 'Enter customer phone.'),
					new Regex(pattern: '/^(?:\+?380|0)[\s\-\(\)]*\d{2}[\s\-\(\)]*\d{3}[\s\-\(\)]*\d{2}[\s\-\(\)]*\d{2}$/', message: 'Enter valid Ukrainian phone number.'),
				],
			])
			->add('email')
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choices' => ActiveStatusEnum::userSelectableCases(),
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
