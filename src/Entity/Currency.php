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

	#[ORM\OneToMany(mappedBy: 'currency', targetEntity: ProductPrice::class)]
	private Collection $productPrices;

	#[ORM\OneToMany(mappedBy: 'fromCurrency', targetEntity: ExchangeRate::class)]
	private Collection $exchangeRatesFrom;

	#[ORM\OneToMany(mappedBy: 'toCurrency', targetEntity: ExchangeRate::class)]
	private Collection $exchangeRatesTo;

	public function __construct()
	{
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->stores = new ArrayCollection();
		$this->productPrices = new ArrayCollection();
		$this->exchangeRatesFrom = new ArrayCollection();
		$this->exchangeRatesTo = new ArrayCollection();
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

	/**
	 * @return Collection<int, ProductPrice>
	 */
	public function getProductPrices(): Collection
	{
		return $this->productPrices;
	}

	public function addProductPrice(ProductPrice $productPrice): self
	{
		if (!$this->productPrices->contains($productPrice)) {
			$this->productPrices->add($productPrice);
			$productPrice->setCurrency($this);
		}

		return $this;
	}

	public function removeProductPrice(ProductPrice $productPrice): self
	{
		if ($this->productPrices->removeElement($productPrice) && $productPrice->getCurrency() === $this) {
			$productPrice->setCurrency(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, ExchangeRate>
	 */
	public function getExchangeRatesFrom(): Collection
	{
		return $this->exchangeRatesFrom;
	}

	public function addExchangeRatesFrom(ExchangeRate $exchangeRate): self
	{
		if (!$this->exchangeRatesFrom->contains($exchangeRate)) {
			$this->exchangeRatesFrom->add($exchangeRate);
			$exchangeRate->setFromCurrency($this);
		}

		return $this;
	}

	public function removeExchangeRatesFrom(ExchangeRate $exchangeRate): self
	{
		if ($this->exchangeRatesFrom->removeElement($exchangeRate) && $exchangeRate->getFromCurrency() === $this) {
			$exchangeRate->setFromCurrency(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, ExchangeRate>
	 */
	public function getExchangeRatesTo(): Collection
	{
		return $this->exchangeRatesTo;
	}

	public function addExchangeRatesTo(ExchangeRate $exchangeRate): self
	{
		if (!$this->exchangeRatesTo->contains($exchangeRate)) {
			$this->exchangeRatesTo->add($exchangeRate);
			$exchangeRate->setToCurrency($this);
		}

		return $this;
	}

	public function removeExchangeRatesTo(ExchangeRate $exchangeRate): self
	{
		if ($this->exchangeRatesTo->removeElement($exchangeRate) && $exchangeRate->getToCurrency() === $this) {
			$exchangeRate->setToCurrency(null);
		}

		return $this;
	}
}
