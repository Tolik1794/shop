<?php

namespace App\Controller\Admin;

use App\Entity\LegalEntity;
use App\Form\Admin\Type\LegalEntityType;
use App\Manager\LegalEntityManager;
use App\Repository\LegalEntityRepository;
use App\Tools\AbstractAdvancedController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// TODO tax-module phase 6: replace the temporary `dashboard.financial` gate with
// dedicated `tax.*` permissions once they are added to the PermissionCatalog.
#[Route('/admin/legal-entity', name: 'admin_legal_entity_'), IsGranted('dashboard.financial')]
class LegalEntityController extends AbstractAdvancedController
{
	public function __construct(
		private readonly LegalEntityRepository $legalEntityRepository,
		private readonly LegalEntityManager $legalEntityManager,
	)
	{
	}

	#[Route('/', name: 'index', methods: ['GET'])]
	public function index(): Response
	{
		return $this->render('admin/legal_entity/index.html.twig', [
			'legalEntities' => $this->legalEntityRepository->findActive(),
		]);
	}

	#[Route('/new', name: 'new', methods: ['GET', 'POST'])]
	public function new(Request $request): Response
	{
		$legalEntity = new LegalEntity();
		$form = $this->createForm(LegalEntityType::class, $legalEntity, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->legalEntityManager->save($legalEntity);

			return $this->redirectToRoute('admin_legal_entity_index');
		}

		return $this->render('admin/legal_entity/form.html.twig', [
			'entity' => $legalEntity,
			'form' => $form,
		]);
	}

	#[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
	public function edit(Request $request, LegalEntity $legalEntity): Response
	{
		$form = $this->createForm(LegalEntityType::class, $legalEntity, ['method' => 'POST']);
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->legalEntityManager->save($legalEntity);

			return $this->stayOrRedirect('admin_legal_entity_index');
		}

		return $this->render('admin/legal_entity/form.html.twig', [
			'entity' => $legalEntity,
			'form' => $form,
		]);
	}

	#[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
	public function delete(Request $request, LegalEntity $legalEntity): Response
	{
		if (!$this->isCsrfTokenValid('delete_legal_entity_' . $legalEntity->getId(), (string) $request->request->get('_token'))) {
			return $this->redirectToRoute('admin_legal_entity_index');
		}

		$this->legalEntityManager->softDelete($legalEntity);

		return $this->redirectToRoute('admin_legal_entity_index');
	}
}
