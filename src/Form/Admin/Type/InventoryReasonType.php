<?php

namespace App\Form\Admin\Type;

use App\Entity\InventoryReason;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryReasonType as InventoryReasonTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InventoryReasonType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name')
			->add('type', EnumType::class, [
				'class' => InventoryReasonTypeEnum::class,
				'choice_label' => fn(InventoryReasonTypeEnum $choice): string => $choice->value,
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choice_label' => fn(ActiveStatusEnum $choice): string => $choice->value,
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => InventoryReason::class,
		]);
	}
}
