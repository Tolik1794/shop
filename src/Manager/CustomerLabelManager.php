<?php

namespace App\Manager;

use App\Entity\CustomerLabel;
use App\Repository\CustomerLabelRepository;
use App\Service\Lifecycle\ReferenceArchivePolicy;
use Doctrine\ORM\EntityManagerInterface;

class CustomerLabelManager extends AbstractManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly ReferenceArchivePolicy $referenceArchivePolicy,
	)
	{
	}

	public function getRepository(): CustomerLabelRepository
	{
		return $this->entityManager->getRepository(CustomerLabel::class);
	}

	public function archive(CustomerLabel $customerLabel): void
	{
		$this->referenceArchivePolicy->archive($customerLabel);
		$this->save($customerLabel);
	}

	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}
}
