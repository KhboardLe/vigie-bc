<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
// use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ForgotPasswordController extends AbstractController
{

#[Route('/forgot-password', name: 'app_forgot_password')]
public function index(): Response
{
    return $this->render('security/forgot_password.html.twig');
}
   
    #[Route('/send-temporary-password', name: 'app_send_temp_password', methods: ['POST'])]
    public function sendTemporaryPassword(
    Request $request,
    Connection $connection,
    MailerInterface $mailer
): Response
    {
$email = trim($request->request->get('email'));
$user = $connection->fetchAssociative("
    SELECT id, email
    FROM users
    WHERE email = ?
", [$email]);

if (!$user) {

    $this->addFlash(
        'success',
        'Si cette adresse existe, un mot de passe temporaire a été envoyé.'
    );

    return $this->redirectToRoute('app_forgot_password');
}

$tempPassword = substr(bin2hex(random_bytes(8)), 0, 10);

        // We'll add the logic here next.
// $this->addFlash(
//     'success',
//     'Si cette adresse existe, un mot de passe temporaire a été envoyé.'
// );
$hashedPassword = password_hash(
    $tempPassword,
    PASSWORD_DEFAULT
);

$connection->update(
    'users',
    [
        'password_hash' => $hashedPassword
    ],
    [
        'id' => $user['id']
    ]
);

$message = (new Email())
    ->from('alerts@vigiebc.test')
    ->to($user['email'])
    ->subject('Mot de passe temporaire - Vigie BC')
    ->html("
        <h2>Réinitialisation du mot de passe</h2>

        <p>Bonjour,</p>

        <p>Voici votre mot de passe temporaire :</p>

        <h2>{$tempPassword}</h2>

        <p>
            Connectez-vous avec ce mot de passe puis
            changez-le depuis votre profil.
        </p>
    ");

try {

    $mailer->send($message);

} catch (\Throwable $e) {

    dd($e->getMessage());

}

$this->addFlash(
    'success',
    'Si cette adresse existe, un mot de passe temporaire a été envoyé.'
);

return $this->redirectToRoute('app_forgot_password');


return $this->redirectToRoute('app_forgot_password');
    }
}