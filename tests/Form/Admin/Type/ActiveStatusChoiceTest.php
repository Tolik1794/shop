<?php

namespace App\Tests\Form\Admin\Type;

use App\Entity\Supplier;
use App\Enum\ActiveStatusEnum;
use App\Form\Admin\FilterType\SupplierFilterType;
use App\Form\Admin\Type\SupplierType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class ActiveStatusChoiceTest extends KernelTestCase
{
	private FormFactoryInterface $formFactory;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		unset($this->formFactory);
	}

	public function testReferenceStatusTypeDoesNotOfferDeletedStatus(): void
	{
		$form = $this->formFactory->create(SupplierType::class, new Supplier());

		self::assertSame([
			ActiveStatusEnum::ACTIVE->value,
			ActiveStatusEnum::INACTIVE->value,
		], $this->statusChoiceValues($form->createView()->children['status']->vars['choices']));
	}

	public function testReferenceStatusFilterDoesNotOfferDeletedStatus(): void
	{
		$form = $this->formFactory->create(SupplierFilterType::class);

		self::assertSame([
			ActiveStatusEnum::ACTIVE->value,
			ActiveStatusEnum::INACTIVE->value,
		], $this->statusChoiceValues($form->createView()->children['status']->vars['choices']));
	}

	/**
	 * @param iterable<object> $choices
	 *
	 * @return string[]
	 */
	private function statusChoiceValues(iterable $choices): array
	{
		$values = [];

		foreach ($choices as $choice) {
			$values[] = $choice->value;
		}

		return $values;
	}
}
