<?php

namespace App\Form\Admin\Type;

use App\Entity\User;
use App\Manager\UserManager;
use App\Security\RoleEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
	public function __construct(private readonly UserManager $userManager)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
//            ->add('roles', ChoiceType::class, [
//				'choices' => [
////					RoleEnum::ROLE_SUPER_ADMIN->value => RoleEnum::ROLE_SUPER_ADMIN->name,
//					RoleEnum::ROLE_ADMIN->value => RoleEnum::ROLE_ADMIN->name,
//					RoleEnum::ROLE_USER->value => RoleEnum::ROLE_USER->name,
//				],
//	            'multiple' => true
//            ])
            ->add('password', RepeatedType::class, [
		        'type' => PasswordType::class,
		        'invalid_message' => 'The password fields must match.',
		        'options' => ['attr' => ['class' => 'password-field']],
		        'required' => true,
		        'first_options'  => ['label' => 'Password'],
		        'second_options' => ['label' => 'Repeat Password'],
	        ])
//            ->add('isVerified')
//            ->add('stores')
//            ->add('store')
//            ->add('managerStores')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
