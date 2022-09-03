<?php

namespace App\Form\Admin\Type;

use App\Entity\Store;
use App\Enum\ActiveStatusEnum;
use App\Manager\StoreManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class StoreType extends AbstractType
{
	public function __construct(private readonly StoreManager $storeManager)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
		/** @var Store $store */
		$store = $builder->getData();
        $builder
	        ->add('avatar', FileType::class, [
		        'required' => false,
		        'mapped' => false,
		        'data' => $this->storeManager->getAvatar($store),
		        'constraints' => [
			        new File([
				        'mimeTypes' => ['image/jpeg', 'image/png'],
			        ]),
		        ],
	        ])
	        ->add('name')
	        ->add('slug')
	        ->add('phone')
	        ->add('email')
	        ->add('description')
            ->add('status', EnumType::class, [
				'class' => ActiveStatusEnum::class
            ])
	        ->setMethod($options['method'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Store::class,
        ]);
    }
}
