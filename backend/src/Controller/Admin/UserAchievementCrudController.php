<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\UserAchievement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class UserAchievementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserAchievement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Achievement')
            ->setEntityLabelInPlural('Achievementy')
            ->setDefaultSort(['unlockedAt' => 'DESC'])
            ->setSearchFields(['user.name', 'achievementId']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm()->hideOnIndex();
        yield AssociationField::new('user', 'Uživatel');
        yield TextField::new('achievementId', 'Achievement');
        yield DateTimeField::new('unlockedAt', 'Odemčeno')
            ->setFormat('d.M.Y HH:mm');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user', 'Uživatel'))
            ->add(TextFilter::new('achievementId', 'Achievement'));
    }
}
