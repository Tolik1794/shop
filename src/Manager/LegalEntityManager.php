<?php

namespace App\Manager;

use App\Entity\LegalEntity;
use App\Entity\User\User;
use App\Service\Lifecycle\ReferenceArchivePolicy;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class LegalEntityManager
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly UserManager $userManager,
		private readonly ReferenceArchivePolicy $referenceArchivePolicy,
	)
	{
	}

	public function save(LegalEntity $legalEntity): void
	{
		$actor = $this->currentActor();

		if (!$legalEntity->getId()) {
			$legalEntity->setCreatedBy($actor);
		}

		$legalEntity
			->setUpdatedBy($actor)
			->setUpdatedAt(new DateTimeImmutable());

		$this->entityManager->persist($legalEntity);
		$this->entityManager->flush();
	}

	public function softDelete(LegalEntity $legalEntity): void
	{
		$this->referenceArchivePolicy->archive($legalEntity);
		$legalEntity->setUpdatedBy($this->currentActor());

		$this->entityManager->flush();
	}

	private function currentActor(): ?User
	{
		$user = $this->userManager->getCurrentUser();

		return $user instanceof User ? $user : null;
	}
}
