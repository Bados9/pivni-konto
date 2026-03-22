<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Beer;
use App\Entity\BeerEntry;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Repository\BeerEntryRepository;
use App\Repository\BeerRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BeerRepository $beerRepository,
        private readonly BeerEntryRepository $beerEntryRepository,
        private readonly GroupRepository $groupRepository,
    ) {
    }

    public function index(): Response
    {
        $totalUsers = $this->userRepository->count([]);
        $totalBeers = $this->beerRepository->count([]);
        $pendingBeers = $this->beerRepository->count(['status' => 'pending']);
        $totalEntries = $this->beerEntryRepository->count([]);
        $totalGroups = $this->groupRepository->count([]);

        $todayStart = new \DateTimeImmutable('today 05:00');
        if (new \DateTimeImmutable() < $todayStart) {
            $todayStart = $todayStart->modify('-1 day');
        }
        $todayEntries = $this->beerEntryRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.consumedAt >= :start')
            ->setParameter('start', $todayStart)
            ->getQuery()
            ->getSingleScalarResult();

        $recentUsers = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $topDrinkers = $this->beerEntryRepository->createQueryBuilder('e')
            ->select('IDENTITY(e.user) as userId, u.name, SUM(e.quantity) as totalBeers')
            ->join('e.user', 'u')
            ->groupBy('e.user, u.name')
            ->orderBy('totalBeers', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalBeers' => $totalBeers,
            'pendingBeers' => $pendingBeers,
            'totalEntries' => $totalEntries,
            'totalGroups' => $totalGroups,
            'todayEntries' => $todayEntries,
            'recentUsers' => $recentUsers,
            'topDrinkers' => $topDrinkers,
        ]);
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
        yield MenuItem::linkTo(GroupMemberCrudController::class, 'Členství', 'fa fa-people-arrows');

        yield MenuItem::section('Achievementy');
        yield MenuItem::linkTo(UserAchievementCrudController::class, 'Achievementy', 'fa fa-trophy');
    }
}
