<?php

namespace App\DataFixtures;

use App\Entity\ProductParameterName;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductParameterNameFixtures extends Fixture
{
	public const PARAMETER_REFERENCES = [
		'width_mm',
		'height_mm',
		'depth_mm',
		'length_mm',
		'thickness_mm',
		'material',
		'finish',
		'color',
		'weight_kg',
		'style',
		'seat_count',
		'bed_size',
		'storage',
		'grade',
		'application',
	];

	public function load(ObjectManager $manager): void
	{
		$parameters = [
			'width_mm' => 'Product or material width in millimeters.',
			'height_mm' => 'Product height in millimeters.',
			'depth_mm' => 'Product depth in millimeters.',
			'length_mm' => 'Material length in millimeters.',
			'thickness_mm' => 'Board, panel or edging thickness in millimeters.',
			'material' => 'Primary material or material composition.',
			'finish' => 'Surface finish or coating.',
			'color' => 'Primary visible color.',
			'weight_kg' => 'Approximate product or package weight in kilograms.',
			'style' => 'Furniture style for catalog filtering.',
			'seat_count' => 'Number of seats for seating products.',
			'bed_size' => 'Mattress or sleeping area size.',
			'storage' => 'Storage configuration or capacity.',
			'grade' => 'Material grade or quality class.',
			'application' => 'Typical manufacturing application.',
		];

		foreach ($parameters as $name => $description) {
			$parameter = (new ProductParameterName())
				->setName($name)
				->setDescription($description);

			$manager->persist($parameter);
			$this->addReference('parameter.' . $name, $parameter);
		}

		$manager->flush();
	}
}
