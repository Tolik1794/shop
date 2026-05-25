<?php

namespace App\Form\Admin\Type;

use App\Dto\Admin\Inventory\InventoryStockAdjustmentOperation;
use App\Entity\InventoryReason;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryReasonType;
use App\Repository\InventoryReasonRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

class InventoryStockAdjustmentOperationType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('reason', EntityType::class, [
				'class' => InventoryReason::class,
				'query_builder' => fn (InventoryReasonRepository $repository) => $options['store'] instanceof Store
					? $repository->findAvailableByStoreQB($options['store'])
						->andWhere('inventoryReason.status = :status')
						->andWhere('inventoryReason.type IN (:types)')
						->setParameter('status', ActiveStatusEnum::ACTIVE)
						->setParameter('types', [
							InventoryReasonType::STOCK_ADJUSTMENT,
							InventoryReasonType::INVENTORY_COUNT,
							InventoryReasonType::INITIAL_STOCK,
							InventoryReasonType::OTHER,
						])
						->orderBy('inventoryReason.type', 'ASC')
						->addOrderBy('inventoryReason.name', 'ASC')
					: $repository->createQueryBuilder('inventoryReason')->andWhere('1 = 0'),
				'choice_label' => fn (InventoryReason $reason): string => sprintf('%s (%s)', $reason->getName(), $reason->getType()->value),
				'placeholder' => 'Select reason',
				'attr' => ['class' => 'select2'],
				'help' => 'Required for stock adjustment documents.',
				'constraints' => [new NotBlank(['message' => 'Select reason.'])],
			])
			->add('number', TextType::class, [
				'required' => false,
				'help' => 'Leave empty to generate automatically.',
			])
			->add('lines', CollectionType::class, [
				'entry_type' => InventoryStockAdjustmentLineType::class,
				'entry_options' => [
					'label' => false,
					'store' => $options['store'],
				],
				'allow_add' => true,
				'allow_delete' => true,
				'by_reference' => false,
				'label' => false,
				'constraints' => [new Count(['min' => 1, 'minMessage' => 'Add at least one adjustment line.'])],
			]);
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => InventoryStockAdjustmentOperation::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
