<?php

namespace App\Entity;

use App\Enum\TaxPeriodStatusEnum;
use App\Enum\TaxPeriodTypeEnum;
use App\Repository\TaxReportingPeriodRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxReportingPeriodRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tax_reporting_period_entity_type_from', columns: ['legal_entity_id', 'type', 'date_from'])]
class TaxReportingPeriod
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?LegalEntity $legalEntity = null;

	#[ORM\Column(length: 32, enumType: TaxPeriodTypeEnum::class)]
	private TaxPeriodTypeEnum $type;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ?DateTimeImmutable $dateFrom = null;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ?DateTimeImmutable $dateTo = null;

	#[ORM\Column(length: 32, enumType: TaxPeriodStatusEnum::class)]
	private TaxPeriodStatusEnum $status;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	public function __construct()
	{
		$this->type = TaxPeriodTypeEnum::QUARTER;
		$this->status = TaxPeriodStatusEnum::OPEN;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getLegalEntity(): ?LegalEntity
	{
		return $this->legalEntity;
	}

	public function setLegalEntity(?LegalEntity $legalEntity): self
	{
		$this->legalEntity = $legalEntity;

		return $this;
	}

	public function getType(): TaxPeriodTypeEnum
	{
		return $this->type;
	}

	public function setType(TaxPeriodTypeEnum $type): self
	{
		$this->type = $type;

		return $this;
	}

	public function getDateFrom(): ?DateTimeImmutable
	{
		return $this->dateFrom;
	}

	public function setDateFrom(DateTimeImmutable $dateFrom): self
	{
		$this->dateFrom = $dateFrom;

		return $this;
	}

	public function getDateTo(): ?DateTimeImmutable
	{
		return $this->dateTo;
	}

	public function setDateTo(DateTimeImmutable $dateTo): self
	{
		$this->dateTo = $dateTo;

		return $this;
	}

	public function getStatus(): TaxPeriodStatusEnum
	{
		return $this->status;
	}

	public function setStatus(TaxPeriodStatusEnum $status): self
	{
		$this->status = $status;

		return $this;
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTimeImmutable $updatedAt): self
	{
		$this->updatedAt = $updatedAt;

		return $this;
	}
}
