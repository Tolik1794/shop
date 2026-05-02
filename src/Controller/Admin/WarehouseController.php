<?php

namespace App\Controller\Admin;

use App\Entity\Warehouse;
use App\Form\Admin\Type\WarehouseType;
use App\Repository\WarehouseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/warehouse')]
class WarehouseController extends AbstractController
{
    #[Route('/', name: 'app_admin_warehouse_index', methods: ['GET'])]
    public function index(WarehouseRepository $warehouseRepository): Response
    {
        return $this->render('admin/warehouse/index.html.twig', [
            'warehouses' => $warehouseRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_warehouse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $warehouse = new Warehouse();
        $form = $this->createForm(WarehouseType::class, $warehouse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($warehouse);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_warehouse_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('admin/warehouse/new.html.twig', [
            'warehouse' => $warehouse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_warehouse_show', methods: ['GET'])]
    public function show(Warehouse $warehouse): Response
    {
        return $this->render('admin/warehouse/show.html.twig', [
            'warehouse' => $warehouse,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_warehouse_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Warehouse $warehouse, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(WarehouseType::class, $warehouse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_warehouse_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('admin/warehouse/edit.html.twig', [
            'warehouse' => $warehouse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_warehouse_delete', methods: ['POST'])]
    public function delete(Request $request, Warehouse $warehouse, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$warehouse->getId(), $request->request->get('_token'))) {
            $entityManager->remove($warehouse);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_warehouse_index', [], Response::HTTP_SEE_OTHER);
    }
}
