<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\Type\UserType;
use App\Manager\UserManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/user', name: 'admin_user_'), IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
	public function __construct(private readonly UserManager $userManager)
	{
	}

	#[Route('/profile', name: 'profile', methods: ['GET', 'POST'])]
	public function profile(Request $request): Response
	{
		/** @var User $user */
		$user = $this->getUser();
		$form = $this->createForm(UserType::class, $user, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			if ($avatar = $form->get('avatar')->getData()) $this->userManager->updateAvatar($user, $avatar);
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
	#[Entity('user', expr: 'repository.find(user_id)')]
	public function info(User $user)
	{
		return $this->renderForm('admin/user/info.html.twig', [
			'entity' => $user,
			'avatar' => $this->userManager->getAvatar($user)?->getPathname()
		]);
	}

	#[Route('/', name: 'index')]
	public function index(): Response
	{
		return $this->render('admin/user/index.html.twig', [
			'controller_name' => 'UserController',
		]);
	}

	#[Route('/{user_id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	#[Entity('user', expr: 'repository.find(user_id)')]
	public function edit(Request $request, User $user): Response
	{
		$form = $this->createForm(UserType::class, $user, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			if ($avatar = $form->get('avatar')->getData()) $this->userManager->updateAvatar($user, $avatar);
			$this->userManager->save($user);

			return $this->redirectToRoute('admin_user_edit');
		}

		return $this->renderForm('profile_form.html.twig', [
			'entity' => $user,
			'form' => $form,
			'avatar' => $this->userManager->getAvatar($user)?->getPathname()
		]);
	}
}
