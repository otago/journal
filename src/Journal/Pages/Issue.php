<?php

namespace Journal\Pages;

use Page;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TextField;
use Journal\Models\Author;

class Issue extends Page
{
    private static $table_name = 'Journal_Pages_Issue';

    private static $db = [
        'Subtitle' => 'Varchar(255)',
        'DOI' => 'Varchar(255)',
        'Number' => 'Int',
        'Year' => 'Int',
    ];

    private static $belongs_many_many = [
        'Authors' => Author::class
    ];

    private static $has_one = [
        'File' => File::class,
        'Cover' => Image::class,
    ];

    private static $owns = [
        'File',
        'Cover'
    ];

    private static $allowed_children = [
        Article::class
    ];

    private static $can_be_root = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab(
            'Root.Issue',
            [
                UploadField::create('File'),
                UploadField::create('Cover'),
                TextField::create('Subtitle'),
                TextField::create('DOI'),
                TextField::create('Number'),
                TextField::create('Year')
            ]
        );

        $fields->addFieldToTab(
            'Root.Authors',
            GridField::create('Authors', 'Authors', $this->Authors())
                ->setConfig(GridFieldConfig_RelationEditor::create())
        );

        return $fields;
    }
}
