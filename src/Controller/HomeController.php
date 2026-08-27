<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        // Important: guests only see login/register; authenticated users go to the table.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_users');
        }

        return $this->redirectToRoute('app_login');
    }
}
