<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use App\Repository\LegalEntityRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LegalEntityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_legal_entity_tax_number', columns: ['tax_number'], options: ['where' => '(deleted_at IS NULL)'])]
class LegalEntity
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $shortName = null;

	#[ORM\Column(length: 255, enumType: LegalEntityTypeEnum::class)]
	private LegalEntityTypeEnum $type;

	#[ORM\Column(length: 32)]
	private ?string $taxNumber = null;

	#[ORM\Column(length: 255, enumType: TaxSystemEnum::class)]
	private TaxSystemEnum $taxSystem;

	#[ORM\Column(nullable: true)]
	private ?int $epGroup = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
	private ?string $epRate = null;

	#[ORM\Column]
	private bool $vatPayer = false;

	#[ORM\Column]
	private bool $esvExempt = false;

	#[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
	private ?DateTimeImmutable $registeredAt = null;

	#[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
	private ?DateTimeImmutable $simplifiedSince = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $address = null;

	#[ORM\Column(type: Types::JSON, nullable: true)]
	private ?array $kveds = null;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $deletedAt = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\OneToMany(mappedBy: 'legalEntity', targetEntity: Store::class)]
	private Collection $stores;

	public function __construct()
	{
		$this->type = LegalEntityTypeEnum::FOP;
		$this->taxSystem = TaxSystemEnum::SIMPLIFIED;
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->stores = new ArrayCollection();
	}

	public function __toString(): string
	{
		return (string) ($this->shortName ?: $this->name);
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;

		return $this;
	}

	public function getShortName(): ?string
	{
		return $this->shortName;
	}

	public function setShortName(?string $shortName): self
	{
		$this->shortName = $shortName;

		return $this;
	}

	public function getType(): LegalEntityTypeEnum
	{
		return $this->type;
	}

	public function setType(LegalEntityTypeEnum $type): self
	{
		$this->type = $type;

		return $this;
	}

	public function getTaxNumber(): ?string
	{
		return $this->taxNumber;
	}

	public function setTaxNumber(string $taxNumber): self
	{
		$this->taxNumber = $taxNumber;

		return $this;
	}

	public function getTaxSystem(): TaxSystemEnum
	{
		return $this->taxSystem;
	}

	public function setTaxSystem(TaxSystemEnum $taxSystem): self
	{
		$this->taxSystem = $taxSystem;

		return $this;
	}

	public function getEpGroup(): ?int
	{
		return $this->epGroup;
	}

	public function setEpGroup(?int $epGroup): self
	{
		$this->epGroup = $epGroup;

		return $this;
	}

	public function getEpRate(): ?string
	{
		return $this->epRate;
	}

	public function setEpRate(?string $epRate): self
	{
		$this->epRate = $epRate;

		return $this;
	}

	public function isVatPayer(): bool
	{
		return $this->vatPayer;
	}

	public function setVatPayer(bool $vatPayer): self
	{
		$this->vatPayer = $vatPayer;

		return $this;
	}

	public function isEsvExempt(): bool
	{
		return $this->esvExempt;
	}

	public function setEsvExempt(bool $esvExempt): self
	{
		$this->esvExempt = $esvExempt;

		return $this;
	}

	public function getRegisteredAt(): ?DateTimeImmutable
	{
		return $this->registeredAt;
	}

	public function setRegisteredAt(?DateTimeImmutable $registeredAt): self
	{
		$this->registeredAt = $registeredAt;

		return $this;
	}

	public function getSimplifiedSince(): ?DateTimeImmutable
	{
		return $this->simplifiedSince;
	}

	public function setSimplifiedSince(?DateTimeImmutable $simplifiedSince): self
	{
		$this->simplifiedSince = $simplifiedSince;

		return $this;
	}

	public function getAddress(): ?string
	{
		return $this->address;
	}

	public function setAddress(?string $address): self
	{
		$this->address = $address;

		return $this;
	}

	public function getKveds(): ?array
	{
		return $this->kveds;
	}

	public function setKveds(?array $kveds): self
	{
		$this->kveds = $kveds;

		return $this;
	}

	public function getKvedsAsString(): string
	{
		return implode(', ', $this->kveds ?? []);
	}

	public function getStatus(): ActiveStatusEnum
	{
		return $this->status;
	}

	public function setStatus(ActiveStatusEnum $status): self
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

	public function getDeletedAt(): ?DateTimeImmutable
	{
		return $this->deletedAt;
	}

	public function setDeletedAt(?DateTimeImmutable $deletedAt): self
	{
		$this->deletedAt = $deletedAt;

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

	/**
	 * @return Collection<int, Store>
	 */
	public function getStores(): Collection
	{
		return $this->stores;
	}
}
