<?php

namespace App\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentTypeEnum;
use App\Repository\OrderRepository;
use App\Repository\PurchaseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class PaymentType extends AbstractType
{
	public function __construct(
		private readonly OrderRepository $orderRepository,
		private readonly PurchaseRepository $purchaseRepository,
	)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var Store|null $store */
		$store = $options['store'];
		$payment = $builder->getData();
		$lockPrefilledDocumentFields = $options['lock_prefilled_document_fields'];

		$builder
			->add('direction', EnumType::class, [
				'class' => PaymentDirectionEnum::class,
				'choice_label' => fn (PaymentDirectionEnum $choice): string => $choice->value,
				'disabled' => $lockPrefilledDocumentFields,
				'attr' => [
					'class' => 'select2',
					'data-payment-form-target' => 'direction',
				],
			])
			->add('type', EnumType::class, [
				'class' => PaymentTypeEnum::class,
				'choice_label' => fn (PaymentTypeEnum $choice): string => $choice->value,
				'attr' => ['class' => 'select2'],
			])
			->add('amount', NumberType::class, [
				'html5' => true,
				'scale' => 4,
				'help' => $this->amountHelp($payment),
				'disabled' => $lockPrefilledDocumentFields,
				'attr' => [
					'min' => 0.0001,
					'step' => '0.0001',
					'data-payment-form-target' => 'amount',
				],
				'constraints' => [
					new GreaterThan([
						'value' => 0,
						'message' => 'Amount must be greater than zero.',
					]),
				],
			])
			->add('paidAt', DateTimeType::class, [
				'widget' => 'single_text',
				'input' => 'datetime_immutable',
			])
			->add('currency', EntityType::class, [
				'class' => Currency::class,
				'choice_label' => 'code',
				'disabled' => $lockPrefilledDocumentFields,
				'attr' => [
					'class' => 'select2',
					'data-payment-form-target' => 'currency',
				],
			])
			->add('documentType', ChoiceType::class, [
				'choices' => [
					'Order' => 'order',
					'Purchase' => 'purchase',
				],
				'expanded' => true,
				'mapped' => false,
				'required' => false,
				'data' => $this->documentType($payment),
				'placeholder_attr' => [
					'data-payment-form-target' => 'documentType',
					'data-action' => 'change->payment-form#toggle',
				],
				'choice_attr' => fn (): array => [
					'data-payment-form-target' => 'documentType',
					'data-action' => 'change->payment-form#toggle',
				],
			])
			->add('externalReference', TextType::class, [
				'required' => false,
			])
			->add('comment', TextareaType::class, [
				'required' => false,
			]);

		$this->addOrderField($builder, $options, $payment instanceof Payment ? $payment->getOrder() : null);
		$this->addPurchaseField($builder, $options, $payment instanceof Payment ? $payment->getPurchase() : null);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
			$data = $event->getData();

			if (!is_array($data) || !$options['store'] instanceof Store) {
				return;
			}

			$order = !empty($data['order'])
				? $this->orderRepository->findOneBy(['id' => $data['order'], 'store' => $options['store']])
				: null;
			$purchase = !empty($data['purchase'])
				? $this->purchaseRepository->findOneBy(['id' => $data['purchase'], 'store' => $options['store']])
				: null;

			$this->addOrderField($event->getForm(), $options, $order instanceof Order ? $order : null);
			$this->addPurchaseField($event->getForm(), $options, $purchase instanceof Purchase ? $purchase : null);
		});
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => Payment::class,
			'store' => null,
			'order_ajax_url' => null,
			'purchase_ajax_url' => null,
			'lock_prefilled_document_fields' => false,
		]);

		$resolver->setAllowedTypes('store', ['null', Store::class]);
		$resolver->setAllowedTypes('order_ajax_url', ['null', 'string']);
		$resolver->setAllowedTypes('purchase_ajax_url', ['null', 'string']);
		$resolver->setAllowedTypes('lock_prefilled_document_fields', 'bool');
	}

	private function amountHelp(mixed $payment): ?string
	{
		if (!$payment instanceof Payment) {
			return null;
		}

		if ($payment->getOrder() instanceof Order) {
			return 'Prefilled with the remaining unpaid amount for the linked order.';
		}

		if ($payment->getPurchase() instanceof Purchase) {
			return 'Prefilled with the remaining unpaid amount for the linked purchase.';
		}

		return null;
	}

	private function addOrderField(FormBuilderInterface|FormInterface $form, array $options, ?Order $selectedOrder = null): void
	{
		$form->add('order', EntityType::class, [
			'class' => Order::class,
			'choices' => $selectedOrder ? [$selectedOrder] : [],
			'choice_label' => fn (Order $order): string => $this->orderLabel($order),
			'placeholder' => 'Select order',
			'required' => false,
			'disabled' => $options['lock_prefilled_document_fields'],
			'attr' => [
				'class' => 'select2',
				'data-select2-ajax-url' => $options['order_ajax_url'],
				'data-select2-minimum-input-length' => 3,
				'data-payment-form-target' => 'order',
			],
		]);
	}

	private function addPurchaseField(FormBuilderInterface|FormInterface $form, array $options, ?Purchase $selectedPurchase = null): void
	{
		$form->add('purchase', EntityType::class, [
			'class' => Purchase::class,
			'choices' => $selectedPurchase ? [$selectedPurchase] : [],
			'choice_label' => fn (Purchase $purchase): string => $this->purchaseLabel($purchase),
			'placeholder' => 'Select purchase',
			'required' => false,
			'disabled' => $options['lock_prefilled_document_fields'],
			'attr' => [
				'class' => 'select2',
				'data-select2-ajax-url' => $options['purchase_ajax_url'],
				'data-select2-minimum-input-length' => 3,
				'data-payment-form-target' => 'purchase',
			],
		]);
	}

	private function documentType(mixed $payment): ?string
	{
		if (!$payment instanceof Payment) {
			return null;
		}

		if ($payment->getOrder() instanceof Order) {
			return 'order';
		}

		if ($payment->getPurchase() instanceof Purchase) {
			return 'purchase';
		}

		return null;
	}

	private function orderLabel(Order $order): string
	{
		return $order->getCustomerNameSnapshot()
			? sprintf('%s — %s', $order->getNumber(), $order->getCustomerNameSnapshot())
			: (string) $order->getNumber();
	}

	private function purchaseLabel(Purchase $purchase): string
	{
		return $purchase->getSupplierNameSnapshot()
			? sprintf('%s — %s', $purchase->getNumber(), $purchase->getSupplierNameSnapshot())
			: (string) $purchase->getNumber();
	}
}
