<?php

namespace App\Validator;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Store;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

class PaymentBusinessValidator
{
	public function validate(Payment $payment, Store $store, FormInterface $form): bool
	{
		$targetCount = (int) ($payment->getOrder() instanceof Order)
			+ (int) ($payment->getPurchase() instanceof Purchase);

		if ($targetCount !== 1) {
			$form->addError(new FormError('Select exactly one payment document.'));
		}

		if ($payment->getOrder() instanceof Order && $payment->getOrder()->getStore()?->getId() !== $store->getId()) {
			$form->get('order')->addError(new FormError('Select order from current store.'));
		}

		if ($payment->getPurchase() instanceof Purchase && $payment->getPurchase()->getStore()?->getId() !== $store->getId()) {
			$form->get('purchase')->addError(new FormError('Select purchase from current store.'));
		}

		return $form->isValid();
	}
}
