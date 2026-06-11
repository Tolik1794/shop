<?php

namespace App\Entity;

use App\Enum\TaxAccrualStatusEnum;
use App\Enum\TaxTypeEnum;
use App\Repository\TaxAccrualRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxAccrualRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tax_accrual_entity_period_tax', columns: ['legal_entity_id', 'period_id', 'tax_type'])]
#[ORM\Index(name: 'idx_tax_accrual_entity_due_date', columns: ['legal_entity_id', 'due_date'])]
#[ORM\Index(name: 'idx_tax_accrual_status_due_date', columns: ['status', 'due_date'])]
class TaxAccrual
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?LegalEntity $legalEntity = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(name: 'period_id', nullable: false)]
	private ?TaxReportingPeriod $period = null;

	#[ORM\Column(name: 'tax_type', length: 32, enumType: TaxTypeEnum::class)]
	private TaxTypeEnum $taxType;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $accruedAmount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $paidAmount = '0.0000';

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ?DateTimeImmutable $dueDate = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $paidAt = null;

	#[ORM\Column(length: 32, enumType: TaxAccrualStatusEnum::class)]
	private TaxAccrualStatusEnum $status;

	#[ORM\Column(nullable: true)]
	private ?int $bankStatementRowId = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	public function __construct()
	{
		$this->taxType = TaxTypeEnum::EP;
		$this->status = TaxAccrualStatusEnum::ACCRUED;
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

	public function getPeriod(): ?TaxReportingPeriod
	{
		return $this->period;
	}

	public function setPeriod(?TaxReportingPeriod $period): self
	{
		$this->period = $period;

		return $this;
	}

	public function getTaxType(): TaxTypeEnum
	{
		return $this->taxType;
	}

	public function setTaxType(TaxTypeEnum $taxType): self
	{
		$this->taxType = $taxType;

		return $this;
	}

	public function getAccruedAmount(): ?string
	{
		return $this->accruedAmount;
	}

	public function setAccruedAmount(string $accruedAmount): self
	{
		$this->accruedAmount = $accruedAmount;

		return $this;
	}

	public function getPaidAmount(): ?string
	{
		return $this->paidAmount;
	}

	public function setPaidAmount(string $paidAmount): self
	{
		$this->paidAmount = $paidAmount;

		return $this;
	}

	public function getDueDate(): ?DateTimeImmutable
	{
		return $this->dueDate;
	}

	public function setDueDate(DateTimeImmutable $dueDate): self
	{
		$this->dueDate = $dueDate;

		return $this;
	}

	public function getPaidAt(): ?DateTimeImmutable
	{
		return $this->paidAt;
	}

	public function setPaidAt(?DateTimeImmutable $paidAt): self
	{
		$this->paidAt = $paidAt;

		return $this;
	}

	public function getStatus(): TaxAccrualStatusEnum
	{
		return $this->status;
	}

	public function setStatus(TaxAccrualStatusEnum $status): self
	{
		$this->status = $status;

		return $this;
	}

	public function getBankStatementRowId(): ?int
	{
		return $this->bankStatementRowId;
	}

	public function setBankStatementRowId(?int $bankStatementRowId): self
	{
		$this->bankStatementRowId = $bankStatementRowId;

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
