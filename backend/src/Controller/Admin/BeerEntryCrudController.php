<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BeerEntry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BeerEntryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BeerEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Záznam')
            ->setEntityLabelInPlural('Záznamy')
            ->setDefaultSort(['consumedAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user', 'Uživatel');
        yield AssociationField::new('beer', 'Pivo');
        yield TextField::new('customBeerName', 'Vlastní název')->hideOnIndex();
        yield AssociationField::new('group', 'Skupina');
        yield IntegerField::new('quantity', 'Množství');
        yield IntegerField::new('volumeMl', 'Objem (ml)');
        yield DateTimeField::new('consumedAt', 'Vypito');
        yield TextField::new('note', 'Poznámka')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Vytvořeno')->hideOnForm();
    }
}
