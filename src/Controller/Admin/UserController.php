<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\FilterType\UserFilterType;
use App\Form\Admin\Type\ProfileType;
use App\Form\Admin\Type\UserType;
use App\Manager\UserManager;
use App\Security\Voter\UserVoter;
use App\Service\FilterFormHandler;
use App\Tools\AbstractAdvancedController;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/user', name: 'admin_user_'), IsGranted('ROLE_STORE_MANAGER')]
class UserController extends AbstractAdvancedController
{
	public function __construct(private readonly UserManager $userManager)
	{
	}

	#[Route('/profile', name: 'profile', methods: ['GET', 'POST'])]
	public function profile(Request $request): Response
	{
		/** @var User $user */
		$user = $this->getUser();
		$form = $this->createForm(ProfileType::class, $user, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			if ($avatar = $form->get('avatar')->getData()) $this->userManager->updateAvatar($user, $avatar);
			if ($password = $form->get('password')->getData()) $this->userManager->updatePassword($user, $password);
			$this->userManager->save($user);

			return $this->redirectToRoute('admin_user_profile');
		}

		return $this->renderForm('admin/user/profile_form.html.twig', [
			'entity' => $user,
			'form' => $form,
			'avatar' => $this->userManager->getAvatar($user)?->getPathname()
		]);
	}

	#[Route('/{user_id}/info', name: 'info', methods: ['GET'])]
	public function info(): Response
	{
		$user = $this->getUser();
		return $this->renderForm('admin/user/info.html.twig', [
			'entity' => $user,
			'avatar' => $this->userManager->getAvatar($user)?->getPathname()
		]);
	}

	#[Route('/', name: 'index')]
	public function index(PaginatorInterface $paginator, Request $request, FilterFormHandler $filterTypeHandler): Response
	{
		$queryBuilder = $this->userManager
			->getRepository()
			->findUsersToShowQB($this->getUser());

		$filterForm = $this->createForm(UserFilterType::class);
		$filterForm->handleRequest($request);

		if ($filterForm->isSubmitted() && $filterForm->isValid()) {
			$filterTypeHandler->handleFilterForm($filterForm, $queryBuilder);
		}

		$page = $request->query->getInt('page', 1);

		if ($page < 1) return $this->redirectToFirstPage();

		$pagination = $paginator->paginate($queryBuilder, $page, options: [
			'defaultSortFieldName' => ['user.nickname'],
			'defaultSortDirection' => 'desc',
		]);

		if ($pagination->count() === 0 && $pagination->getTotalItemCount() > 0) {
			return $this->redirectToLastPage($pagination);
		}

		$entity = ($entityId = $request->get('user_id'))
			? $this->userManager->getRepository()->find($entityId)
			: $pagination->current();

		return $this->render('admin/user/index.html.twig', [
			'pagination' => $pagination,
			'first_entity' => $entity,
			'filter_form' => $filterForm->createView()
		]);
	}

	#[Route('/{user_id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[Entity('user', expr: 'repository.find(user_id)')]
	public function edit(Request $request, User $user): Response
	{
		$this->denyAccessUnlessGranted(UserVoter::EDIT, $user);
		$form = $this->createForm(UserType::class, $user, [
			'method' => 'POST',
			'attr' => [
				'data-controller' => 'select-two',
				'data-select-two-target' => 'form'
			]
		]);

		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			if ($avatar = $form->get('avatar')->getData()) $this->userManager->updateAvatar($user, $avatar);
			$this->userManager->save($user);

			return $this->stayOrRedirect('admin_user_index');
		}

		return $this->renderForm('admin/user/form.html.twig', [
			'entity' => $user,
			'form' => $form,
			'avatar' => $this->userManager->getAvatar($user)?->getPathname()
		]);
	}

	#[Route('/{user_id}/show', name: 'show', methods: ['GET'])]
	#[Entity('user', expr: 'repository.find(user_id)')]
	public function show(Request $request, User $user): Response
	{
		$this->denyAccessUnlessGranted(UserVoter::VIEW, $user);
		return $this->renderForm('admin/user/show.html.twig', [
			'entity' => $user,
			'avatar' => $this->userManager->getAvatar($user)?->getPathname(),
			'query_params' => $request->query->all()
		]);
	}
}
