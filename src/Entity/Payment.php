<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\PaymentDirectionEnum;
use App\Enum\PaymentTypeEnum;
use App\Repository\PaymentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Index(name: 'idx_payment_store_paid_at', columns: ['store_id', 'paid_at'])]
#[ORM\Index(name: 'idx_payment_store_direction_paid_at', columns: ['store_id', 'direction', 'paid_at'])]
class Payment
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255, enumType: PaymentDirectionEnum::class)]
	private PaymentDirectionEnum $direction;

	#[ORM\Column(length: 255, enumType: PaymentTypeEnum::class)]
	private PaymentTypeEnum $type;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $amount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $amountBase = null;

	#[ORM\Column]
	private DateTimeImmutable $paidAt;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $externalReference = null;

	#[ORM\ManyToOne(inversedBy: 'payments')]
	#[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false)]
	private ?Currency $currency = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8)]
	private ?string $exchangeRateToBase = null;

	#[ORM\ManyToOne(inversedBy: 'payments')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne(inversedBy: 'payments')]
	private ?Order $order = null;

	#[ORM\ManyToOne(inversedBy: 'payments')]
	private ?Purchase $purchase = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\OneToOne(targetEntity: self::class, inversedBy: 'reversedByPayment')]
	private ?self $reversesPayment = null;

	#[ORM\OneToOne(mappedBy: 'reversesPayment', targetEntity: self::class)]
	private ?self $reversedByPayment = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	public function __construct()
	{
		$this->direction = PaymentDirectionEnum::INCOMING;
		$this->type = PaymentTypeEnum::CASH;
		$this->amount = '0.0000';
		$this->amountBase = '0.0000';
		$this->exchangeRateToBase = '1.00000000';
		$this->paidAt = new DateTimeImmutable();
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
	}

	public function getId(): ?int { return $this->id; }
	public function getDirection(): PaymentDirectionEnum { return $this->direction; }
	public function setDirection(PaymentDirectionEnum $direction): self { $this->direction = $direction; return $this; }
	public function getType(): PaymentTypeEnum { return $this->type; }
	public function setType(PaymentTypeEnum $type): self { $this->type = $type; return $this; }
	public function getAmount(): ?string { return $this->amount; }
	public function setAmount(string $amount): self { $this->amount = $amount; return $this; }
	public function getAmountBase(): ?string { return $this->amountBase; }
	public function setAmountBase(string $amountBase): self { $this->amountBase = $amountBase; return $this; }
	public function getPaidAt(): DateTimeImmutable { return $this->paidAt; }
	public function setPaidAt(DateTimeImmutable $paidAt): self { $this->paidAt = $paidAt; return $this; }
	public function getComment(): ?string { return $this->comment; }
	public function setComment(?string $comment): self { $this->comment = $comment; return $this; }
	public function getExternalReference(): ?string { return $this->externalReference; }
	public function setExternalReference(?string $externalReference): self { $this->externalReference = $externalReference; return $this; }
	public function getCurrency(): ?Currency { return $this->currency; }
	public function setCurrency(?Currency $currency): self { $this->currency = $currency; return $this; }
	public function getExchangeRateToBase(): ?string { return $this->exchangeRateToBase; }
	public function setExchangeRateToBase(string $exchangeRateToBase): self { $this->exchangeRateToBase = $exchangeRateToBase; return $this; }
	public function getStore(): ?Store { return $this->store; }
	public function setStore(?Store $store): self { $this->store = $store; return $this; }
	public function getOrder(): ?Order { return $this->order; }
	public function setOrder(?Order $order): self { $this->order = $order; return $this; }
	public function getPurchase(): ?Purchase { return $this->purchase; }
	public function setPurchase(?Purchase $purchase): self { $this->purchase = $purchase; return $this; }
	public function getCreatedBy(): ?User { return $this->createdBy; }
	public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
	public function getUpdatedBy(): ?User { return $this->updatedBy; }
	public function setUpdatedBy(?User $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }
	public function getReversesPayment(): ?self { return $this->reversesPayment; }
	public function setReversesPayment(?self $reversesPayment): self { $this->reversesPayment = $reversesPayment; return $this; }
	public function getReversedByPayment(): ?self { return $this->reversedByPayment; }
	public function setReversedByPayment(?self $reversedByPayment): self { $this->reversedByPayment = $reversedByPayment; return $this; }
	public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
	public function setCreatedAt(DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
	public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
	public function setUpdatedAt(DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
