<?php

namespace App\Entity;

use App\Repository\ExchangeRateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
class ExchangeRate
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8)]
	private ?string $rate = null;

	#[ORM\Column]
	private DateTimeImmutable $validFrom;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $validTo = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $source = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\ManyToOne(inversedBy: 'exchangeRatesFrom')]
	#[ORM\JoinColumn(name: 'from_currency_code', referencedColumnName: 'code', nullable: false)]
	private ?Currency $fromCurrency = null;

	#[ORM\ManyToOne(inversedBy: 'exchangeRatesTo')]
	#[ORM\JoinColumn(name: 'to_currency_code', referencedColumnName: 'code', nullable: false)]
	private ?Currency $toCurrency = null;

	#[ORM\ManyToOne(inversedBy: 'exchangeRates')]
	private ?Store $store = null;

	public function __construct()
	{
		$this->validFrom = new DateTimeImmutable();
		$this->createdAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getRate(): ?string
	{
		return $this->rate;
	}

	public function setRate(string $rate): self
	{
		$this->rate = $rate;

		return $this;
	}

	public function getValidFrom(): DateTimeImmutable
	{
		return $this->validFrom;
	}

	public function setValidFrom(DateTimeImmutable $validFrom): self
	{
		$this->validFrom = $validFrom;

		return $this;
	}

	public function getValidTo(): ?DateTimeImmutable
	{
		return $this->validTo;
	}

	public function setValidTo(?DateTimeImmutable $validTo): self
	{
		$this->validTo = $validTo;

		return $this;
	}

	public function getSource(): ?string
	{
		return $this->source;
	}

	public function setSource(?string $source): self
	{
		$this->source = $source;

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

	public function setCreatedAt(DateTimeImmutable $createdAt): self
	{
		$this->createdAt = $createdAt;

		return $this;
	}

	public function getFromCurrency(): ?Currency
	{
		return $this->fromCurrency;
	}

	public function setFromCurrency(?Currency $fromCurrency): self
	{
		$this->fromCurrency = $fromCurrency;

		return $this;
	}

	public function getToCurrency(): ?Currency
	{
		return $this->toCurrency;
	}

	public function setToCurrency(?Currency $toCurrency): self
	{
		$this->toCurrency = $toCurrency;

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
}
