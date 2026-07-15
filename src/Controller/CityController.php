<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class CityController extends AbstractController
{
    #[Route('/choose-cities', name: 'app_choose_cities')]
    public function chooseCities(
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
                return $this->redirectToRoute('app_choose_keywords');
            }

            $cities = $request->request->all('cities');

            if (!is_array($cities)) {
                $cities = [];
            }

            $cities = array_unique(array_map('trim', $cities));

            $connection->executeStatement(
                "DELETE FROM user_cities WHERE user_id = ?",
                [$userId]
            );

            foreach ($cities as $cityName) {

                if ($cityName === '') {
                    continue;
                }

                $connection->insert('user_cities', [
                    'user_id' => $userId,
                    'city' => $cityName,
                ]);
            }

            return $this->redirectToRoute('app_choose_keywords');
        }

        $cities = $connection->fetchAllAssociative("
    SELECT DISTINCT lieu AS name
    FROM bons_commandes
    WHERE lieu IS NOT NULL
      AND lieu <> ''
      AND lieu NOT IN (
          SELECT city
          FROM user_cities
          WHERE user_id = ?
      )
    ORDER BY lieu ASC
", [$userId]);

        $selectedCities = $connection->fetchFirstColumn("
            SELECT city
            FROM user_cities
            WHERE user_id = ?
        ", [$userId]);

        return $this->render('cities/choose.html.twig', [
            'cities' => $cities,
            'selectedCities' => $selectedCities,
        ]);
    }
}