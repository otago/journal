<?php

namespace OP\Journals\Pages;

use Page;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataObject;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class Issue extends Page
{
    private static $table_name = 'OP_Journals_Pages_Issue';

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

    private static $has_one = [
        'File' => File::class,
        'Cover' => Image::class,
        'Parent' => Volume::class
    ];

    private static $has_many = [
        'Articles' => Article::class
    ];

    private static $many_many = [
        'Authors' => Author::class
    ];

    private static $many_many_extraFields = [
        'Authors' => [
            'IssueSort' => 'Int'
        ]
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

        $dataobject_fields = DataObject::getCMSFields();

        $fields->addFieldsToTab(
            "Root.Main",
            [
                $dataobject_fields->fieldByName("Root.Main.Subtitle"),
            ],
            "URLSegment"
        );

        $fields->addFieldsToTab(
            "Root.Main",
            [
                DropdownField::create('ParentID', 'Parent', Volume::get()->map()),
                $dataobject_fields->fieldByName("Root.Main.DOI"),
                $dataobject_fields->fieldByName("Root.Main.Number"),
                $dataobject_fields->fieldByName("Root.Main.Year"),
                $dataobject_fields->fieldByName("Root.Main.File"),
                $dataobject_fields->fieldByName("Root.Main.Cover"),
            ],
            "Content"
        );

        $articles = $dataobject_fields->fieldByName("Root.Articles.Articles");
        if ($articles) {
            $articles->getConfig()->addComponent(new GridFieldOrderableRows());
            $fields->addFieldToTab("Root.Articles", $articles);
        }

        $authors = $dataobject_fields->fieldByName("Root.Authors.Authors");
        if ($authors) {
            $authors->getConfig()->addComponent(new GridFieldOrderableRows('IssueSort'));
            $fields->addFieldToTab("Root.Authors", $authors);
        }

        return $fields;
    }
}
