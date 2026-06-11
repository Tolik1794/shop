<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\IncomeClassificationEnum;
use App\Enum\IncomeSourceTypeEnum;
use App\Repository\IncomeRecordRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IncomeRecordRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_income_record_source', columns: ['source_type', 'source_id'], options: ['where' => '(source_id IS NOT NULL)'])]
#[ORM\Index(name: 'idx_income_record_entity_recognized', columns: ['legal_entity_id', 'recognized_at'])]
#[ORM\Index(name: 'idx_income_record_entity_class_recognized', columns: ['legal_entity_id', 'classification', 'recognized_at'])]
class IncomeRecord
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private ?LegalEntity $legalEntity = null;

	#[ORM\ManyToOne]
	private ?Store $store = null;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ?DateTimeImmutable $recognizedAt = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $amount = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false)]
	private ?Currency $currency = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $amountUah = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8)]
	private ?string $nbuExchangeRate = '1.00000000';

	#[ORM\Column(length: 32, enumType: IncomeSourceTypeEnum::class)]
	private IncomeSourceTypeEnum $sourceType;

	#[ORM\Column(nullable: true)]
	private ?int $sourceId = null;

	#[ORM\ManyToOne]
	private ?Payment $payment = null;

	#[ORM\Column(length: 32, enumType: IncomeClassificationEnum::class)]
	private IncomeClassificationEnum $classification;

	#[ORM\ManyToOne]
	private ?IncomeRecord $refundOfIncomeRecord = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $counterparty = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $paymentPurpose = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	public function __construct()
	{
		$this->sourceType = IncomeSourceTypeEnum::MANUAL;
		$this->classification = IncomeClassificationEnum::INCOME;
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

	public function getStore(): ?Store
	{
		return $this->store;
	}

	public function setStore(?Store $store): self
	{
		$this->store = $store;

		return $this;
	}

	public function getRecognizedAt(): ?DateTimeImmutable
	{
		return $this->recognizedAt;
	}

	public function setRecognizedAt(DateTimeImmutable $recognizedAt): self
	{
		$this->recognizedAt = $recognizedAt;

		return $this;
	}

	public function getAmount(): ?string
	{
		return $this->amount;
	}

	public function setAmount(string $amount): self
	{
		$this->amount = $amount;

		return $this;
	}

	public function getCurrency(): ?Currency
	{
		return $this->currency;
	}

	public function setCurrency(?Currency $currency): self
	{
		$this->currency = $currency;

		return $this;
	}

	public function getAmountUah(): ?string
	{
		return $this->amountUah;
	}

	public function setAmountUah(string $amountUah): self
	{
		$this->amountUah = $amountUah;

		return $this;
	}

	public function getNbuExchangeRate(): ?string
	{
		return $this->nbuExchangeRate;
	}

	public function setNbuExchangeRate(string $nbuExchangeRate): self
	{
		$this->nbuExchangeRate = $nbuExchangeRate;

		return $this;
	}

	public function getSourceType(): IncomeSourceTypeEnum
	{
		return $this->sourceType;
	}

	public function setSourceType(IncomeSourceTypeEnum $sourceType): self
	{
		$this->sourceType = $sourceType;

		return $this;
	}

	public function getSourceId(): ?int
	{
		return $this->sourceId;
	}

	public function setSourceId(?int $sourceId): self
	{
		$this->sourceId = $sourceId;

		return $this;
	}

	public function getPayment(): ?Payment
	{
		return $this->payment;
	}

	public function setPayment(?Payment $payment): self
	{
		$this->payment = $payment;

		return $this;
	}

	public function getClassification(): IncomeClassificationEnum
	{
		return $this->classification;
	}

	public function setClassification(IncomeClassificationEnum $classification): self
	{
		$this->classification = $classification;

		return $this;
	}

	public function getRefundOfIncomeRecord(): ?IncomeRecord
	{
		return $this->refundOfIncomeRecord;
	}

	public function setRefundOfIncomeRecord(?IncomeRecord $refundOfIncomeRecord): self
	{
		$this->refundOfIncomeRecord = $refundOfIncomeRecord;

		return $this;
	}

	public function getCounterparty(): ?string
	{
		return $this->counterparty;
	}

	public function setCounterparty(?string $counterparty): self
	{
		$this->counterparty = $counterparty;

		return $this;
	}

	public function getPaymentPurpose(): ?string
	{
		return $this->paymentPurpose;
	}

	public function setPaymentPurpose(?string $paymentPurpose): self
	{
		$this->paymentPurpose = $paymentPurpose;

		return $this;
	}

	public function getComment(): ?string
	{
		return $this->comment;
	}

	public function setComment(?string $comment): self
	{
		$this->comment = $comment;

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

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?User $createdBy): self
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	public function getUpdatedBy(): ?User
	{
		return $this->updatedBy;
	}

	public function setUpdatedBy(?User $updatedBy): self
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}
}
