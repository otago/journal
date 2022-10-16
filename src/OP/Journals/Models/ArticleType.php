<?php

namespace OP\Journals\Models;

use OP\Journals\Pages\Article;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class ArticleType extends DataObject
{
    private static $table_name = 'OP_Journals_Models_ArticleType';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $belongs_many_many = [
        'Articles' => Article::class
    ];

    private static $many_many_extraFields = [
        'Articles' => [
            'ArticleTypeSort' => 'Int'
        ],
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'Articles.Count' => 'Articles'
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $default_sort = "Title ASC";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $dataobject_fields = DataObject::getCMSFields();
        $volumes = $dataobject_fields->fieldByName("Root.Articles.Articles");
        if ($volumes) {
            $volumes->getConfig()->addComponent(new GridFieldOrderableRows('ArticleTypeSort'));
            $fields->addFieldToTab("Root.Articles", $volumes);
        }

        return $fields;
    }
}
