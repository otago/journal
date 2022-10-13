<?php

namespace OP\Journals\Models;

use OP\Journals\Traits\JournalTrait;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class Article extends DataObject
{
    use JournalTrait;

    private static $table_name = 'OP_Journals_Article';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'DOI' => 'Varchar(255)',
        'Content' => 'HTMLText',
    ];

    private static $many_many = [
        'Types' => ArticleType::class
    ];

    private static $belongs_many_many = [
        'Authors' => Author::class,
        'Issues' => Issue::class
    ];

    private static $many_many_extraFields = [
        'Authors' => [
            'Sort' => 'Int'
        ]
    ];

    private static $has_one = [
        'File' => File::class
    ];

    private static $owns = [
        'File'
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'URLSegment' => 'URL Segment',
        'DOI' => 'DOI'
    ];

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
