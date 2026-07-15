<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends AbstractController
{
    #[Route('/choose-categories', name: 'app_choose_categories')]
    public function chooseCategories(
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
                return $this->redirectToRoute('app_choose_nature');
            }

            $categories = $request->request->all('categories');

            if (!is_array($categories)) {
                $categories = [];
            }

            $categories = array_unique(
                array_map('trim', $categories)
            );

            // Remove previous choices
            $connection->executeStatement(
                "DELETE FROM user_categories WHERE user_id = ?",
                [$userId]
            );

            // Save new choices
            foreach ($categories as $category) {

                if ($category === '') {
                    continue;
                }

                $connection->insert('user_categories', [
                    'user_id' => $userId,
                    'category' => $category,
                ]);
            }

            return $this->redirectToRoute('app_choose_nature');
        }

        // Categories come directly from the scraped BCs
        $categories = $connection->fetchAllAssociative("
    SELECT DISTINCT category AS name
    FROM bons_commandes
    WHERE category NOT IN (
        SELECT category
        FROM user_categories
        WHERE user_id = ?
    )
    ORDER BY category ASC
", [$userId]);

        // Already selected categories
        $selectedCategories = $connection->fetchFirstColumn("
            SELECT category
            FROM user_categories
            WHERE user_id = ?
        ", [$userId]);

        return $this->render('categories/choose.html.twig', [
            'categories' => $categories,
            'selectedCategories' => $selectedCategories,
        ]);
    }
}