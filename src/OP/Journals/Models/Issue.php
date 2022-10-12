<?php

namespace OP\Journals\Models;

use OP\Journals\Traits\JournalTrait;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Issue extends DataObject
{
    use JournalTrait;

    private static $table_name = 'OP_Journals_Issue';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Subtitle' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'DOI' => 'Varchar(255)',
        'Number' => 'Int',
        'Year' => 'Int',
        'Content' => 'HTMLText'
    ];

    private static $many_many = [
        'Articles' => Article::class,
    ];

    private static $belongs_many_many = [
        'Authors' => Author::class
    ];

    private static $many_many_extraFields = [
        'Articles' => [
            'Sort' => 'Int'
        ],
        'Authors' => [
            'Sort' => 'Int'
        ]
    ];

    private static $has_one = [
        'File' => File::class,
        'Cover' => Image::class,
    ];

    private static $owns = [
        'Cover',
        'Image'
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Number' => 'Number',
        'Title' => 'Title',
        'Links' => 'Links',
        'AuthorsSummary' => 'Authors'
    ];
}
