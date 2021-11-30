<?php

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RecipeRepository::class)
 */
class Recipe
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $details;

    /**
     * @ORM\ManyToMany(targetEntity=Ingredients::class, inversedBy="nameRecipe")
     */
    private $ingredients;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $cookingTime;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $bakingTime;

    /**
     * @ORM\Column(type="integer")
     */
    private $difficulty;

    /**
     * @ORM\Column(type="integer")
     */
    private $cost;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="favorites")
     */
    private $nameUser;

    public function __construct()
    {
        $this->ingredients = new ArrayCollection();
        $this->nameUser = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(string $details): self
    {
        $this->details = $details;

        return $this;
    }

    /**
     * @return Collection|Ingredients[]
     */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(Ingredients $ingredient): self
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients[] = $ingredient;
        }

        return $this;
    }

    public function removeIngredient(Ingredients $ingredient): self
    {
        $this->ingredients->removeElement($ingredient);

        return $this;
    }

    public function getCookingTime(): ?int
    {
        return $this->cookingTime;
    }

    public function setCookingTime(?int $cookingTime): self
    {
        $this->cookingTime = $cookingTime;

        return $this;
    }

    public function getBakingTime(): ?int
    {
        return $this->bakingTime;
    }

    public function setBakingTime(?int $bakingTime): self
    {
        $this->bakingTime = $bakingTime;

        return $this;
    }

    public function getDifficulty(): ?int
    {
        return $this->difficulty;
    }

    public function setDifficulty(int $difficulty): self
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getCost(): ?int
    {
        return $this->cost;
    }

    public function setCost(int $cost): self
    {
        $this->cost = $cost;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getNameUser(): Collection
    {
        return $this->nameUser;
    }

    public function addNameUser(User $nameUser): self
    {
        if (!$this->nameUser->contains($nameUser)) {
            $this->nameUser[] = $nameUser;
            $nameUser->addFavorite($this);
        }

        return $this;
    }

    public function removeNameUser(User $nameUser): self
    {
        if ($this->nameUser->removeElement($nameUser)) {
            $nameUser->removeFavorite($this);
        }

        return $this;
    }
}
