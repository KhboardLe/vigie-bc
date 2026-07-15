<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class NatureController extends AbstractController
{
    #[Route('/choose-nature', name: 'app_choose_nature')]
    public function chooseNature(
        Request $request,
        Connection $connection,
        SessionInterface $session
    ): Response {

        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {

            if ($request->request->has('skip')) {
                return $this->redirectToRoute('app_choose_cities');
            }

            

            $natures = $request->request->all('natures');

if (!is_array($natures)) {
    $natures = [];
}

$natures = array_unique(array_map('trim', $natures));

$connection->executeStatement(
    "DELETE FROM user_natures WHERE user_id = ?",
    [$userId]
);

foreach ($natures as $nature) {

    if ($nature === '') {
        continue;
    }

    $connection->insert('user_natures', [
        'user_id' => $userId,
        'nature' => $nature,
    ]);

}

            return $this->redirectToRoute('app_choose_cities');
        }

        $natures = $connection->fetchAllAssociative("
    SELECT DISTINCT name
    FROM nature_options
    WHERE name NOT IN (
        SELECT nature
        FROM user_natures
        WHERE user_id = ?
    )
    ORDER BY name ASC
", [$userId]);

        $selectedNatures = $connection->fetchFirstColumn("
            SELECT nature
            FROM user_natures
            WHERE user_id = ?
        ", [$userId]);

        return $this->render('nature/choose.html.twig', [
            'natures' => $natures,
            'selectedNatures' => $selectedNatures,
        ]);
    }
}
