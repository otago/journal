<?php

namespace OP\Journals\Models;

use OP\Journals\Traits\JournalTrait;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class Volume extends DataObject
{
    use JournalTrait;

    private static $table_name = 'OP_Journals_Volume';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'Content' => 'HTMLText'
    ];

    private static $many_many = [
        'Issues' => Issue::class,
        'Journals' => Journal::class
    ];

    private static $has_one = [
        'Cover' => Image::class,
        'Image' => Image::class,
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
        'Title' => 'Title',
        'URLSegment' => 'URL Segment',
        'Issues.Count' => 'Issues'
    ];
}
