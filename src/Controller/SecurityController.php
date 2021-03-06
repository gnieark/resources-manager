<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use App\Entity\User;
use App\Service\Menus;
use Doctrine\ORM\EntityManagerInterface;


class SecurityController extends AbstractController
{

    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    public function logout()
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall');
    }

    public function showRegisterForm()
    {
        return $this->render('security/register.html.twig', [
            'form' => $this->getRegisterForm()->createView()
        ]);
    }

    private function getRegisterForm()
    {
        return $this->createFormBuilder()
        ->add('email', EmailType::class,[])
        ->add('password', PasswordType::class, [])
        ->add('display_name', TextType::class,[])
        ->add('save', SubmitType::class,[])
        ->getForm();

    }

    public function getNbUsersActives() {

        $em = $this->getDoctrine()->getManager();
        $repoUser = $em->getRepository(User::class);
 
        $totalUsers = $repoUser->createQueryBuilder('u')
            ->select('count(u.id)')
            ->where('u.active= 1')
            ->getQuery()
            ->getSingleScalarResult();
        return $totalUsers;
    }

    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $form =  $this->getRegisterForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid())
        {
            $data = $form->getData();
            $entityManager = $this->getDoctrine()->getManager();
            $user = new User();
            $user   ->setEmail($data["email"])
                    ->setPassword( $passwordEncoder->encodePassword($user,$data["password"])   )
                    ->setDisplayName( $data["display_name"] );

            if( $this->getNbUsersActives() == 0 )
            {
                //it's the first user, he will be activated and added to group SUPER_ADMIN
                $user->setActive(true)
                     ->setRoles( array('SUPER_ADMIN'));
            }else{
                $user->setActive(false);
            }

            $entityManager->persist($user);
            $entityManager->flush();
            return $this->redirectToRoute('index',[]);
        }

    }
    private function getformCreateUser()
    {
        return $this->createFormBuilder()
        ->add('email', EmailType::class,[])
        ->add('password', PasswordType::class, [])
        ->add('display_name', TextType::class,[])
        ->add('roles', ChoiceType::class, [
            'choices'  => [
                'USER' => false,
                'RESOURCE_MANAGER' => 'RESOURCE_MANAGER',
                'SUPER ADMIN' => 'SUPER_ADMIN',
                ],
            'multiple'  => false
            ])
        ->add('save', SubmitType::class,[])
        ->getForm();
    }
    
    public function showUserManagePage(Request $request,  UserPasswordEncoderInterface $passwordEncoder)
    {


        $formCreateUser = $this->getformCreateUser();
        $formCreateUser->handleRequest($request);

        if ($formCreateUser->isSubmitted() && $formCreateUser->isValid()) {
            $userValues = $formCreateUser->getData();
            
            $user = new User();
            $user   ->setEmail($userValues ["email"])
                    ->setPassword( $passwordEncoder->encodePassword($user,$userValues ["password"])   )
                    ->setDisplayName( $userValues ["display_name"] )
                    ->setActive(true)
                    ->setRoles(array( $userValues["roles"] ));

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            //empty the form
            $formCreateUser = $this->getformCreateUser();

        }

        $users = $this->getDoctrine()
            ->getRepository(User::class)
            ->findAll();
        $menus = new Menus();


        return $this->render('security/listUsers.html.twig', [
            "menus" => $menus->getMenus( $this->getUser() ),
            "users" => $users,
            "formCreateUser"  => $formCreateUser->createView()
        ]);
    }

}
