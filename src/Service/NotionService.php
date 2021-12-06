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

        // Get notion base url and token in the ./config/services.yaml
        $notionBaseUrl = $this->parameterBag->get('notion_base_url');
        $notionToken = $this->parameterBag->get('notion_token');

        // Formating the string for the query
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

    public function getNotionContent(string $notionId, string $type): array {
        $notionBaseUrl = $this->parameterBag->get('notion_base_url');
        $notionToken = $this->parameterBag->get('notion_token');

        // Formating the URL for blocks items
        if ($type === "block")
        {
            $notionSearchUrl = sprintf('%s/blocks/%s/children', $notionBaseUrl, $notionId);
        } else if ($type === "page")
        {
            $notionSearchUrl = sprintf('%s/pages/%s/', $notionBaseUrl, $notionId);
        } else if ($type === "database")
        {
            $notionSearchUrl = sprintf('%s/databases/%s/', $notionBaseUrl, $notionId);
        }

        $authorizationHeader = sprintf('Bearer %s', $notionToken);

        // Here is the query
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

    // PUBLIC fetch


    public function editNotionDB(string $notionId, string $className) :array{
        // Case 1 : We need to edit the Notion Content

        // Récupérer le nom de la database
        $retrievedNotionPage = $this->getNotionContent($notionId);
        $blockTask = [];

        // Foreach block we got we retrieve the text-content he have and we store it in a array
        foreach ($retrievedNotionPage['results'] as $block) {
            if (isset($block['paragraph']['text'][0]["plain_text"])) {
                $blockTask[] = $block['paragraph']['text'][0]["plain_text"];
            }
        }
        $contentNotion = $this->getNotionContent($notionId);
        $field = new $className();
        $pagesBDD = $this->entityManager->getRepository($className);
        $content = [];

        // Case 2 : We need to edit the Notion page properties

        return $content;
    }

    // Envoie en argument ce qu'on veut regarder au niveau de la page Notion
    // On regarde la BDD correspondante
    // On compare les valeurs entre la BDD et la page Notion
    // Et si jamais y'a une différence, on update ?
    public function updateDatabase(string $notionId) :bool {
        $notionUpdated = false;
        $notionContent = $this->getNotionContent($notionId, "page");
        $parentDatabaseName = $this->getNotionContent($notionContent['parent']['database_id'], "database")['title'][0]['plain_text'];

        //We do a switch to take the good class name
        switch ($parentDatabaseName) {
            case "Recipes":
                $BDDContent = $this->entityManager->getRepository(Recipe::class)->findOneByNotionId($notionId);
                break;
            case "Ingredients":
                $BDDContent = $this->entityManager->getRepository(Ingredients::class)->findOneByNotionId($notionId);
                break;
            case "Users":
                $BDDContent = $this->entityManager->getRepository(Recipes::class)->findOneByNotionId($notionId);
                break;
        }

        // We return this variable to check if the process is successful
        return $notionUpdated;
    }

    //Fetching the ingredients of a recipe as an array of Ingredients
    public function getIngredientsOfRecipe(string $notionId) :array {
        $BDDContent = $this->entityManager->getRepository(Recipe::class)->findOneByNotionId($notionId);

    return $BDDContent;
    }

    // Saving into the database the properties we need that are stored in Notion
    public function saveNotionProperties() :array {
        $pages = $this->getNotionPages();
        $content = [];

        // Adding new properties to the DBB
        foreach ($pages['results'] as $page)
        {
            $existingIngredients = $this->entityManager->getRepository(Ingredients::class)->findOneByNotionId($page['id']);
            $existingRecipe = $this->entityManager->getRepository(Recipe::class)->findOneByNotionId($page['id']);

            if ($existingIngredients !== null || $existingRecipe !== null) {
                // @todo: If the ID already exist it will not modify the entity in the table !!!! need to fix it
                // Regarder si il y a des modifications en entrée (pour les 2 DB)
                // Faire la check modification, check ID si c'est le même et comparer les entrées
                // H3 Workshop : comment retrieve les données pour les comparer
                // Si modification, alors faire un Set[…] mais avec le bon ID
                //
                continue;
            }

            //  We check if the page / block is in the database
            if ($databaseId = $this->isInDatabase($page)) {
                $id = $page['id'];
                $name = '';
                $type = '';

                if (isset($page['properties']['Name']['title'][0]['plain_text'])) {
                    $name = $page['properties']['Name']['title'][0]['plain_text'];
                }

                if (isset($page['properties']['Tags']['select']['name'])) {
                    $type = $page['properties']['Tags']['select']['name'];
                }

                if($databaseId === $this->parameterBag->get('ingredients_database_id')) {

                    // Store it in the ingredients table
                    $ingredients = new Ingredients();
                    $ingredients->setDatabaseId($databaseId);
                    $ingredients->setName($name);
                    $ingredients->setType($type);
                    $ingredients->setNotionId($id);
                    $content[] = $ingredients;
                    $this->entityManager->persist($ingredients);

                    // Use it for debug (good sprintf) !!
                    //echo(sprintf("%s which is a %s saved in the ingredients database\n", $name, $type));

                } elseif ($databaseId === $this->parameterBag->get('recipes_database_id')) {
                    $retrievedNotionPage = $this->getNotionContent($id, "block");
                    $blockTask = [];

                    // Foreach block we got we retrieve the text-content he have and we store it in a array
                    foreach ($retrievedNotionPage['results'] as $block) {
                        if (isset($block['paragraph']['text'][0]["plain_text"])) {
                            $blockTask[] = $block['paragraph']['text'][0]["plain_text"];
                        }
                    }

                    if($page['properties']['difficulty']['number'] === null) {
                        dd($page);
                    }

                    $recipes = new Recipe();
                    $recipes->setDatabaseId($databaseId);
                    $recipes->setName($name);
                    $recipes->setType($type);
                    $recipes->setBakingTime($page['properties']['bakingTime']['number']);
                    $recipes->setCookingTime($page['properties']['cookingTime']['number']);
                    $recipes->setDifficulty($page['properties']['difficulty']['number']);
                    $recipes->setCost($page['properties']['cost']['number']);
                    $recipes->setDetails($blockTask);
                    $recipes->setNotionId($id);
                    $content[] = $recipes;

                    // Make property persist to save them until we flush it
                    $this->entityManager->persist($recipes);
                }
            }
        }
        // Flush all the things to the DBB
        $this->entityManager->flush();

        // For debug we can see what have been saved in the DBB
        return $content;
    }

    public function isInDatabase(array $page) :?string {
        // first time pierrot uses ternary
        return $page['parent']["database_id"] ?? null;
    }
}