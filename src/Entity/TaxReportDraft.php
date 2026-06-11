<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\TaxReportDraftStatusEnum;
use App\Repository\TaxReportDraftRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxReportDraftRepository::class)]
#[ORM\Index(name: 'idx_tax_report_draft_entity_period', columns: ['legal_entity_id', 'period_id'])]
class TaxReportDraft
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

	#[ORM\Column(length: 64)]
	private ?string $reportType = null;

	#[ORM\Column(length: 255)]
	private ?string $formVersion = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	private ?array $payload = null;

	#[ORM\Column]
	private DateTimeImmutable $generatedAt;

	#[ORM\Column(length: 32, enumType: TaxReportDraftStatusEnum::class)]
	private TaxReportDraftStatusEnum $status;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	public function __construct()
	{
		$this->status = TaxReportDraftStatusEnum::DRAFT;
		$this->generatedAt = new DateTimeImmutable();
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

	public function getReportType(): ?string
	{
		return $this->reportType;
	}

	public function setReportType(string $reportType): self
	{
		$this->reportType = $reportType;

		return $this;
	}

	public function getFormVersion(): ?string
	{
		return $this->formVersion;
	}

	public function setFormVersion(string $formVersion): self
	{
		$this->formVersion = $formVersion;

		return $this;
	}

	public function getPayload(): ?array
	{
		return $this->payload;
	}

	public function setPayload(?array $payload): self
	{
		$this->payload = $payload;

		return $this;
	}

	public function getGeneratedAt(): DateTimeImmutable
	{
		return $this->generatedAt;
	}

	public function setGeneratedAt(DateTimeImmutable $generatedAt): self
	{
		$this->generatedAt = $generatedAt;

		return $this;
	}

	public function getStatus(): TaxReportDraftStatusEnum
	{
		return $this->status;
	}

	public function setStatus(TaxReportDraftStatusEnum $status): self
	{
		$this->status = $status;

		return $this;
	}

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?User $createdBy): self
	{
		$this->createdBy = $createdBy;

		return $this;
	}
}
