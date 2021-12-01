<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\Ingredients;
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

//      Maybe we can use it to see if a page is archived in case of problems in DB
//            if ($page['object'] === 'page' && $pages['archived'] === true) {
//                continue;
//            }

            if ($page['object'] === 'database') {
                $title = substr($page['title'][0]['plain_text'], 0, 255);
            } else if($page['object'] === 'page' && isset($page['properties']['title']['title'][0]['plain_text']) ) {
                $title = substr($page['properties']['title']['title'][0]['plain_text'], 0, 255);
            } else if (isset($page['properties']['Name']['title'][0]['plain_text'])){
                $title = $page['properties']['Name']['title'][0]['plain_text'];
            }

            if (isset($page['parent']["database_id"])) {
                $databaseID = $page['parent']["database_id"];
            } else {
                $databaseID = null;
            }

            $returnArray[] = [
                'id' => $page['id'],
                'objectname' => $page['object'],
                'name' => $title,
                'databaseId' => $databaseID
            ];

        }
        return $this->json($returnArray);
    }

    /**
     * @Route("/savenotionpages", name="savenotionpages")
     */
    public function saveNotionPages(): Response
    {
        $savedNotionPages = $this->notionService->saveNotionProperties();
        return $this->json($savedNotionPages);
    }

}
