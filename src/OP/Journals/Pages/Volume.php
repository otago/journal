<?php

namespace OP\Journals\Pages;

use Page;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class Volume extends Page
{
    private static $table_name = 'OP_Journals_Volume';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'Content' => 'HTMLText'
    ];

    private static $has_one = [
        'Cover' => Image::class,
        'Image' => Image::class,
        'Parent' => Journal::class
    ];

    private static $has_many = [
        'Issues' => Issue::class
    ];

    private static $owns = [
        'Cover',
        'Image'
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'URLSegment' => 'URL Segment',
        'Issues.Count' => 'Issues'
    ];

    private static $allowed_children = [
        Issue::class
    ];

    private static $can_be_root = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $dataobject_fields = DataObject::getCMSFields();

        $fields->addFieldsToTab(
            "Root.Main",
            [
                $dataobject_fields->fieldByName("Root.Main.Image"),
                $dataobject_fields->fieldByName("Root.Main.Cover")
            ],
            "Content"
        );

        $issues = $dataobject_fields->fieldByName("Root.Issues.Issues");
        if ($issues) {
            $fields->addFieldToTab("Root.Issues", $issues);
        }

        return $fields;
    }
}
