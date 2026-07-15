<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class FilterController extends AbstractController
{
    #[Route('/filters', name: 'app_filters')]
    public function index(
        SessionInterface $session,
        Connection $connection
    ): Response
    {
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('user_id');

        $categories = $connection->fetchFirstColumn("
            SELECT category
            FROM user_categories
            WHERE user_id = ?
            ORDER BY category
        ", [$userId]);

        $natures = $connection->fetchFirstColumn("
            SELECT nature
            FROM user_natures
            WHERE user_id = ?
            ORDER BY nature
        ", [$userId]);

        $cities = $connection->fetchFirstColumn("
            SELECT city
            FROM user_cities
            WHERE user_id = ?
            ORDER BY city
        ", [$userId]);

        $keywords = $connection->fetchFirstColumn("
            SELECT keyword
            FROM user_keywords
            WHERE user_id = ?
            ORDER BY keyword
        ", [$userId]);

        return $this->render('filter/index.html.twig', [
            'categories' => $categories,
            'natures'    => $natures,
            'cities'     => $cities,
            'keywords'   => $keywords,
        ]);
    }
#[Route('/filters/delete/{type}/{value}', name: 'app_filter_delete')]
public function deleteFilter(
    string $type,
    string $value,
    SessionInterface $session,
    Connection $connection
): Response
{
    if (!$session->has('user_id')) {
        return $this->redirectToRoute('app_login');
    }

    $userId = $session->get('user_id');

    switch ($type) {

        case 'category':
            $connection->executeStatement(
                "DELETE FROM user_categories
                 WHERE user_id = ? AND category = ?",
                [$userId, $value]
            );
            break;

        case 'nature':
            $connection->executeStatement(
                "DELETE FROM user_natures
                 WHERE user_id = ? AND nature = ?",
                [$userId, $value]
            );
            break;

        case 'city':
            $connection->executeStatement(
                "DELETE FROM user_cities
                 WHERE user_id = ? AND city = ?",
                [$userId, $value]
            );
            break;

        case 'keyword':
            $connection->executeStatement(
                "DELETE FROM user_keywords
                 WHERE user_id = ? AND keyword = ?",
                [$userId, $value]
            );
            break;
    }

    return $this->redirectToRoute('app_filters');
}

}