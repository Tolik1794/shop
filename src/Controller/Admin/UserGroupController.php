<?php

namespace App\Controller\Admin;

use App\Entity\User\UserGroup;
use App\Form\Admin\Type\UserGroupType;
use App\Repository\UserGroupRepository;
use App\Tools\AbstractAdvancedController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/access/group', name: 'admin_user_group_'), IsGranted('rbac.view')]
class UserGroupController extends AbstractAdvancedController
{
	public function __construct(
		private readonly EntityManagerInterface $entityManager,
		private readonly UserGroupRepository $userGroupRepository,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(): Response
	{
		return $this->render('admin/user_group/index.html.twig', [
			'groups' => $this->userGroupRepository->findBy([], ['system' => 'DESC', 'name' => 'ASC']),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	#[IsGranted('rbac.manage')]
	public function new(Request $request): Response
	{
		$group = new UserGroup();
		$form = $this->createForm(UserGroupType::class, $group, [
			'method' => 'POST',
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->entityManager->persist($group);
			$this->entityManager->flush();

			return $this->redirectToRoute('admin_user_group_index');
		}

		return $this->render('admin/user_group/form.html.twig', [
			'entity' => $group,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[IsGranted('rbac.manage')]
	public function edit(Request $request, UserGroup $group): Response
	{
		$form = $this->createForm(UserGroupType::class, $group, [
			'method' => 'POST',
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form',
			],
		]);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$group->touch();
			$this->entityManager->flush();

			return $this->stayOrRedirect('admin_user_group_index');
		}

		return $this->render('admin/user_group/form.html.twig', [
			'entity' => $group,
			'form' => $form,
		]);
	}
}
