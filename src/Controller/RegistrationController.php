<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RegistrationController extends AbstractController
{
    private UserPasswordHasherInterface $passwordHasher;
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private ParameterBagInterface $parameterBag;

    public function __construct(
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->parameterBag = $parameterBag;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @Route("/registration", name="app_registration")
     */
    public function index(Request $request): Response
    {
        $params = json_decode($request->getContent(), true);

        if (!isset($params['username']) || empty($params['username'])) {
            throw new HttpException(400, 'Missing username parameter.');
        }

        if (!isset($params['email']) || empty($params['email'])) {
            throw new HttpException(400, 'Missing email parameter.');
        }

        if (!isset($params['password']) || empty($params['password'])) {
            throw new HttpException(400, 'Missing password parameter.');
        }
        $plainPassword = $params['password'];

        $user = new User();
        $user->setUsername($params['username']);
        $user->setEmail($params['email']);
        $encodedPass = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($encodedPass);


        $this->entityManager->persist($user);
        $this->entityManager->flush();



        return $this->json($params);
    }

    /**
     * @Route("/login", name="app_login")
     */
    public function login(Request $request): Response
    {
        $params = json_decode($request->getContent(), true);

        if (!isset($params['email']) || empty($params['email'])) {
            throw new HttpException(400, 'Missing email parameter.');
        }

        if (!isset($params['password']) || empty($params['password'])) {
            throw new HttpException(400, 'Missing password parameter.');
        }

        $entityManager = $this->getDoctrine()->getManager();
        $user = $entityManager->getRepository(User::class)->findOneByEmail($params['email']);

        if ($user === null) {
            $user = $entityManager->getRepository(User::class)->findOneByUsername($params['email']);
        }

        if ($user === null) {
            throw new HttpException(400, 'Account not found');
        }

        if ($this->passwordHasher->isPasswordValid($user, $params['password'])) {
            $returnedArray = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'description' => $user->getDescription(),
                'photo' => $user->getPhoto(),
                'username' => $user->getUsername()
            ];
        } else {
            throw new HttpException(400, 'Bad Password');
        }

        return $this->json($returnedArray);

    }

}
