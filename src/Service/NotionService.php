<?php

namespace App\Service;

use App\Entity\Ingredients;
use App\Entity\Recipe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotionService
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->parameterBag = $parameterBag;
    }

    public function getNotionPages(): array {
        $notionBaseUrl = $this->parameterBag->get('notion_base_url');
        $notionToken = $this->parameterBag->get('notion_token');

        $notionSearchUrl = sprintf('%s/search', $notionBaseUrl);
        $authorizationHeader = sprintf('Bearer %s', $notionToken);

        $pages = $this->httpClient->request('POST', $notionSearchUrl, [
            'body' => [
                'query' => '',
            ],
            'headers' => [
                'Authorization' => $authorizationHeader,
                'Notion-Version' => '2021-08-16'
            ],
        ]);
        return json_decode($pages->getContent(),true);
    }

    public function getNotionContent(string $notionId): array {
        $notionBaseUrl = $this->parameterBag->get('notion_base_url');
        $notionToken = $this->parameterBag->get('notion_token');

        $notionSearchUrl = sprintf('%s/blocks/%s/children', $notionBaseUrl, $notionId);
        $authorizationHeader = sprintf('Bearer %s', $notionToken);

        $pages = $this->httpClient->request('GET', $notionSearchUrl, [
            'body' => [
                'query' => '',
            ],
            'headers' => [
                'Authorization' => $authorizationHeader,
                'Notion-Version' => '2021-08-16'
            ],
        ]);
        return json_decode($pages->getContent(), true);
    }


    public function saveNotionProperties() :array {
        /*
         * @todo: Saving Pages in the right Database using the function in the controller
        */
        $pages = $this->getNotionPages();

        $ingredientsPages = [];

        foreach ($pages['results'] as $page)
        {
            $existingPage = $this->entityManager->getRepository(Ingredients::class)->findOneByNotionId($page['id']);
            if ($existingPage !== null)
            {
                continue;
            }
            if ($databaseId = $this->isInDatabase($page))
            {
                $id = $page['id'];
                $name = "";
                $type = "";

                if (isset($page['properties']['Name']['title'][0]['plain_text'])){
                    $name = $page['properties']['Name']['title'][0]['plain_text'];
                }

                if (isset($page['properties']['Tags']['select']['name']))
                {
                    $type = $page['properties']['Tags']['select']['name'];
                }

                if($databaseId === $this->parameterBag->get('ingredients_database_id'))
                {
                    $ingredients = new Ingredients();
                    $ingredients->setDatabaseId($databaseId);
                    $ingredients->setName($name);
                    $ingredients->setType($type);
                    $ingredients->setNotionId($id);
                    $content[] = $ingredients;
                    $this->entityManager->persist($ingredients);
                    //echo(sprintf("%s which is a %s saved in the ingredients database\n", $name, $type));
                } elseif ($databaseId === $this->parameterBag->get('recipes_database_id'))
                {
                    $recipes = new Recipe();
                    $recipes->setDatabaseId($databaseId);
                    $recipes->setName($name);
                    $recipes->setType($type);
                    $recipes->setBakingTime($page['properties']['bakingTime']['number']);
                    $recipes->setCookingTime($page['properties']['cookingTime']['number']);
                    $recipes->setDifficulty($page['properties']['difficulty']['number']);
                    $recipes->setCost($page['properties']['cost']['number']);
                    $content[] = $recipes;
                    $retrievedNotionPage = $this->getNotionContent($id);

                    //$this->entityManager->persist($recipes);

                }
            }
        }
        $this->entityManager->flush();

        return $retrievedNotionPage;
    }

    public function isInDatabase(array $page) :?string {
        return $page['parent']["database_id"] ?? null;
    }
}