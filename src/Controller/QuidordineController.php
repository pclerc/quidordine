<?php

namespace App\Controller;

use App\Entity\Recipe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\NotionService;

class QuidordineController extends AbstractController
{
    /**
     * @var NotionService
     */
    private $notionService;

    public function __construct(NotionService $notionService)
    {
        $this->notionService = $notionService;
    }

    /**
     * @Route("/quidordine", name="quidordine")
     */
    public function quidordine(): Response
    {
        return $this->render('quidordine/index.html.twig', [
            'controller_name' => 'QuidordineController',
        ]);
    }

    /**
     * @Route("/", name="default")
     */
    public function index(): Response
    {
        return $this->json("Hello World");
    }

    /**
     * @Route("/notionpages", name="notionspages")
     */
    public function notionPages(): Response
    {
        $pages = $this->notionService->getNotionPages();
//        var_dump($pages["results"][0]["id"]);


        $returnArray = [];

        /** @var Recipe $page */
        foreach ($pages['results'] as $page) {

            if ($page["object"] === "database") {
                $title = substr($page['title'][0]['plain_text'], 0, 255);
            } else if($page["object"] === "page" ) {
                $title = $page['properties']["Name"]["title"][0]["plain_text"];
            }

            $returnArray[] = [
                'id' => $page["id"],
                'objectname' => $page["object"],
                'name' => $title
            ];
//            $returnArray[] = [
//                'results' => $page
//            ];

//            return $this->json($page);
        }

//        return $this->json($returnArray);
        return $this->json($returnArray);
    }

}
