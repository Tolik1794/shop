<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Store;
use App\Repository\CustomerRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class OrderType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var Store|null $store */
		$store = $options['store'];
		/** @var Order|null $order */
		$order = $builder->getData();

		$builder
			->add('version', HiddenType::class, [
				'mapped' => false,
				'data' => (string) ($order?->getVersion() ?? 1),
			])
			->add('customer', EntityType::class, [
				'class' => Customer::class,
				'query_builder' => fn(CustomerRepository $repository) => $store instanceof Store
					? $repository->findAvailableByStoreQB($store)->orderBy('customer.name', 'ASC')
					: $repository->createQueryBuilder('customer')->andWhere('1 = 0'),
				'choice_label' => 'fullName',
				'required' => false,
				'attr' => [
					'class' => 'd-none',
					'data-order-form-target' => 'customerSelect',
				],
			])
			->add('customerPhone', TelType::class, [
				'label' => 'Phone',
				'mapped' => false,
				'required' => true,
				'data' => $order?->getCustomer()?->getPhone(),
				'attr' => [
					'autocomplete' => 'off',
					'inputmode' => 'tel',
					'placeholder' => '+380XXXXXXXXX',
					'pattern' => '(\\+?380|0)[\\s\\-\\(\\)]*\\d{2}[\\s\\-\\(\\)]*\\d{3}[\\s\\-\\(\\)]*\\d{2}[\\s\\-\\(\\)]*\\d{2}',
					'data-order-form-target' => 'customerPhone',
					'data-action' => 'input->order-form#customerChanged focus->order-form#customerPhoneFocused',
				],
				'constraints' => [
					new NotBlank(message: 'Enter customer phone.'),
					new Regex(pattern: '/^(?:\+?380|0)[\s\-\(\)]*\d{2}[\s\-\(\)]*\d{3}[\s\-\(\)]*\d{2}[\s\-\(\)]*\d{2}$/', message: 'Enter valid Ukrainian phone number.'),
				],
			])
			->add('customerName', TextType::class, [
				'label' => 'First name',
				'mapped' => false,
				'required' => true,
				'data' => $order?->getCustomer()?->getName(),
				'attr' => [
					'data-order-form-target' => 'customerName',
					'data-action' => 'input->order-form#customerDetailsChanged',
				],
				'constraints' => [
					new NotBlank(['message' => 'Enter customer first name.']),
				],
			])
			->add('customerLastName', TextType::class, [
				'label' => 'Last name',
				'mapped' => false,
				'required' => true,
				'data' => $order?->getCustomer()?->getLastName(),
				'attr' => [
					'data-order-form-target' => 'customerLastName',
					'data-action' => 'input->order-form#customerDetailsChanged',
				],
				'constraints' => [
					new NotBlank(message: 'Enter customer last name.'),
				],
			])
			->add('currency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'required' => true,
				'attr' => ['class' => 'select2'],
			])
			->add('deliveryAddress', TextareaType::class, [
				'required' => false,
			])
			->add('orderEntries', CollectionType::class, [
				'entry_type' => OrderEntryType::class,
				'entry_options' => [
					'label' => false,
					'store' => $store,
					'currency' => $order?->getCurrency(),
				],
				'allow_add' => true,
				'allow_delete' => true,
				'by_reference' => false,
				'label' => false,
			])
			->add('draftComments', CollectionType::class, [
				'entry_type' => HiddenType::class,
				'entry_options' => [
					'constraints' => [
						new NotBlank([
							'message' => 'Order comment must not be blank.',
							'normalizer' => 'trim',
						]),
					],
				],
				'allow_add' => true,
				'allow_delete' => true,
				'mapped' => false,
				'required' => false,
				'label' => false,
			]);

		$builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
			$order = $event->getData();
			$form = $event->getForm();

			if ($order instanceof Order && $form->has('version')) {
				$form->get('version')->setData((string) $order->getVersion());
			}
		});
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => Order::class,
			'store' => null,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
	}
}
