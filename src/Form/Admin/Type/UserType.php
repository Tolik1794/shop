<?php

namespace App\Form\Admin\Type;

use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Manager\UserManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
	public function __construct(private readonly UserManager $userManager)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var User $user */
		$user = $builder->getData();
        $builder
	        ->add('avatar', FileType::class, [
		        'required' => false,
		        'mapped' => false,
		        'data' => $this->userManager->getAvatar($user),
		        'constraints' => [
			        new File([
				        'mimeTypes' => ['image/jpeg', 'image/png'],
			        ]),
		        ],
	        ])
	        ->add('email', EmailType::class, [
				'disabled' => true
            ])
	        ->add('nickname', TextType::class, [
				'disabled' => true,
	        ])
	        ->add('firstName', TextType::class, [
				'disabled' => true,
	        ])
	        ->add('lastName', TextType::class, [
				'disabled' => true,
	        ])
	        ->add('dateOfBirth', DateType::class, [
				'disabled' => true,
	        ])
            ->add('roles', ChoiceType::class, [
				'choices' => [
//					RoleEnum::ROLE_SUPER_ADMIN->value => RoleEnum::ROLE_SUPER_ADMIN->name,
					RoleEnum::ROLE_ADMIN->value => RoleEnum::ROLE_ADMIN->name,
					RoleEnum::ROLE_USER->value => RoleEnum::ROLE_USER->name,
				],
	            'multiple' => true,
	            'disabled' => true,
            ])
            ->add('managerStores', EntityType::class, [
				'choices' => $this->userManager->hasRole(RoleEnum::ROLE_ADMIN)
					? $this->userManager->entityManager->getRepository(Store::class)->findAll()
					: $this->userManager->getCurrentUser()->getManagerStores()->getValues(),
				'class' => Store::class,
				'choice_label' => 'name',
				'multiple' => true,
	            'required' => false,
			])
		;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => User::class,
		]);
	}
}
