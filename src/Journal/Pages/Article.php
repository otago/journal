<?php

namespace Journal\Pages;

use Page;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TextField;
use Journal\Models\Author;

class Article extends Page
{
    private static $table_name = 'Journal_Pages_Article';

    private static $db = [
        'DOI' => 'Varchar(255)',
    ];

    private static $belongs_many_many = [
        'Authors' => Author::class
    ];

    private static $has_one = [
        'File' => File::class
    ];

    private static $owns = [
        'File'
    ];

    private static $allowed_children = [];

    private static $can_be_root = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab(
            'Root.Article',
            [
                UploadField::create('File'),
                TextField::create('DOI'),
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
