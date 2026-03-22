<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GroupMember;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class GroupMemberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GroupMember::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Členství')
            ->setEntityLabelInPlural('Členství ve skupinách')
            ->setDefaultSort(['joinedAt' => 'DESC'])
            ->setSearchFields(['user.name', 'group.name']);
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
        yield AssociationField::new('group', 'Skupina');
        yield ChoiceField::new('role', 'Role')
            ->setChoices([
                'Admin' => 'admin',
                'Člen' => 'member',
            ])
            ->renderAsBadges([
                'admin' => 'primary',
                'member' => 'secondary',
            ]);
        yield DateTimeField::new('joinedAt', 'Připojil se')
            ->setFormat('d.M.Y HH:mm');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user', 'Uživatel'))
            ->add(EntityFilter::new('group', 'Skupina'))
            ->add(ChoiceFilter::new('role', 'Role')->setChoices([
                'Admin' => 'admin',
                'Člen' => 'member',
            ]));
    }
}
