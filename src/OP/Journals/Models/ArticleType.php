<?php

namespace OP\Journals\Models;

use OP\Journals\Pages\Article;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class ArticleType extends DataObject
{
    private static $table_name = 'OP_Journals_ArticleType';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $belongs_many_many = [
        'Articles' => Article::class
    ];

    private static $many_many_extraFields = [
        'Articles' => [
            'Sort' => 'Int'
        ],
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'Articles.COUNT' => 'Articles'
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $default_sort = "Title ASC";
}
