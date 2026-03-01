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
        yield MenuItem::linkToCrud('Uživatelé', 'fa fa-users', User::class);
        yield MenuItem::section('Piva');
        yield MenuItem::linkToCrud('Piva', 'fa fa-beer', Beer::class);
        yield MenuItem::linkToCrud('Záznamy', 'fa fa-list', BeerEntry::class);
        yield MenuItem::section('Skupiny');
        yield MenuItem::linkToCrud('Skupiny', 'fa fa-user-group', Group::class);
        yield MenuItem::section('Achievementy');
        yield MenuItem::linkToCrud('Achievementy', 'fa fa-trophy', UserAchievement::class);
    }
}
