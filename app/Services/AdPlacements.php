<?php
declare(strict_types=1);
namespace App\Services;

final class AdPlacements
{
    public static function definitions(): array
    {
        return [
            'header'=>['Site header','All public pages, below navigation',970,90,'all'],
            'footer'=>['Site footer','All public pages, before the footer',970,90,'all'],
            'homepage-top'=>['Homepage top','Before the edition cover',970,90,'home'],
            'homepage-middle'=>['Homepage middle','Between latest stories and category sections',970,250,'home'],
            'homepage-bottom'=>['Homepage bottom','After category sections',970,250,'home'],
            'sidebar'=>['Homepage sidebar','Beside latest stories on desktop',300,600,'home'],
            'category-top'=>['Category top','Before the category lead story',970,90,'category'],
            'category-page'=>['Category sidebar','Beside the category article grid on desktop',300,600,'category'],
            'category-bottom'=>['Category bottom','After category pagination',970,250,'category'],
            'article-top'=>['Article top','Between headline and lead image',970,90,'article'],
            'article-middle'=>['Article in-body','Inside longer articles; after the body on short stories',336,280,'article'],
            'article-sidebar'=>['Article sidebar','Beside the reading column on desktop',300,600,'article'],
            'article-bottom'=>['Article bottom','After the article and related navigation',970,250,'article'],
            'archive-top'=>['Topic / author top','Before topic and author archives',970,90,'archive'],
            'archive-sidebar'=>['Topic / author sidebar','Beside topic and author article grids',300,600,'archive'],
            'archive-bottom'=>['Topic / author bottom','After topic and author archives',970,250,'archive'],
            'page-top'=>['Static page top','Before about, contact and policy pages',970,90,'page'],
            'page-bottom'=>['Static page bottom','After about, contact and policy pages',970,250,'page'],
            'search-top'=>['Search top','Above search results',970,90,'search'],
            'search-bottom'=>['Search bottom','Below search results',970,250,'search'],
        ];
    }
}
