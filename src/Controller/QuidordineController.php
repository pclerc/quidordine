<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuidordineController extends AbstractController
{
    /**
     * @Route("/quidordine", name="quidordine")
     */
    public function index(): Response
    {
        return $this->render('quidordine/index.html.twig', [
            'controller_name' => 'QuidordineController',
        ]);
    }
}
