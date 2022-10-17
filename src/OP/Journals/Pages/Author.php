<?php

namespace OP\Journals\Pages;

use OP\Journals\Pages\Article;
use OP\Journals\Pages\Issue;
use Page;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;

class Author extends Page
{
    private static $table_name = 'OP_Journals_Pages_Author';

    private static $db = [
        'FirstName' => 'Varchar(255)',
        'Surname' => 'Varchar(255)'
    ];

    private static $has_one = [
        'Parent' => Authors::class
    ];

    private static $belongs_many_many = [
        'Issues' => Issue::class,
        'Articles' => Article::class
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'FirstName' => 'First Name',
        'Surname' => 'Surname',
        'Issues.Count' => 'Issues',
        'Articles.Count' => 'Articles'
    ];

    private static $allowed_children = [];

    private static $can_be_root = false;

    private static $default_sort = "Surname ASC";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->fieldByName("Root.Main.Title")->setDisabled(true);
        $fields->fieldByName("Root.Main.MenuTitle")->setDisabled(true);

        $fields->addFieldsToTab(
            'Root.Main',
            [
                DropdownField::create('ParentID', 'Parent', Authors::get()->map()),
                TextField::create('FirstName'),
                TextField::create('Surname')
            ],
            "Content"
        );

        return $fields;
    }

    public function onBeforeWrite()
    {
        $this->Title = "$this->FirstName $this->Surname";
        $this->MenuTitle = $this->Title;

        parent::onBeforeWrite();
    }
}
