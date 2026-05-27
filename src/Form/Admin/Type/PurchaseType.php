<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class PurchaseType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var Store|null $store */
		$store = $options['store'];
		/** @var Purchase|null $purchase */
		$purchase = $builder->getData();

		$builder
			->add('version', HiddenType::class, [
				'mapped' => false,
				'data' => (string) ($purchase?->getVersion() ?? 1),
			])
			->add('supplier', EntityType::class, [
				'class' => Supplier::class,
				'query_builder' => fn (SupplierRepository $repository) => $store instanceof Store
					? $repository->findAvailableByStoreQB($store)->orderBy('supplier.name', 'ASC')
					: $repository->createQueryBuilder('supplier')->andWhere('1 = 0'),
				'choice_label' => 'name',
				'placeholder' => 'Select supplier',
				'required' => false,
				'attr' => ['class' => 'select2'],
			])
			->add('currency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'required' => true,
				'attr' => ['class' => 'select2'],
			])
			->add('invoiceNumber', TextType::class, [
				'required' => false,
			])
			->add('documentDate', DateType::class, [
				'required' => false,
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('deliveryCost', NumberType::class, [
				'required' => false,
				'html5' => true,
				'scale' => 4,
				'attr' => [
					'min' => 0,
					'step' => '0.0001',
				],
				'constraints' => [
					new GreaterThanOrEqual([
						'value' => 0,
						'message' => 'Delivery cost cannot be negative.',
					]),
				],
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			])
			->add('purchaseEntries', CollectionType::class, [
				'entry_type' => PurchaseEntryType::class,
				'entry_options' => [
					'label' => false,
					'store' => $store,
					'product_ajax_url' => $options['product_ajax_url'],
				],
				'allow_add' => true,
				'allow_delete' => true,
				'by_reference' => false,
				'label' => false,
			]);

		$builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
			$purchase = $event->getData();
			$form = $event->getForm();

			if ($purchase instanceof Purchase && $form->has('version')) {
				$form->get('version')->setData((string) $purchase->getVersion());
			}
		});
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => Purchase::class,
			'store' => null,
			'product_ajax_url' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('product_ajax_url', ['null', 'string']);
	}
}
