<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Group;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class GroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Skupina')
            ->setEntityLabelInPlural('Skupiny')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'inviteCode'])
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
        yield TextField::new('name', 'Název');
        yield TextField::new('inviteCode', 'Kód pozvánky')->setDisabled();
        yield AssociationField::new('createdBy', 'Vytvořil');
        yield IntegerField::new('membersCount', 'Členů')
            ->hideOnForm();
        yield IntegerField::new('beerEntriesCount', 'Záznamů')
            ->hideOnForm();
        yield AssociationField::new('members', 'Členové')
            ->hideOnForm()
            ->hideOnIndex()
            ->setTemplatePath('admin/field/group_members.html.twig');
        yield DateTimeField::new('createdAt', 'Vytvořeno')
            ->hideOnForm()
            ->setFormat('d.M.Y HH:mm');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('createdBy', 'Vytvořil'));
    }
}
