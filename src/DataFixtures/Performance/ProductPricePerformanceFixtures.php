<?php

namespace App\DataFixtures\Performance;

use App\Entity\Product;
use App\Entity\ProductPrice;
use App\Enum\ProductPriceTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ProductPricePerformanceFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
	private const DEFAULT_BATCH_SIZE = 500;

	public static function getGroups(): array
	{
		return ['performance'];
	}

	public function getDependencies(): array
	{
		return [
			ProductPerformanceFixtures::class,
		];
	}

	public function load(ObjectManager $manager): void
	{
		$faker = Factory::create();
		$batch = [];
		$batchSize = $this->getPositiveEnvInt('PERFORMANCE_PRODUCT_PRICES_BATCH_SIZE', self::DEFAULT_BATCH_SIZE);

		foreach ($manager->getRepository(Product::class)->findAll() as $product) {
			$store = $product->getStore();
			$currency = $store?->getBaseCurrency();

			if (!$store || !$currency) {
				continue;
			}

			$price = number_format($faker->randomFloat(4, 10, 5000), 4, '.', '');
			$productPrice = (new ProductPrice())
				->setStore($store)
				->setProduct($product)
				->setCurrency($currency)
				->setType(ProductPriceTypeEnum::REGULAR)
				->setPrice($price)
				->setPriceBase($price);

			$product->setBaseSalePrice($price);

			$manager->persist($productPrice);
			$manager->persist($product);
			$batch[] = $productPrice;
			$batch[] = $product;

			if (count($batch) >= $batchSize) {
				$manager->flush();
				$this->detachBatch($manager, $batch);
				$batch = [];
			}
		}

		$manager->flush();
		$this->detachBatch($manager, $batch);
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
