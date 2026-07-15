<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(
        Request $request,
        Connection $connection,
        SessionInterface $session
    ): Response {

        if ($request->isMethod('POST')) {

            $fullName = trim($request->request->get('full_name'));
            $email = trim($request->request->get('email'));
            $password = $request->request->get('password');

            // Empty fields
            if (!$fullName || !$email || !$password) {

                return $this->render('auth/register.html.twig', [
                    'error' => 'Veuillez remplir tous les champs.'
                ]);
            }

            // Check email already exists
            $existing = $connection->fetchAssociative(
                "SELECT id FROM users WHERE email = :email",
                [
                    'email' => $email
                ]
            );

            if ($existing) {

                return $this->render('auth/register.html.twig', [
                    'error' => 'Cette adresse e-mail est déjà utilisée.'
                ]);
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $connection->insert('users', [

                'full_name' => $fullName,
                'email' => $email,
                'password_hash' => $passwordHash,
                'created_at' => date('Y-m-d H:i:s')

            ]);

            // Auto login
            $userId = $connection->lastInsertId();

            $session->set('user_id', $userId);

            return $this->redirectToRoute('app_onboarding');
        }

        return $this->render('auth/register.html.twig');
    }

    /**
     * @Route("/login", name="app_login")
     */
    public function login(
        Request $request,
        Connection $connection,
        SessionInterface $session
    ): Response {

        if ($session->has('user_id')) {

            return $this->redirectToRoute('dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {

            $email = trim($request->request->get('email'));
            $password = $request->request->get('password');

            $user = $connection->fetchAssociative(

                "SELECT * FROM users WHERE email = :email",

                [
                    'email' => $email
                ]

            );

            if (!$user) {

                $error = "Adresse e-mail inconnue.";

            } elseif (!password_verify($password, $user['password_hash'])) {

                $error = "Mot de passe incorrect.";

            } else {

                $session->set('user_id', $user['id']);

                return $this->redirectToRoute('dashboard');

            }

        }

        return $this->render('auth/login.html.twig', [

            'error' => $error

        ]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout(SessionInterface $session): Response
    {
        $session->clear();

        return $this->redirectToRoute('home');
    }
}