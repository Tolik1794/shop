<?php

namespace App\Form\Admin\Type;

use App\Entity\User;
use App\Form\Extension\Core\Type\FlatpickrType;
use App\Manager\UserManager;
use App\Security\RoleEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Validator\Constraints\File;

class ProfileType extends AbstractType
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
			]);

		if (!$user->getNickname()) $builder->add('nickname', TextType::class, [
			'required' => true,
		]);

		$builder
			->add('firstName', TextType::class, [
				'required' => true,
			])
			->add('lastName', TextType::class, [
				'required' => true,
			])
			->add('dateOfBirth', FlatpickrType::class, [
				'required' => true,
				'max_date' => (new \DateTime())->modify('-16 year'),
				'alt_input' => true,
				'alt_format' => 'j F, Y',
				'help' => 'It is necessary for everyone in the company to know when to congratulate you on the day of aging.',
			])
			->add('password', RepeatedType::class, [
				'mapped' => false,
				'type' => PasswordType::class,
				'invalid_message' => 'The password fields must match.',
				'options' => ['attr' => ['class' => 'password-field']],
				'required' => false,
				'first_options' => [
					'label' => 'Password',
				],
				'second_options' => [
					'label' => 'Repeat Password',
				],
				'label' => false,
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
