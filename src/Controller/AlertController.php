<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AlertController extends AbstractController
{
    #[Route('/alerts', name: 'app_alerts')]
    public function index(
        SessionInterface $session,
        Connection $connection
    ): Response
    {
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('user_id');

        $alerts = $connection->fetchAllAssociative("
            SELECT
                a.*,
                b.reference,
                b.objet,
                b.category,
                b.nature,
                b.lieu,
                b.date_limite,
                b.detail_url
            FROM alerts a
            JOIN bons_commandes b
                ON a.bc_id = b.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
        ", [$userId]);

        return $this->render('alert/index.html.twig', [
            'alerts' => $alerts,
        ]);
    }

    #[Route('/test-email', name: 'app_test_email')]
    public function testEmail(MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('alerts@vigiebc.test')
            ->to('test@example.com')
            ->subject('Mailtrap Test')
            ->html('
                <h2>Congratulations 🎉</h2>
                <p>Your Symfony application is connected to Mailtrap.</p>
                <p>If you can read this, emails are working correctly.</p>
            ');

        $mailer->send($email);

        return new Response('Test email sent successfully!');
    }
}