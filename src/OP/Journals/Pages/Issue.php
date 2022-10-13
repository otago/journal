<?php

namespace OP\Journals\Pages;

use Page;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class Issue extends Page
{
    private static $table_name = 'OP_Journals_Issue';

    private static $singular_name = 'Issue';
    private static $plural_name = 'Issues';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Subtitle' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'DOI' => 'Varchar(255)',
        'Number' => 'Int',
        'Year' => 'Int',
        'Content' => 'HTMLText'
    ];

    private static $many_many_extraFields = [
        'Authors' => [
            'Sort' => 'Int'
        ]
    ];

    private static $has_one = [
        'File' => File::class,
        'Cover' => Image::class,
        'Parent' => Volume::class
    ];

    private static $has_many = [
        'Articles' => Article::class
    ];

    private static $owns = [
        'File',
        'Cover'
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'URLSegment' => 'URL Segment',
        'DOI' => 'DOI',
        'Number' => 'Number',
        'Year' => 'Year',
        'Articles.Count' => 'Articles'
    ];

    private static $allowed_children = [
        Article::class
    ];

    private static $default_sort = 'Number ASC';

    private static $can_be_root = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $grid_field = $fields->fieldByName("Root.Authors.Authors");
        if ($grid_field) {
            $grid_field->getConfig()->addComponent(new GridFieldOrderableRows());
        }

        $grid_field = $fields->fieldByName("Root.Articles.Articles");
        if ($grid_field) {
            $grid_field->getConfig()->addComponent(new GridFieldOrderableRows());
        }

        return $fields;
    }
}
