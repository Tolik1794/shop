<?php

namespace App\Tests\Entity;

use App\Entity\ProductParameter;
use App\Entity\ProductParameterName;
use PHPUnit\Framework\TestCase;

class ProductParameterNameTest extends TestCase
{
	public function testAddProductParameterKeepsOwningSideInSync(): void
	{
		$productParameterName = (new ProductParameterName())
			->setName('Size')
			->setDescription('Size');
		$productParameter = new ProductParameter();

		$productParameterName->addProductParameter($productParameter);

		self::assertTrue($productParameterName->getProductParameters()->contains($productParameter));
		self::assertSame($productParameterName, $productParameter->getProductParameterName());
	}
}
