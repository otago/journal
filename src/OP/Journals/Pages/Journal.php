<?php

namespace OP\Journals\Pages;

use Page;

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
}
