<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    /**
     * @Route("/profile", name="app_profile")
     */
    public function index(
        Request $request,
        Connection $connection,
        SessionInterface $session
    ): Response
    {
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('user_id');
        $error = null;
$success = null;

        // Get current user
        $user = $connection->fetchAssociative("
            SELECT *
            FROM users
            WHERE id = :id
        ", [
            'id' => $userId,
        ]);

        $keywords = $connection->fetchAllAssociative("
    SELECT keyword
    FROM user_keywords
    WHERE user_id = ?
", [$userId]);

        $names = explode(' ', trim($user['full_name']));
$initials = '';

foreach ($names as $name) {
    $initials .= strtoupper(substr($name, 0, 1));
}

        // Save modifications
if ($request->isMethod('POST') && $request->request->has('save_profile')) {

    $fullName = trim($request->request->get('full_name'));
    $email = trim($request->request->get('email'));

    $connection->executeStatement("
        UPDATE users
        SET
            full_name = :full_name,
            email = :email
        WHERE id = :id
    ", [
        'full_name' => $fullName,
        'email' => $email,
        'id' => $userId,
    ]);

    $success = "Profil mis à jour avec succès.";

    $user = $connection->fetchAssociative("
    SELECT *
    FROM users
    WHERE id = :id
", [
    'id' => $userId,
]);


$names = explode(' ', trim($user['full_name']));
$initials = '';

foreach ($names as $name) {
    $initials .= strtoupper(substr($name, 0, 1));
}

$session->set('user_name', $user['full_name']);

$session->set('user_email', $user['email']);
}




        // if ($request->isMethod('POST')) {

        //     $fullName = trim($request->request->get('full_name'));
        //     $email = trim($request->request->get('email'));

        //     $connection->executeStatement("
        //         UPDATE users
        //         SET
        //             full_name = :full_name,
        //             email = :email
        //         WHERE id = :id
        //     ", [
        //         'full_name' => $fullName,
        //         'email' => $email,
        //         'id' => $userId,
        //     ]);

if ($request->isMethod('POST') && $request->request->has('change_password')) {

    $currentPassword = $request->request->get('current_password');
    $newPassword = $request->request->get('new_password');

    if (!password_verify($currentPassword, $user['password_hash'])) {

        $error = "Le mot de passe actuel est incorrect.";

    } elseif (password_verify($newPassword, $user['password_hash'])) {

        $error = "Le nouveau mot de passe doit être différent de l'ancien.";

    } elseif (strlen($newPassword) < 8) {

        $error = "Le mot de passe doit contenir au moins 8 caractères.";

    } else {

        $connection->executeStatement("
            UPDATE users
            SET password_hash = :password
            WHERE id = :id
        ", [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        $success = "Mot de passe modifié avec succès.";
    }



            // Reload updated user
          $user = $connection->fetchAssociative("
    SELECT *
    FROM users
    WHERE id = :id
", [
    'id' => $userId,
]);


        }

        return $this->render('profile/index.html.twig', [
              'user' => $user,
    'initials' => $initials,
    'error' => $error,
    'success' => $success,
    'keywords' => $keywords,
    
        ]);
    }
}