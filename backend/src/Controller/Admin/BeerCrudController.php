<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Beer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class BeerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Beer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Pivo')
            ->setEntityLabelInPlural('Piva')
            ->setDefaultSort(['status' => 'ASC', 'name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Název');
        yield TextField::new('brewery', 'Pivovar');
        yield TextField::new('style', 'Styl');
        yield NumberField::new('abv', 'ABV %')->setNumDecimals(1);
        yield ChoiceField::new('status', 'Status')
            ->setChoices([
                'Čeká na schválení' => 'pending',
                'Schváleno' => 'approved',
                'Zamítnuto' => 'rejected',
            ])
            ->renderAsBadges([
                'pending' => 'warning',
                'approved' => 'success',
                'rejected' => 'danger',
            ]);
        yield AssociationField::new('submittedBy', 'Navrhl')->hideOnForm();
        yield TextField::new('logo', 'Logo URL')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Vytvořeno')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Status')->setChoices([
                'Čeká na schválení' => 'pending',
                'Schváleno' => 'approved',
                'Zamítnuto' => 'rejected',
            ]));
    }
}
