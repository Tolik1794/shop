<?php

namespace App\DataFixtures\Performance;

use App\DataFixtures\StoreFixtures;
use App\DataFixtures\UnitFixtures;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Store;
use App\Entity\Unit;
use App\Enum\ProductKindEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use RuntimeException;

class ProductPerformanceFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
	private const DEFAULT_PRODUCTS_COUNT = 20000;
	private const DEFAULT_BATCH_SIZE = 500;

	public static function getGroups(): array
	{
		return ['performance'];
	}

	public function getDependencies(): array
	{
		return [
			StoreFixtures::class,
			UnitFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$stores = $manager->getRepository(Store::class)->findAll();
		$categoriesByStore = $this->getCategoriesByStore($manager);
		$unitsByStore = $this->getDefaultUnitsByStore($manager);

		if (!$stores || !$categoriesByStore || !$unitsByStore) {
			throw new RuntimeException('Load StoreFixtures and UnitFixtures before ProductPerformanceFixtures.');
		}

		$faker = Factory::create();
		$count = $this->getPositiveEnvInt('PERFORMANCE_PRODUCTS_COUNT', self::DEFAULT_PRODUCTS_COUNT);
		$batchSize = $this->getPositiveEnvInt('PERFORMANCE_PRODUCTS_BATCH_SIZE', self::DEFAULT_BATCH_SIZE);
		$runId = date('YmdHis').'-'.getmypid();
		$batch = [];

		for ($i = 1; $i <= $count; $i++) {
			$store = $faker->randomElement($stores);
			$storeCategories = $categoriesByStore[$store->getId()] ?? null;

			if (!$storeCategories) {
				continue;
			}

			$unit = $unitsByStore[$store->getId()] ?? null;
			if (!$unit instanceof Unit) {
				continue;
			}

			$product = (new Product())
				->setStore($store)
				->setCategory($faker->randomElement($storeCategories))
				->setProductKind(ProductKindEnum::FINISHED_PRODUCT)
				->setName($faker->words(3, true))
				->setCode(sprintf('PERF-%s-%06d', $runId, $i))
				->setCanBeSold(true)
				->setCanBePurchased(false)
				->setCanBeManufactured(false)
				->setUnit($unit);

			$manager->persist($product);
			$batch[] = $product;

			if ($i % $batchSize === 0) {
				$manager->flush();
				$this->detachBatch($manager, $batch);
				$batch = [];
			}
		}

		$manager->flush();
		$this->detachBatch($manager, $batch);
	}

	private function getCategoriesByStore(ObjectManager $manager): array
	{
		$categoriesByStore = [];

		foreach ($manager->getRepository(Category::class)->findAll() as $category) {
			$store = $category->getStore();

			if (!$store?->getId()) {
				continue;
			}

			$categoriesByStore[$store->getId()][] = $category;
		}

		return $categoriesByStore;
	}

	private function getDefaultUnitsByStore(ObjectManager $manager): array
	{
		$unitsByStore = [];

		foreach ($manager->getRepository(Unit::class)->findBy(['code' => 'pcs']) as $unit) {
			$store = $unit->getStore();

			if (!$store?->getId()) {
				continue;
			}

			$unitsByStore[$store->getId()] = $unit;
		}

		return $unitsByStore;
	}

	private function getPositiveEnvInt(string $name, int $default): int
	{
		$value = getenv($name);

		if ($value === false || $value === '') {
			return $default;
		}

		$value = (int) $value;

		return $value > 0 ? $value : $default;
	}

	private function detachBatch(ObjectManager $manager, array $batch): void
	{
		foreach ($batch as $entity) {
			$manager->detach($entity);
		}
	}
}
