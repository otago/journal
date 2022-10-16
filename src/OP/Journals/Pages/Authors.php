<?php

namespace OP\Journals\Pages;

use Page;
use SilverStripe\ORM\DataObject;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class Authors extends Page
{
    private static $table_name = 'OP_Journals_Pages_Authors';

    private static $has_many = [
        'Authors' => Author::class
    ];

    private static $allowed_children = [
        Author::class
    ];

    private static $can_be_root = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $dataobject_fields = DataObject::getCMSFields();
        $authors = $dataobject_fields->fieldByName("Root.Authors.Authors");
        if ($authors) {
            $authors->getConfig()->addComponent(new GridFieldOrderableRows());
            $fields->addFieldToTab("Root.Authors", $authors);
        }

        return $fields;
    }
}
