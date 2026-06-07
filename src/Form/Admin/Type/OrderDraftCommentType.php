<?php

namespace App\Form\Admin\Type;

use App\Enum\CommentTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderDraftCommentType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('body', HiddenType::class, [
				'constraints' => [
					new NotBlank([
						'message' => 'Order comment must not be blank.',
						'normalizer' => 'trim',
					]),
				],
			])
			->add('type', HiddenType::class, [
				'empty_data' => CommentTypeEnum::GENERAL->value,
				'constraints' => [
					new Choice(choices: array_map(
						static fn (CommentTypeEnum $type): string => $type->value,
						CommentTypeEnum::cases(),
					)),
				],
			])
			->add('important', HiddenType::class, [
				'empty_data' => '0',
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => null,
		]);
	}
}
