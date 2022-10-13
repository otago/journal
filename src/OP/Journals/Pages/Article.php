<?php

namespace OP\Journals\Pages;

use OP\Journals\Models\ArticleType;
use OP\Journals\Models\Author;
use Page;
use SilverStripe\Assets\File;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class Article extends Page
{
    private static $table_name = 'OP_Journals_Article';

    private static $singular_name = 'Article';
    private static $plural_name = 'Articles';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'DOI' => 'Varchar(255)',
        'Content' => 'HTMLText',
    ];

    private static $has_one = [
        'File' => File::class,
        'Issue' => Issue::class
    ];

    private static $many_many = [
        'Types' => ArticleType::class
    ];

    private static $belongs_many_many = [
        'Authors' => Author::class
    ];

    private static $many_many_extraFields = [
        'Authors' => [
            'Sort' => 'Int'
        ]
    ];

    private static $owns = [
        'File'
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'URLSegment' => 'URL Segment',
        'DOI' => 'DOI'
    ];

    private static $can_be_root = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $grid_field = $fields->fieldByName("Root.Authors.Authors");
        if ($grid_field) {
            $grid_field->getConfig()->addComponent(new GridFieldOrderableRows());
        }

        return $fields;
    }
}
