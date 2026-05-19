<?php

namespace App\Tests\Form\Admin\Type;

use App\Entity\Currency;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Store;
use App\Form\Admin\Type\PaymentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class PaymentTypeTest extends KernelTestCase
{
	private EntityManagerInterface $entityManager;
	private FormFactoryInterface $formFactory;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
		$this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->entityManager, $this->formFactory);
	}

	public function testAjaxBackedOrderFieldAcceptsSubmittedOrderChoice(): void
	{
		$currency = $this->persistCurrency('O' . substr(uniqid(), -2), 'Order form currency');
		$store = $this->persistStore('payment-form-order-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency);
		$payment = (new Payment())
			->setStore($store)
			->setCurrency($currency);

		$form = $this->formFactory->create(PaymentType::class, $payment, $this->formOptions($store));
		$form->submit($this->payload($currency, [
			'documentType' => 'order',
			'order' => (string) $order->getId(),
		]));

		self::assertTrue($form->isSynchronized());
		self::assertSame($order, $payment->getOrder());
		self::assertNull($payment->getPurchase());
	}

	public function testAjaxBackedPurchaseFieldAcceptsSubmittedPurchaseChoice(): void
	{
		$currency = $this->persistCurrency('P' . substr(uniqid(), -2), 'Purchase form currency');
		$store = $this->persistStore('payment-form-purchase-' . uniqid(), $currency);
		$purchase = $this->persistPurchase($store, $currency);
		$payment = (new Payment())
			->setStore($store)
			->setCurrency($currency);

		$form = $this->formFactory->create(PaymentType::class, $payment, $this->formOptions($store));
		$form->submit($this->payload($currency, [
			'documentType' => 'purchase',
			'purchase' => (string) $purchase->getId(),
		]));

		self::assertTrue($form->isSynchronized());
		self::assertNull($payment->getOrder());
		self::assertSame($purchase, $payment->getPurchase());
	}

	public function testDocumentTypeChoicesExposeStimulusToggleAttributes(): void
	{
		$currency = $this->persistCurrency('D' . substr(uniqid(), -2), 'Document type form currency');
		$store = $this->persistStore('payment-form-document-type-' . uniqid(), $currency);
		$payment = (new Payment())
			->setStore($store)
			->setCurrency($currency);

		$view = $this->formFactory->create(PaymentType::class, $payment, $this->formOptions($store))->createView();

		foreach ($view['documentType']->children as $choice) {
			self::assertSame('documentType', $choice->vars['attr']['data-payment-form-target'] ?? null);
			self::assertSame('change->payment-form#toggle', $choice->vars['attr']['data-action'] ?? null);
		}
	}

	public function testLockedDocumentFieldsKeepPrefilledValuesWithoutSubmittedInput(): void
	{
		$currency = $this->persistCurrency('L' . substr(uniqid(), -2), 'Locked payment form currency');
		$store = $this->persistStore('payment-form-locked-' . uniqid(), $currency);
		$order = $this->persistOrder($store, $currency);
		$payment = (new Payment())
			->setStore($store)
			->setCurrency($currency)
			->setOrder($order)
			->setAmount('42.0000');

		$form = $this->formFactory->create(PaymentType::class, $payment, array_merge($this->formOptions($store), [
			'lock_prefilled_document_fields' => true,
		]));
		$view = $form->createView();

		self::assertTrue($view['direction']->vars['disabled']);
		self::assertTrue($view['amount']->vars['disabled']);
		self::assertTrue($view['currency']->vars['disabled']);
		self::assertTrue($view['order']->vars['disabled']);

		$form->submit([
			'type' => 'cash',
			'paidAt' => '2026-05-18T10:00',
			'externalReference' => '',
			'comment' => '',
		]);

		self::assertTrue($form->isSynchronized());
		self::assertSame($currency, $payment->getCurrency());
		self::assertSame($order, $payment->getOrder());
		self::assertSame('42.0000', $payment->getAmount());
	}

	/**
	 * @return array<string, mixed>
	 */
	private function formOptions(Store $store): array
	{
		return [
			'store' => $store,
			'order_ajax_url' => '/order-search',
			'purchase_ajax_url' => '/purchase-search',
		];
	}

	/**
	 * @param array<string, string> $documentData
	 *
	 * @return array<string, string>
	 */
	private function payload(Currency $currency, array $documentData): array
	{
		return array_merge([
			'direction' => 'incoming',
			'type' => 'cash',
			'amount' => '10.0000',
			'paidAt' => '2026-05-18T10:00',
			'currency' => (string) $currency->getCode(),
		], $documentData);
	}

	private function persistCurrency(string $code, string $name): Currency
	{
		$currency = $this->entityManager->getRepository(Currency::class)->find($code);

		if ($currency instanceof Currency) {
			return $currency;
		}

		$currency = (new Currency())
			->setCode($code)
			->setName($name)
			->setSymbol($code)
			->setDecimalPlaces(2);

		$this->entityManager->persist($currency);
		$this->entityManager->flush();

		return $currency;
	}

	private function persistStore(string $slug, Currency $baseCurrency): Store
	{
		$store = (new Store())
			->setName($slug)
			->setSlug($slug)
			->setPhone('+380000000000')
			->setEmail($slug . '@example.com')
			->setBaseCurrency($baseCurrency);

		$this->entityManager->persist($store);
		$this->entityManager->flush();

		return $store;
	}

	private function persistOrder(Store $store, Currency $currency): Order
	{
		$order = (new Order())
			->setStore($store)
			->setCurrency($currency)
			->setNumber('SO-' . uniqid());

		$this->entityManager->persist($order);
		$this->entityManager->flush();

		return $order;
	}

	private function persistPurchase(Store $store, Currency $currency): Purchase
	{
		$purchase = (new Purchase())
			->setStore($store)
			->setCurrency($currency)
			->setNumber('PO-' . uniqid());

		$this->entityManager->persist($purchase);
		$this->entityManager->flush();

		return $purchase;
	}
}
