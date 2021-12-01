<?php

namespace App\Entity;

use App\Repository\IngredientsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=IngredientsRepository::class)
 */
class Ingredients
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="string", length=255)
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\ManyToMany(targetEntity=Recipe::class, mappedBy="ingredients")
     */
    private $nameRecipe;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="allergies")
     */
    private $nameUser;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $databaseId;

    public function __construct()
    {
        $this->nameRecipe = new ArrayCollection();
        $this->nameUser = new ArrayCollection();
    }

    public function getId(): ?string
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

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection|Recipe[]
     */
    public function getNameRecipe(): Collection
    {
        return $this->nameRecipe;
    }

    public function addNameRecipe(Recipe $nameRecipe): self
    {
        if (!$this->nameRecipe->contains($nameRecipe)) {
            $this->nameRecipe[] = $nameRecipe;
            $nameRecipe->addIngredient($this);
        }

        return $this;
    }

    public function removeNameRecipe(Recipe $nameRecipe): self
    {
        if ($this->nameRecipe->removeElement($nameRecipe)) {
            $nameRecipe->removeIngredient($this);
        }

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
            $nameUser->addAllergy($this);
        }

        return $this;
    }

    public function removeNameUser(User $nameUser): self
    {
        if ($this->nameUser->removeElement($nameUser)) {
            $nameUser->removeAllergy($this);
        }

        return $this;
    }

    public function getDatabaseId(): ?string
    {
        return $this->databaseId;
    }

    public function setDatabaseId(string $databaseId): self
    {
        $this->databaseId = $databaseId;

        return $this;
    }
}
