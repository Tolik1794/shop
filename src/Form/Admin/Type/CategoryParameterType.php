<?php

namespace App\Form\Admin\Type;

use App\Entity\CategoryProductParameterName;
use App\Entity\ProductParameterName;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryParameterType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('productParameterName', EntityType::class, [
				'class' => ProductParameterName::class,
				'choice_label' => 'name',
				'placeholder' => 'admin.category.parameters.select_parameter',
				'required' => true,
				'attr' => ['class' => 'select2'],
			])
			->add('isRequired')
			->add('isFilter')
		;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => CategoryProductParameterName::class,
		]);
	}
}
