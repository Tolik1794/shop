<?php

namespace App\Form\Admin\Type;

use App\Entity\Permission;
use App\Entity\User\UserGroup;
use App\Repository\PermissionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserGroupType extends AbstractType
{
	public function __construct(private readonly PermissionRepository $permissionRepository)
	{
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void
	{
		/** @var UserGroup|null $group */
		$group = $builder->getData();

		$builder
			->add('name', TextType::class, [
				'label' => 'Name',
			])
			->add('code', TextType::class, [
				'label' => 'Code',
				'disabled' => $group?->isSystem() ?? false,
				'help' => 'Use stable snake_case codes for custom groups.',
			])
			->add('description', TextareaType::class, [
				'label' => 'Description',
				'required' => false,
			])
			->add('permissions', EntityType::class, [
				'class' => Permission::class,
				'choices' => $this->permissionRepository->findBy([], ['category' => 'ASC', 'sortOrder' => 'ASC', 'code' => 'ASC']),
				'choice_label' => static fn (Permission $permission): string => sprintf('%s - %s', $permission->getCode(), $permission->getName()),
				'multiple' => true,
				'required' => false,
				'attr' => ['class' => 'select2'],
				'label' => 'Permissions',
			])
		;
	}

	public function configureOptions(OptionsResolver $resolver): void
	{
		$resolver->setDefaults([
			'data_class' => UserGroup::class,
		]);
	}
}
