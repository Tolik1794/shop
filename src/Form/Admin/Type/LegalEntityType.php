<?php

namespace App\Form\Admin\Type;

use App\Entity\LegalEntity;
use App\Enum\ActiveStatusEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LegalEntityType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('name', TextType::class, [
				'label' => 'admin.legal_entity.fields.name',
			])
			->add('shortName', TextType::class, [
				'label' => 'admin.legal_entity.fields.short_name',
				'required' => false,
			])
			->add('type', EnumType::class, [
				'class' => LegalEntityTypeEnum::class,
				'choice_label' => static fn (LegalEntityTypeEnum $choice): string => 'admin.legal_entity.type.' . $choice->value,
				'label' => 'admin.legal_entity.fields.type',
			])
			->add('taxNumber', TextType::class, [
				'label' => 'admin.legal_entity.fields.tax_number',
				'help' => 'admin.legal_entity.fields.tax_number_help',
			])
			->add('taxSystem', EnumType::class, [
				'class' => TaxSystemEnum::class,
				'choice_label' => static fn (TaxSystemEnum $choice): string => 'admin.legal_entity.tax_system.' . $choice->value,
				'label' => 'admin.legal_entity.fields.tax_system',
			])
			->add('epGroup', ChoiceType::class, [
				'choices' => [1 => 1, 2 => 2, 3 => 3],
				'required' => false,
				'placeholder' => 'admin.legal_entity.fields.ep_group_placeholder',
				'label' => 'admin.legal_entity.fields.ep_group',
			])
			->add('epRate', ChoiceType::class, [
				'choices' => ['5%' => '5.00', '3%' => '3.00'],
				'required' => false,
				'placeholder' => 'admin.legal_entity.fields.ep_rate_placeholder',
				'label' => 'admin.legal_entity.fields.ep_rate',
				'help' => 'admin.legal_entity.fields.ep_rate_help',
			])
			->add('vatPayer', CheckboxType::class, [
				'label' => 'admin.legal_entity.fields.vat_payer',
				'required' => false,
			])
			->add('esvExempt', CheckboxType::class, [
				'label' => 'admin.legal_entity.fields.esv_exempt',
				'required' => false,
				'help' => 'admin.legal_entity.fields.esv_exempt_help',
			])
			->add('registeredAt', DateType::class, [
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
				'required' => false,
				'label' => 'admin.legal_entity.fields.registered_at',
			])
			->add('simplifiedSince', DateType::class, [
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
				'required' => false,
				'label' => 'admin.legal_entity.fields.simplified_since',
			])
			->add('address', TextareaType::class, [
				'required' => false,
				'label' => 'admin.legal_entity.fields.address',
			])
			->add('kveds', TextType::class, [
				'required' => false,
				'label' => 'admin.legal_entity.fields.kveds',
				'help' => 'admin.legal_entity.fields.kveds_help',
			])
			->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class,
				'choices' => ActiveStatusEnum::userSelectableCases(),
				'choice_label' => static fn (ActiveStatusEnum $choice): string => $choice->value,
				'label' => 'admin.legal_entity.fields.status',
			])
		;

		$builder->get('kveds')->addModelTransformer(new CallbackTransformer(
			static fn (?array $kveds): string => implode(', ', $kveds ?? []),
			static function (?string $kveds): ?array {
				$values = array_values(array_filter(array_map('trim', explode(',', (string) $kveds))));

				return $values !== [] ? $values : null;
			}
		));
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => LegalEntity::class,
		]);
	}
}
