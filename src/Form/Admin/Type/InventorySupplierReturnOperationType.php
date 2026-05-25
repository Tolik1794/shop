<?php

namespace App\Form\Admin\Type;

use App\Dto\Admin\Inventory\InventorySupplierReturnOperation;
use App\Entity\InventoryReason;
use App\Entity\PurchaseEntry;
use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Enum\InventoryReasonType;
use App\Repository\InventoryReasonRepository;
use App\Repository\PurchaseEntryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class InventorySupplierReturnOperationType extends AbstractType
{
	public function __construct(private readonly TranslatorInterface $translator)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		$builder
			->add('purchaseEntry', EntityType::class, [
				'class' => PurchaseEntry::class,
				'query_builder' => fn (PurchaseEntryRepository $repository) => $options['store'] instanceof Store
					? $repository->findReturnableByStoreQB($options['store'])
					: $repository->createQueryBuilder('purchaseEntry')->andWhere('1 = 0'),
				'choice_label' => fn (PurchaseEntry $entry): string => sprintf(
					'%s · %s (%s) · %s %s',
					$entry->getPurchase()?->getNumber() ?? ('#' . $entry->getPurchase()?->getId()),
					$entry->getProductNameSnapshot(),
					$entry->getProductCodeSnapshot(),
					$this->translator->trans('Available'),
					$this->remaining($entry->getReceivedQuantity(), $entry->getReturnedQuantity())
				),
				'placeholder' => 'Select received purchase line',
				'attr' => ['class' => 'select2'],
				'constraints' => [new NotBlank(['message' => 'Select purchase line.'])],
			])
			->add('reason', EntityType::class, [
				'class' => InventoryReason::class,
				'query_builder' => fn (InventoryReasonRepository $repository) => $this->returnReasonQueryBuilder($repository, $options),
				'choice_label' => fn (InventoryReason $reason): string => sprintf('%s (%s)', $reason->getName(), $reason->getType()->value),
				'placeholder' => 'No reason',
				'required' => false,
				'attr' => ['class' => 'select2'],
				'help' => 'Optional for supplier returns.',
			])
			->add('quantity', NumberType::class, [
				'html5' => true,
				'scale' => 4,
				'attr' => ['min' => '0.0001', 'step' => '0.0001'],
				'constraints' => [new GreaterThan(['value' => 0, 'message' => 'Quantity must be greater than zero.'])],
			])
			->add('number', TextType::class, [
				'required' => false,
				'help' => 'Leave empty to generate automatically.',
			]);
	}

	private function returnReasonQueryBuilder(InventoryReasonRepository $repository, array $options)
	{
		return $options['store'] instanceof Store
			? $repository->findAvailableByStoreQB($options['store'])
				->andWhere('inventoryReason.status = :status')
				->andWhere('inventoryReason.type IN (:types)')
				->setParameter('status', ActiveStatusEnum::ACTIVE)
				->setParameter('types', [InventoryReasonType::RETURN, InventoryReasonType::OTHER])
				->orderBy('inventoryReason.type', 'ASC')
				->addOrderBy('inventoryReason.name', 'ASC')
			: $repository->createQueryBuilder('inventoryReason')->andWhere('1 = 0');
	}

	private function remaining(?string $completed, ?string $returned): string
	{
		return number_format(max(0, (float) ($completed ?? '0') - (float) ($returned ?? '0')), 4, '.', '');
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => InventorySupplierReturnOperation::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
