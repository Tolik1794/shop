<?php

namespace App\Controller\Admin;

use App\Exception\ConcurrencyConflictException;
use App\Service\Concurrency\ConcurrencyGuard;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

trait ConcurrencyFormTrait
{
	private function rejectStaleForm(FormInterface $form, object $entity, ConcurrencyGuard $concurrencyGuard): bool
	{
		if (!$form->has('version')) {
			return false;
		}

		try {
			$concurrencyGuard->assertSubmittedVersion($entity, $form->get('version')->getData());
		} catch (ConcurrencyConflictException $exception) {
			$form->addError(new FormError($exception->getMessage()));

			return true;
		}

		return false;
	}
}
