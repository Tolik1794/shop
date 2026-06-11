<?php

namespace App\Tests\Controller;

use App\Entity\LegalEntity;
use App\Entity\TaxAccrual;
use App\Entity\TaxRateSet;
use App\Entity\User\RoleEnum;
use App\Entity\User\User;
use App\Enum\LegalEntityTypeEnum;
use App\Enum\TaxSystemEnum;
use App\Service\Tax\TaxAccrualGeneratorService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaxAccrualControllerTest extends WebTestCase
{
	private KernelBrowser $client;
	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		$this->client = static::createClient();
		$this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
	}

	public function testIndexRequiresAuthentication(): void
	{
		$this->client->request('GET', '/admin/tax/accruals/');

		self::assertResponseRedirects('/login');
	}

	public function testIndexShowsEmptyStateBeforeGeneration(): void
	{
		$this->client->loginUser($this->createUser('accrual-idx-' . uniqid() . '@example.com'));

		$this->client->request('GET', '/admin/tax/accruals/?year=2026');

		self::assertResponseIsSuccessful();
	}

	public function testGenerateCreatesAccrualsForGroup3(): void
	{
		$this->client->loginUser($this->createUser('accrual-gen-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$generator = static::getContainer()->get(TaxAccrualGeneratorService::class);
		$report = $generator->generate($entity, 2026, apply: true);

		self::assertSame(0, count($report['errors']));
		self::assertGreaterThan(0, $report['created']);

		$accruals = $this->entityManager->getRepository(TaxAccrual::class)->findBy(['legalEntity' => $entity]);

		self::assertNotEmpty($accruals);
		// Group 3: 4 quarters × 3 tax types (EP, ESV, VZ) = 12
		self::assertCount(12, $accruals);
	}

	public function testGenerateCreatesCorrectTaxTypesForGroup1(): void
	{
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(1, null);

		$generator = static::getContainer()->get(TaxAccrualGeneratorService::class);
		$generator->generate($entity, 2026, apply: true);

		$accruals = $this->entityManager->getRepository(TaxAccrual::class)->findBy(['legalEntity' => $entity]);

		// Group 1: 12 monthly (EP + VZ) + 4 quarterly (ESV) = 12*2 + 4 = 28
		self::assertCount(28, $accruals);
	}

	public function testMarkPaidChangesStatus(): void
	{
		$this->client->loginUser($this->createUser('accrual-pay-' . uniqid() . '@example.com'));
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$generator = static::getContainer()->get(TaxAccrualGeneratorService::class);
		$generator->generate($entity, 2026, apply: true);

		$accrual = $this->entityManager->getRepository(TaxAccrual::class)->findOneBy(['legalEntity' => $entity]);
		self::assertInstanceOf(TaxAccrual::class, $accrual);

		// Get CSRF token from the rendered index page (inside the mark-paid modal form)
		$crawler = $this->client->request('GET', sprintf('/admin/tax/accruals/?year=2026&entity=%d', $entity->getId()));
		self::assertResponseIsSuccessful();

		$actionPath = sprintf('/admin/tax/accruals/%d/mark-paid', $accrual->getId());
		$tokenInput = $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $actionPath));
		$token = $tokenInput->count() > 0 ? $tokenInput->attr('value') : 'invalid';

		$this->client->request('POST', sprintf('/admin/tax/accruals/%d/mark-paid', $accrual->getId()), [
			'_token'      => $token,
			'paid_at'     => '2026-04-15',
			'paid_amount' => '5000.00',
		]);

		// AdminTurboResponseSubscriber converts POST redirects to 303
		self::assertResponseStatusCodeSame(303);

		$this->entityManager->clear();
		$updated = $this->entityManager->getRepository(TaxAccrual::class)->find($accrual->getId());

		self::assertSame('paid', $updated?->getStatus()->value);
		self::assertSame('2026-04-15', $updated?->getPaidAt()?->format('Y-m-d'));
	}

	public function testGenerateIsIdempotent(): void
	{
		$this->persistRateSet(2026);
		$entity = $this->persistLegalEntity(3, '5.00');

		$generator = static::getContainer()->get(TaxAccrualGeneratorService::class);
		$generator->generate($entity, 2026, apply: true);
		$generator->generate($entity, 2026, apply: true);

		$accruals = $this->entityManager->getRepository(TaxAccrual::class)->findBy(['legalEntity' => $entity]);
		self::assertCount(12, $accruals);
	}

	public function testNoRateSetSkipsGeneration(): void
	{
		$entity = $this->persistLegalEntity(3, '5.00');

		$generator = static::getContainer()->get(TaxAccrualGeneratorService::class);
		$report = $generator->generate($entity, 1999, apply: true);

		self::assertNotEmpty($report['errors']);
		self::assertSame(0, $report['created']);
	}

	private function createUser(string $email): User
	{
		$user = (new User())
			->setEmail($email)
			->setNickname(str_replace(['@', '.'], '-', $email))
			->setFirstName('Admin')
			->setLastName('User')
			->setDateOfBirth(new DateTime('1990-01-01'))
			->setPassword('password')
			->setRoles([RoleEnum::ROLE_SUPER_ADMIN->name]);

		$this->entityManager->persist($user);
		$this->entityManager->flush();

		return $user;
	}

	private function persistLegalEntity(?int $epGroup, ?string $epRate): LegalEntity
	{
		$entity = (new LegalEntity())
			->setName('ФОП Нарахування ' . uniqid())
			->setType(LegalEntityTypeEnum::FOP)
			->setTaxNumber(substr((string) random_int(1000000000, 9999999999), 0, 10))
			->setTaxSystem(TaxSystemEnum::SIMPLIFIED)
			->setEpGroup($epGroup)
			->setEpRate($epRate);

		$this->entityManager->persist($entity);
		$this->entityManager->flush();

		return $entity;
	}

	private function persistRateSet(int $year): TaxRateSet
	{
		$existing = $this->entityManager->getRepository(TaxRateSet::class)->findOneBy(['year' => $year]);
		if ($existing instanceof TaxRateSet) {
			return $existing;
		}

		$rateSet = (new TaxRateSet())
			->setYear($year)
			->setMinimumWage('8647.0000')
			->setSubsistenceMinimum('3328.0000')
			->setGroup1IncomeLimit('1444049.0000')
			->setGroup2IncomeLimit('7211598.0000')
			->setGroup3IncomeLimit('10091049.0000')
			->setGroup1EpMonthly('332.8000')
			->setGroup2EpMonthly('1729.4000')
			->setGroup3EpRatePct('5.00')
			->setGroup3EpRateVatPct('3.00')
			->setEsvRatePct('22.00')
			->setEsvMonthlyMin('1902.3400')
			->setVzGroup12Monthly('864.7000')
			->setVzGroup3RatePct('1.00');

		$this->entityManager->persist($rateSet);
		$this->entityManager->flush();

		return $rateSet;
	}
}
