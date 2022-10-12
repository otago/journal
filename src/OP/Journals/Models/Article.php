<?php

namespace OP\Journals\Models;

use OP\Journals\ArticleType;
use OP\Journals\Traits\JournalTrait;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Article extends DataObject
{
    use JournalTrait;

    private static $table_name = 'OP_Journals_Article';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'DOI' => 'Varchar(255)',
        'Content' => 'HTMLText',
    ];

    private static $belongs_many_many = [
        'Types' => ArticleType::class,
        'Authors' => Author::class
    ];

    private static $many_many_extraFields = [
        'Authors' => [
            'Sort' => 'Int'
        ],
        'Types' => [
            'Sort' => 'Int'
        ],
    ];

    private static $has_one = [
        'File' => File::class
    ];

    private static $owns = [
        'File'
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'Links' => 'Links',
    ];
}
