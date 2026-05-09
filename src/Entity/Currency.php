<?php

namespace App\Entity;

use App\Enum\ActiveStatusEnum;
use App\Repository\CurrencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
class Currency
{
	#[ORM\Id]
	#[ORM\Column(length: 3)]
	private ?string $code = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column(length: 10)]
	private ?string $symbol = null;

	#[ORM\Column]
	private ?int $decimalPlaces = null;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	#[ORM\OneToMany(mappedBy: 'baseCurrency', targetEntity: Store::class)]
	private Collection $stores;

	public function __construct()
	{
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->stores = new ArrayCollection();
	}

	public function __toString(): string
	{
		return (string)$this->code;
	}

	public function getCode(): ?string
	{
		return $this->code;
	}

	public function setCode(string $code): self
	{
		$this->code = $code;

		return $this;
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

	public function getSymbol(): ?string
	{
		return $this->symbol;
	}

	public function setSymbol(string $symbol): self
	{
		$this->symbol = $symbol;

		return $this;
	}

	public function getDecimalPlaces(): ?int
	{
		return $this->decimalPlaces;
	}

	public function setDecimalPlaces(int $decimalPlaces): self
	{
		$this->decimalPlaces = $decimalPlaces;

		return $this;
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

	/**
	 * @return Collection<int, Store>
	 */
	public function getStores(): Collection
	{
		return $this->stores;
	}

	public function addStore(Store $store): self
	{
		if (!$this->stores->contains($store)) {
			$this->stores->add($store);
			$store->setBaseCurrency($this);
		}

		return $this;
	}

	public function removeStore(Store $store): self
	{
		if ($this->stores->removeElement($store) && $store->getBaseCurrency() === $this) {
			$store->setBaseCurrency(null);
		}

		return $this;
	}
}
