<?php

namespace App\Form\Admin\Type;

use App\Entity\Product;
use App\Entity\ProductRelation;
use App\Entity\Store;
use App\Enum\ProductRelationTypeEnum;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductRelationType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var Store $store */
		$store = $options['store'];
		/** @var Product|null $excludeProduct */
		$excludeProduct = $options['exclude_product'];

		$builder
			->add('relatedProduct', EntityType::class, [
				'class' => Product::class,
				'query_builder' => function (ProductRepository $repository) use ($store, $excludeProduct) {
					$qb = $repository->findAvailableByStoreQB($store)
						->orderBy('product.name', 'ASC');

					if ($excludeProduct?->getId()) {
						$qb->andWhere('product.id != :excludeProduct')
							->setParameter('excludeProduct', $excludeProduct->getId());
					}

					return $qb;
				},
				'choice_label' => fn(Product $product): string => sprintf('%s (%s)', $product->getName(), $product->getCode()),
				'placeholder' => 'admin.product.relations.related_product',
				'required' => true,
				'attr' => ['class' => 'select2'],
			])
			->add('type', EnumType::class, [
				'class' => ProductRelationTypeEnum::class,
				'choice_label' => fn(ProductRelationTypeEnum $choice): string => $choice->label(),
				'required' => true,
			])
		;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => ProductRelation::class,
			'exclude_product' => null,
		]);
		$resolver->setRequired('store');
		$resolver->setAllowedTypes('store', Store::class);
		$resolver->setAllowedTypes('exclude_product', ['null', Product::class]);
	}
}
