<?php

namespace App\Entity;

use App\Repository\NbuExchangeRateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NbuExchangeRateRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_nbu_exchange_rate_currency_date', columns: ['currency_code', 'date'])]
class NbuExchangeRate
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false)]
	private ?Currency $currency = null;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ?DateTimeImmutable $date = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8)]
	private ?string $rate = null;

	#[ORM\Column(length: 32)]
	private string $source = 'manual';

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
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

	public function getDate(): ?DateTimeImmutable
	{
		return $this->date;
	}

	public function setDate(DateTimeImmutable $date): self
	{
		$this->date = $date;

		return $this;
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

	public function getSource(): string
	{
		return $this->source;
	}

	public function setSource(string $source): self
	{
		$this->source = $source;

		return $this;
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}
}
