<?php

namespace OP\Journals\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Journal extends DataObject
{
    private static $table_name = 'OP_Journals_Journal';

    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $many_many = [
        'Volumes' => Volume::class
    ];

    private static $many_many_extraFields = [
        'Volumes' => [
            'Sort' => 'Int'
        ]
    ];

    private static $cascade_deletes = [
        'Volumes',
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title'
    ];
}
