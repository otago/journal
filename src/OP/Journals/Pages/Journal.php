<?php

namespace OP\Journals\Pages;

use Page;
use SilverStripe\ORM\DataObject;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class Journal extends Page
{
    private static $table_name = 'OP_Journals_Journal';

    private static $singular_name = 'Journal';
    private static $plural_name = 'Journals';

    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_many = [
        'Volumes' => Volume::class
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'Volumes.Count' => 'Volumes'
    ];

    private static $allowed_children = [
        Volume::class
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $dataobject_fields = DataObject::getCMSFields();
        $volumes = $dataobject_fields->fieldByName("Root.Volumes.Volumes");
        if ($volumes) {
            $volumes->getConfig()->addComponent(new GridFieldOrderableRows());
            $fields->addFieldToTab("Root.Volumes", $volumes);
        }

        return $fields;
    }
}
