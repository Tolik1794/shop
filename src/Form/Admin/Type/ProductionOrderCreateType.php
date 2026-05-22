<?php

namespace App\Form\Admin\Type;

use App\Entity\ProductionRecipe;
use App\Entity\Store;
use App\Entity\Warehouse;
use App\Enum\ActiveStatusEnum;
use App\Repository\ProductionRecipeRepository;
use App\Repository\WarehouseRepository;
use App\Service\Quantity\QuantityFormatter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class ProductionOrderCreateType extends AbstractType
{
	public function __construct(
		private readonly ProductionRecipeRepository $productionRecipeRepository,
		private readonly QuantityFormatter $quantityFormatter,
	)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$data = $builder->getData();
		$recipe = is_array($data) && ($data['recipe'] ?? null) instanceof ProductionRecipe ? $data['recipe'] : null;

		$builder
			->add('recipe', EntityType::class, [
				'class' => ProductionRecipe::class,
				'query_builder' => fn (ProductionRecipeRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])
						->andWhere('productionRecipe.status = :activeStatus')
						->setParameter('activeStatus', ActiveStatusEnum::ACTIVE)
						->orderBy('productionRecipe.name', 'ASC')
					: $repository->createQueryBuilder('productionRecipe')->andWhere('1 = 0'),
				'choice_label' => fn (ProductionRecipe $recipe): string => sprintf('%s - %s', $recipe->getName(), $recipe->getProduct()?->getName()),
				'placeholder' => 'Select recipe',
				'attr' => ['class' => 'select2'],
			])
			->add('warehouse', EntityType::class, [
				'class' => Warehouse::class,
				'query_builder' => fn (WarehouseRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])->orderBy('warehouse.name', 'ASC')
					: $repository->createQueryBuilder('warehouse')->andWhere('1 = 0'),
				'choice_label' => 'name',
				'placeholder' => 'Select warehouse',
				'required' => false,
				'attr' => ['class' => 'select2'],
			]);

		$this->addPlannedQuantityField($builder, $recipe);

		$builder
			->add('plannedStartAt', DateTimeType::class, [
				'required' => false,
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('plannedEndAt', DateTimeType::class, [
				'required' => false,
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			]);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
			$data = $event->getData();

			if (!is_array($data) || empty($data['recipe']) || !$options['store'] instanceof Store) {
				return;
			}

			$recipe = $this->productionRecipeRepository->findOneBy([
				'id' => $data['recipe'],
				'store' => $options['store'],
				'status' => ActiveStatusEnum::ACTIVE,
			]);

			if ($recipe instanceof ProductionRecipe) {
				$this->addPlannedQuantityField($event->getForm(), $recipe);
			}
		});
	}

	private function addPlannedQuantityField(FormBuilderInterface|FormInterface $form, ?ProductionRecipe $recipe = null): void
	{
		$product = $recipe?->getProduct();
		$precision = $this->quantityFormatter->precisionForProduct($product);
		$isIntegerQuantity = $precision === 0;
		$step = $this->quantityFormatter->stepForPrecision($precision);

		$options = [
			'help' => $product?->getUnit()?->getCode() ?? ' ',
			'constraints' => [
				new GreaterThan([
					'value' => 0,
					'message' => 'Planned quantity must be greater than zero.',
				]),
			],
			'attr' => [
				'min' => $step,
				'step' => $step,
			],
		];

		if ($isIntegerQuantity) {
			$options['invalid_message'] = 'Planned quantity must be an integer.';
		} else {
			$options['html5'] = true;
			$options['scale'] = $precision;
		}

		$form->add('plannedQuantity', $isIntegerQuantity ? IntegerType::class : NumberType::class, $options);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'store' => null,
			'data_class' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
