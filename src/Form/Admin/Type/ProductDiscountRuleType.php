<?php

namespace App\Form\Admin\Type;

use App\Entity\ProductDiscountRule;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductDiscountRuleType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', TextType::class, [
				'constraints' => [
					new NotBlank(['message' => 'Enter discount name.']),
				],
			])
			->add('percent', NumberType::class, [
				'html5' => true,
				'scale' => 4,
				'attr' => [
					'min' => 0.0001,
					'max' => 100,
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThan([
						'value' => 0,
						'message' => 'Discount percent must be greater than zero.',
					]),
					new LessThanOrEqual([
						'value' => 100,
						'message' => 'Discount percent cannot exceed 100.',
					]),
				],
			])
			->add('isDefault', CheckboxType::class, [
				'required' => false,
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choices' => ActiveStatusEnum::userSelectableCases(),
				'choice_label' => static fn (ActiveStatusEnum $choice): string => 'admin.status.' . $choice->value,
			])
			->add('targets', CollectionType::class, [
				'entry_type' => ProductDiscountTargetType::class,
				'entry_options' => [
					'label' => false,
					'store' => $options['store'],
					'product_ajax_url' => $options['product_ajax_url'],
				],
				'allow_add' => true,
				'allow_delete' => true,
				'by_reference' => false,
				'label' => false,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ProductDiscountRule::class,
			'store' => null,
			'product_ajax_url' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('product_ajax_url', ['null', 'string']);
	}
}
