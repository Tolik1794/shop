<?php

namespace App\Controller\Admin;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'app_admin_'), IsGranted('ROLE_ADMIN')]
class MainController extends AbstractController
{
	#[Route('/', name: 'index')]
	public function index(): Response
    {
        return $this->render('admin/main/index.html.twig', [
            'controller_name' => 'MainController',
        ]);
    }
}
