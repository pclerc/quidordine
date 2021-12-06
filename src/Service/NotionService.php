<?php

namespace App\Service;

use App\Entity\Ingredients;
use App\Entity\Recipe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function MongoDB\BSON\toJSON;

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

    public function getNextPages(string $nextCursor): array {
        // Get notion base url and token in the ./config/services.yaml
        $notionBaseUrl = $this->parameterBag->get('notion_base_url');
        $notionToken = $this->parameterBag->get('notion_token');

        // Formating the string for the query
        $notionSearchUrl = sprintf('%s/search', $notionBaseUrl);
        $authorizationHeader = sprintf('Bearer %s', $notionToken);

        $jsonrequest = json_encode([
            'start_cursor' => $nextCursor
        ]);
        $pages = $this->httpClient->request('POST', $notionSearchUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $authorizationHeader,
                'Notion-Version' => '2021-08-16'
            ],
            'body' => $jsonrequest
        ]);
        return json_decode($pages->getContent(),true);
    }

    public function getNotionPages(): array {

        // Get notion base url and token in the ./config/services.yaml
        $notionBaseUrl = $this->parameterBag->get('notion_base_url');
        $notionToken = $this->parameterBag->get('notion_token');

        // Formating the string for the query
        $notionSearchUrl = sprintf('%s/search', $notionBaseUrl);
        $authorizationHeader = sprintf('Bearer %s', $notionToken);
        $jsonrequest = json_encode([
            'page_size' => 100
        ]);
        $pages = $this->httpClient->request('POST', $notionSearchUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $authorizationHeader,
                'Notion-Version' => '2021-08-16'
            ],
            'body' => $jsonrequest
        ]);
        return json_decode($pages->getContent(),true);
    }

    // getNotionContent allows us to get a database, a page or a block of the given notionId.
    // the code is based on what we've learned in class.
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

    // Function not finished.
    // The purpose of updateDatabase was to update the database when this one was already established.
    // The given argument was a notionId of the page we wanted to update.
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

    //Fetching the ingredients of a recipe as an array.
    public function getIngredientsOfRecipe(string $notionId) :array {
        $ingredients = [];
        $BDDContent = $this->entityManager->getRepository(Recipe::class)->findOneByNotionId($notionId);

        foreach ($BDDContent->getIngredients() as $ingredient)
        {
            $ingredientsRecipe = array(
                "name" => $ingredient->getName(),
                "type" => $ingredient->getType(),
                "databaseID" => $ingredient->getDatabaseId(),
                "notionID" => $ingredient->getNotionId(),
            );

            $ingredients[] = $ingredientsRecipe;
        }

        return $ingredients;
    }

    // To avoid any bugs while we are saving Notion pages and properties in the database,
    // we created a function called addIngredientsToRecipes
    // to be called at the end of saveNotionProperties.
    // This function allows us to add ingredients (Ingredients::class) to the corresponding recipe,
    // and it also add (through the Ingredients->addNameRecipe($recipe) method) recipes (Recipe::class)
    // to the corresponding ingredient.
    public function addIngredientsToRecipes() {
        // We fetch every ingredient from the database
        $ingredientsBDD = $this->entityManager->getRepository(Ingredients::class)->findAll();

        foreach ($ingredientsBDD as $ingredientBDD)
        {
            // For each ingredient fetched, we will see the Notion related content
            $ingredientNotion = $this->getNotionContent($ingredientBDD->getNotionId(), "page");

            foreach($ingredientNotion['properties']['recipes']['relation'] as $recipeId)
            {
                //We fetch the corresponding recipe of the related ingredient in the foreach loop
                $recipe = $this->entityManager->getRepository(Recipe::class)->findOneByNotionId($recipeId['id']);

                if ($recipe != null)
                {
                    $ingredientBDD->addNameRecipe($recipe);
                    $this->entityManager->persist($ingredientBDD);
                }
            }
        }
        $this->entityManager->flush();
    }

    // Saving into the database the properties we need that are stored in Notion
    public function saveNotionProperties() {
        $pages = $this->getNotionPages();
        $continue = true;
        while($continue) {
            if($pages['has_more'] === false) {
                $continue = false;
            }
            // Adding new properties to the DBB
            foreach ($pages['results'] as $page)
            {
                $existingIngredients = $this->entityManager->getRepository(Ingredients::class)->findOneByNotionId($page['id']);
                $existingRecipe = $this->entityManager->getRepository(Recipe::class)->findOneByNotionId($page['id']);

                if ($existingIngredients !== null || $existingRecipe !== null) {
                    //this condition is needed to avoid duplicates
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

                    if (isset($page['properties']['type']['select']['name'])) {
                        $type = $page['properties']['type']['select']['name'];
                    } elseif (isset($page['properties']['type']['multi_select'][0]['name']))
                    {
                        $type = $page['properties']['type']['multi_select'][0]['name'];
                    }

                    if($databaseId === $this->parameterBag->get('ingredients_database_id')) {

                        // Store it in the ingredients table
                        $ingredients = new Ingredients();
                        $ingredients->setDatabaseId($databaseId);
                        $ingredients->setName($name);
                        $ingredients->setType($type);
                        $ingredients->setNotionId($id);
                        $this->entityManager->persist($ingredients);

                    } elseif ($databaseId === $this->parameterBag->get('recipes_database_id')) {
                        $retrievedNotionPage = $this->getNotionContent($id, "block");
                        $blockTask = [];

                        // Foreach block we got we retrieve the text-content he have and we store it in a array
                        foreach ($retrievedNotionPage['results'] as $block) {
                            if (isset($block['paragraph']['text'][0]["plain_text"])) {
                                $blockTask[] = $block['paragraph']['text'][0]["plain_text"];
                            }
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

                        // Make property persist to save them until we flush it
                        $this->entityManager->persist($recipes);
                    }
                }
            }
            // Flush all the things to the DBB
            $this->entityManager->flush();
            if ($continue) {
                $pages = $this->getNextPages($pages['next_cursor']);
            }
        }

        // Once everything is perfectly set up, we add the ingredients to the corresponding recipe
        $this->addIngredientsToRecipes();
    }

    // A simple ternary function to check if a page is in a database
    public function isInDatabase(array $page) :?string {
        return $page['parent']["database_id"] ?? null;
    }
}