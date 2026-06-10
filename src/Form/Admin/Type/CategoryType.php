<?php

namespace App\Form\Admin\Type;

use App\Entity\Category;
use App\Repository\CategoryProductParameterNameRepository;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
	public function __construct(private readonly CategoryProductParameterNameRepository $categoryParameterRepository)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
		/** @var Category $category */
		$category = $builder->getData();
		$builder
            ->add('name')
            ->add('description', TextareaType::class, ['required' => false])
	        ->add('parent', EntityType::class, [
		        'query_builder' => function (CategoryRepository $repository) use ($category) {
					$qb = $repository->findAvailableCategoriesAsListQB($category->getStore())
						->orderBy('category.name', 'ASC');

					if ($category->getId()) {
						$qb->andWhere('category.id != :category')
							->setParameter('category', $category->getId());
					}

					return $qb;
				},
		        'class' => Category::class,
		        'choice_label' => 'nameWithParent',
		        'multiple' => false,
		        'required' => false,
				'disabled' => false,
		        'label' => 'admin.category.fields.parent',
		        'attr' => ['class' => 'select2'],
	        ])
        ;

		if ($options['manage_parameters']) {
			$builder->add('categoryProductParameterNames', CollectionType::class, [
				'entry_type' => CategoryParameterType::class,
				'entry_options' => ['label' => false],
				'allow_add' => true,
				'allow_delete' => true,
				'by_reference' => false,
				'prototype' => true,
				'label' => false,
			]);

			$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
				$data = $event->getData();

				if (!is_array($data) || !isset($data['categoryProductParameterNames']) || !is_array($data['categoryProductParameterNames'])) {
					return;
				}

				foreach ($data['categoryProductParameterNames'] as $key => $parameterData) {
					if (!is_array($parameterData) || trim((string) ($parameterData['productParameterName'] ?? '')) === '') {
						unset($data['categoryProductParameterNames'][$key]);
					}
				}

				$event->setData($data);
			});

			$builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
				$form = $event->getForm();
				$category = $event->getData();

				if (!$category instanceof Category || !$form->has('categoryProductParameterNames')) {
					return;
				}

				$inheritedIds = [];
				foreach ($this->categoryParameterRepository->findInheritedByParent($category->getParent()) as $inheritedParameter) {
					$id = $inheritedParameter->getProductParameterName()?->getId();
					if ($id) {
						$inheritedIds[$id] = true;
					}
				}

				$usedIds = [];
				foreach ($form->get('categoryProductParameterNames') as $parameterForm) {
					$id = $parameterForm->getData()?->getProductParameterName()?->getId();
					if (!$id) {
						continue;
					}

					if (isset($inheritedIds[$id])) {
						$parameterForm->get('productParameterName')->addError(new FormError('This parameter is already inherited from a parent category.'));
					}

					if (isset($usedIds[$id])) {
						$parameterForm->get('productParameterName')->addError(new FormError('This category parameter is already added.'));
					}

					$usedIds[$id] = true;
				}
			});
		}
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
	        'manage_parameters' => false,
        ]);
	    $resolver->setAllowedTypes('manage_parameters', 'bool');
    }
}
