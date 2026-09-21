<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Notification;
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

class NotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Notifikace')
            ->setEntityLabelInPlural('Notifikace')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['user.name', 'user.email', 'type', 'title', 'message']);
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
        yield TextField::new('type', 'Typ');
        yield TextField::new('title', 'Titulek');
        yield TextField::new('message', 'Text')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Vytvořeno')
            ->setFormat('d.M.Y HH:mm');
        yield DateTimeField::new('readAt', 'Přečteno')
            ->setFormat('d.M.Y HH:mm');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user', 'Uživatel'))
            ->add(TextFilter::new('type', 'Typ'));
    }
}
