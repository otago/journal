<?php

namespace OP\Journals\Models;

use OP\Journals\Traits\JournalTrait;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Author extends DataObject
{
    use JournalTrait;

    private static $table_name = 'OP_Journals_Author';

    private static $db = [
        'FirstName' => 'Varchar(255)',
        'Surname' => 'Varchar(255)'
    ];

    private static $many_many = [
        'Issues' => Issue::class,
        'Articles' => Article::class
    ];

    private static $many_many_extraFields = [
        'Issues' => [
            'Sort' => 'Int'
        ],
        'Articles' => [
            'Sort' => 'Int'
        ]
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'FirstName' => 'First Name',
        'Surname' => 'Surname',
        'Issues.Count' => 'Issues',
        'Articles.Count' => 'Articles'
    ];

    private static $default_sort = "Surname ASC";
}
