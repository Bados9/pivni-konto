<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Beer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

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
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Název');
        yield TextField::new('brewery', 'Pivovar');
        yield TextField::new('style', 'Styl');
        yield NumberField::new('abv', 'ABV %')->setNumDecimals(1);
        yield TextField::new('logo', 'Logo URL')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Vytvořeno')->hideOnForm();
    }
}
