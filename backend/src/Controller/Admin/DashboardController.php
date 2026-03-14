<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Beer;
use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserAchievement;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(UserCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Pivní Konto - Admin')
            ->setFaviconPath('favicon.ico');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Uživatelé');
        yield MenuItem::linkTo(UserCrudController::class, 'Uživatelé', 'fa fa-users');
        yield MenuItem::section('Piva');
        yield MenuItem::linkTo(BeerCrudController::class, 'Piva', 'fa fa-beer');
        yield MenuItem::linkTo(BeerCrudController::class, 'Ke schválení', 'fa fa-clock')
            ->setQueryParameter('filters[status][comparison]', '=')
            ->setQueryParameter('filters[status][value]', 'pending');
        yield MenuItem::linkTo(BeerEntryCrudController::class, 'Záznamy', 'fa fa-list');
        yield MenuItem::section('Skupiny');
        yield MenuItem::linkTo(GroupCrudController::class, 'Skupiny', 'fa fa-user-group');
        yield MenuItem::section('Achievementy');
        yield MenuItem::linkTo(UserAchievementCrudController::class, 'Achievementy', 'fa fa-trophy');
    }
}
