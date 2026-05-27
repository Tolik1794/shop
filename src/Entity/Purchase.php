<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\PaymentStatusEnum;
use App\Repository\PurchaseRepository;
use App\Workflow\WorkflowSubjectInterface;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
class Purchase implements WorkflowSubjectInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

	#[ORM\Version]
	#[ORM\Column(type: Types::INTEGER)]
	private int $version = 1;

	#[ORM\Column(length: 255)]
	private ?string $number = null;

    #[ORM\Column(length: 128, enumType: PurchaseStatus::class)]
    private PurchaseStatus $status;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(referencedColumnName: 'code', nullable: false)]
	private ?Currency $currency = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8)]
	private ?string $exchangeRateToBase = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $supplierNameSnapshot = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $supplierPhoneSnapshot = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $supplierEmailSnapshot = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $invoiceNumber = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $documentDate = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalAmount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalAmountBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $deliveryCost = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $deliveryCostBase = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $canceledAt = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $paidAmountBase = null;

	#[ORM\Column(length: 255, enumType: PaymentStatusEnum::class)]
	private PaymentStatusEnum $paymentStatus;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $paidAt = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: PurchaseEntry::class)]
    private Collection $purchaseEntries;

    #[ORM\ManyToOne(inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: false)]
    private Store $store;

	#[ORM\ManyToOne(inversedBy: 'purchases')]
	private ?Supplier $supplier = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\ManyToOne]
	private ?User $canceledBy = null;

	#[ORM\OneToMany(mappedBy: 'purchase', targetEntity: Payment::class)]
	private Collection $payments;

	#[ORM\OneToMany(mappedBy: 'purchase', targetEntity: InventoryDocument::class)]
	private Collection $inventoryDocuments;

    public function __construct()
    {
		$this->status = PurchaseStatus::DRAFT;
		$this->exchangeRateToBase = '1.00000000';
		$this->totalAmount = '0.0000';
		$this->totalAmountBase = '0.0000';
		$this->paidAmountBase = '0.0000';
		$this->paymentStatus = PaymentStatusEnum::UNPAID;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->purchaseEntries = new ArrayCollection();
		$this->payments = new ArrayCollection();
		$this->inventoryDocuments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

	public function getVersion(): int
	{
		return $this->version;
	}

    public function getStatus(): PurchaseStatus
    {
        return $this->status;
    }

	public function getNumber(): ?string { return $this->number; }
	public function setNumber(string $number): self { $this->number = $number; return $this; }

    public function setStatus(PurchaseStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

	public function getWorkflowKey(): string
	{
		return 'purchase';
	}

	public function getStatusValue(): string
	{
		return $this->status->value;
	}

	public function setStatusValue(string $status): void
	{
		$this->status = PurchaseStatus::from($status);
	}

	public function getCurrency(): ?Currency { return $this->currency; }
	public function setCurrency(?Currency $currency): self { $this->currency = $currency; return $this; }
	public function getExchangeRateToBase(): ?string { return $this->exchangeRateToBase; }
	public function setExchangeRateToBase(string $exchangeRateToBase): self { $this->exchangeRateToBase = $exchangeRateToBase; return $this; }
	public function getSupplierNameSnapshot(): ?string { return $this->supplierNameSnapshot; }
	public function setSupplierNameSnapshot(?string $supplierNameSnapshot): self { $this->supplierNameSnapshot = $supplierNameSnapshot; return $this; }
	public function getSupplierPhoneSnapshot(): ?string { return $this->supplierPhoneSnapshot; }
	public function setSupplierPhoneSnapshot(?string $supplierPhoneSnapshot): self { $this->supplierPhoneSnapshot = $supplierPhoneSnapshot; return $this; }
	public function getSupplierEmailSnapshot(): ?string { return $this->supplierEmailSnapshot; }
	public function setSupplierEmailSnapshot(?string $supplierEmailSnapshot): self { $this->supplierEmailSnapshot = $supplierEmailSnapshot; return $this; }
	public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
	public function setInvoiceNumber(?string $invoiceNumber): self { $this->invoiceNumber = $invoiceNumber; return $this; }
	public function getDocumentDate(): ?DateTimeImmutable { return $this->documentDate; }
	public function setDocumentDate(?DateTimeImmutable $documentDate): self { $this->documentDate = $documentDate; return $this; }
	public function getComment(): ?string { return $this->comment; }
	public function setComment(?string $comment): self { $this->comment = $comment; return $this; }
	public function getTotalAmount(): ?string { return $this->totalAmount; }
	public function setTotalAmount(string $totalAmount): self { $this->totalAmount = $totalAmount; return $this; }
	public function getTotalAmountBase(): ?string { return $this->totalAmountBase; }
	public function setTotalAmountBase(string $totalAmountBase): self { $this->totalAmountBase = $totalAmountBase; return $this; }
	public function getDeliveryCost(): ?string { return $this->deliveryCost; }
	public function setDeliveryCost(?string $deliveryCost): self { $this->deliveryCost = $deliveryCost; return $this; }
	public function getDeliveryCostBase(): ?string { return $this->deliveryCostBase; }
	public function setDeliveryCostBase(?string $deliveryCostBase): self { $this->deliveryCostBase = $deliveryCostBase; return $this; }
	public function getCanceledAt(): ?DateTimeImmutable { return $this->canceledAt; }
	public function setCanceledAt(?DateTimeImmutable $canceledAt): self { $this->canceledAt = $canceledAt; return $this; }
	public function getPaidAmountBase(): ?string { return $this->paidAmountBase; }
	public function setPaidAmountBase(string $paidAmountBase): self { $this->paidAmountBase = $paidAmountBase; return $this; }
	public function getPaymentStatus(): PaymentStatusEnum { return $this->paymentStatus; }
	public function setPaymentStatus(PaymentStatusEnum $paymentStatus): self { $this->paymentStatus = $paymentStatus; return $this; }
	public function getPaidAt(): ?DateTimeImmutable { return $this->paidAt; }
	public function setPaidAt(?DateTimeImmutable $paidAt): self { $this->paidAt = $paidAt; return $this; }
	public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
	public function setCreatedAt(DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
	public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
	public function setUpdatedAt(DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function getStore(): Store
    {
        return $this->store;
    }

    public function setStore(Store $store): self
    {
        $this->store = $store;

        return $this;
    }

	public function getSupplier(): ?Supplier
	{
		return $this->supplier;
	}

	public function setSupplier(?Supplier $supplier): self
	{
		$this->supplier = $supplier;

		if ($supplier instanceof Supplier) {
			$this->supplierNameSnapshot = $supplier->getName();
			$this->supplierPhoneSnapshot = $supplier->getPhone();
			$this->supplierEmailSnapshot = $supplier->getEmail();
		} else {
			$this->supplierNameSnapshot = null;
			$this->supplierPhoneSnapshot = null;
			$this->supplierEmailSnapshot = null;
		}

		return $this;
	}

	public function getCreatedBy(): ?User { return $this->createdBy; }
	public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
	public function getUpdatedBy(): ?User { return $this->updatedBy; }
	public function setUpdatedBy(?User $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }
	public function getCanceledBy(): ?User { return $this->canceledBy; }
	public function setCanceledBy(?User $canceledBy): self { $this->canceledBy = $canceledBy; return $this; }

    /**
     * @return Collection<int, PurchaseEntry>
     */
    public function getPurchaseEntries(): Collection
    {
        return $this->purchaseEntries;
    }

    public function addPurchaseEntry(PurchaseEntry $purchaseEntry): self
    {
        if (!$this->purchaseEntries->contains($purchaseEntry)) {
            $this->purchaseEntries->add($purchaseEntry);
            $purchaseEntry->setPurchase($this);
        }

        return $this;
    }

    public function removePurchaseEntry(PurchaseEntry $purchaseEntry): self
    {
        if ($this->purchaseEntries->removeElement($purchaseEntry)) {
            // set the owning side to null (unless already changed)
            if ($purchaseEntry->getPurchase() === $this) {
                $purchaseEntry->setPurchase(null);
            }
        }

        return $this;
    }

	/**
	 * @return Collection<int, Payment>
	 */
	public function getPayments(): Collection
	{
		return $this->payments;
	}

	public function addPayment(Payment $payment): self
	{
		if (!$this->payments->contains($payment)) {
			$this->payments->add($payment);
			$payment->setPurchase($this);
		}

		return $this;
	}

	public function removePayment(Payment $payment): self
	{
		if ($this->payments->removeElement($payment) && $payment->getPurchase() === $this) {
			$payment->setPurchase(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, InventoryDocument>
	 */
	public function getInventoryDocuments(): Collection
	{
		return $this->inventoryDocuments;
	}
}
