<?php

namespace App\Form\Admin\Type;

use App\Entity\Permission;
use App\Entity\Store;
use App\Entity\User\User;
use App\Entity\User\UserGroup;
use App\Enum\PermissionOverrideEffect;
use App\Form\Extension\Core\Type\FlatpickrType;
use App\Manager\UserManager;
use App\Repository\PermissionRepository;
use App\Security\PermissionChecker;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
	public function __construct(
		private readonly UserManager $userManager,
		private readonly PermissionChecker $permissionChecker,
		private readonly PermissionRepository $permissionRepository,
	)
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
	        ->add('dateOfBirth', FlatpickrType::class, [
		        'alt_input' => true,
		        'alt_format' => 'j F, Y',
				'disabled' => true,
	        ])
            ->add('groups', EntityType::class, [
				'class' => UserGroup::class,
				'choice_label' => 'name',
				'multiple' => true,
	            'required' => false,
	            'attr' => ['class' => 'select2'],
	            'label' => 'Groups',
            ])
	        ->add('allowedPermissions', EntityType::class, [
		        'class' => Permission::class,
		        'choices' => $this->permissionRepository->findBy([], ['category' => 'ASC', 'sortOrder' => 'ASC', 'code' => 'ASC']),
		        'choice_label' => static fn (Permission $permission): string => sprintf('%s - %s', $permission->getCode(), $permission->getName()),
		        'multiple' => true,
		        'mapped' => false,
		        'required' => false,
		        'data' => $this->overridePermissions($user, PermissionOverrideEffect::ALLOW),
		        'attr' => ['class' => 'select2'],
		        'label' => 'Individual allowed permissions',
		        'help' => 'These permissions are allowed for this user in addition to group permissions.',
	        ])
	        ->add('deniedPermissions', EntityType::class, [
		        'class' => Permission::class,
		        'choices' => $this->permissionRepository->findBy([], ['category' => 'ASC', 'sortOrder' => 'ASC', 'code' => 'ASC']),
		        'choice_label' => static fn (Permission $permission): string => sprintf('%s - %s', $permission->getCode(), $permission->getName()),
		        'multiple' => true,
		        'mapped' => false,
		        'required' => false,
		        'data' => $this->overridePermissions($user, PermissionOverrideEffect::DENY),
		        'attr' => ['class' => 'select2'],
		        'label' => 'Individual denied permissions',
		        'help' => 'Denied permissions override both group and individual allowed permissions.',
	        ])
            ->add('managerStores', EntityType::class, [
				'choices' => $this->permissionChecker->isGranted($this->userManager->getCurrentUser(), 'store.view_all')
					? $this->userManager->getEntityManager()->getRepository(Store::class)->findAll()
					: $this->userManager->getCurrentUser()->getManagerStores()->getValues(),
				'class' => Store::class,
				'choice_label' => 'name',
				'multiple' => true,
	            'required' => false,
	            'attr' => ['class' => 'select2'],
			])
		;
	}

	private function overridePermissions(User $user, PermissionOverrideEffect $effect): array
	{
		$permissions = [];

		foreach ($user->getPermissionOverrides() as $override) {
			if ($override->getEffect() === $effect) {
				$permissions[] = $override->getPermission();
			}
		}

		return $permissions;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => User::class,
		]);
	}
}
