<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Uživatel')
            ->setEntityLabelInPlural('Uživatelé')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'email'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm()->hideOnIndex();
        yield TextField::new('name', 'Jméno');
        yield EmailField::new('email', 'Email');
        yield ArrayField::new('roles', 'Role');
        yield IntegerField::new('totalBeersConsumed', 'Vypitých piv')
            ->hideOnForm()
            ->setTemplatePath('admin/field/beer_count.html.twig');
        yield IntegerField::new('groupsCount', 'Skupin')
            ->hideOnForm()
            ->hideOnDetail();
        yield IntegerField::new('beerEntriesCount', 'Záznamů')
            ->hideOnForm()
            ->hideOnIndex();
        yield AssociationField::new('defaultBeer', 'Výchozí pivo')
            ->hideOnIndex();
        yield AssociationField::new('groupMemberships', 'Skupiny')
            ->hideOnForm()
            ->hideOnIndex();
        yield DateTimeField::new('lastActivity', 'Poslední aktivita')
            ->hideOnForm()
            ->setFormat('d.M.Y HH:mm');
        yield DateTimeField::new('createdAt', 'Registrace')
            ->hideOnForm()
            ->setFormat('d.M.Y HH:mm');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('roles', 'Role')->setChoices([
                'Admin' => 'ROLE_ADMIN',
                'Uživatel' => 'ROLE_USER',
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Registrace'));
    }
}
