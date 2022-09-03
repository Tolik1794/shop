<?php

namespace App\Service;

use App\Form\Extension\QueryCallableExtension;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class FilterFormHandler
{
	private readonly ?Request $request;

	public function __construct(private readonly FormFactoryInterface $formFactory, RequestStack $requestStack)
	{
		$this->request = $requestStack->getCurrentRequest();
	}

	public function handleFilterForm(FormInterface $form, QueryBuilder $queryBuilder): void
	{
		/** @var callable|null $targetCallback */
		$targetCallback = $form->getConfig()->getOption(QueryCallableExtension::NAME);
		if (null !== $targetCallback) {
			$formData = $form->getData();
			$targetCallback($queryBuilder, $formData);
		}
		foreach ($form->all() as $child) {
			$this->handleFilterForm($child, $queryBuilder);
		}
	}
}