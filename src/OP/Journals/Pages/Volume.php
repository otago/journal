<?php

namespace OP\Journals\Pages;

use Page;
use SilverStripe\Assets\Image;

class Volume extends Page
{
    private static $table_name = 'OP_Journals_Volume';

    private static $db = [
        'Title' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'Content' => 'HTMLText'
    ];

    private static $has_one = [
        'Cover' => Image::class,
        'Image' => Image::class,
        'Parent' => Journal::class
    ];

    private static $has_many = [
        'Issues' => Issue::class
    ];

    private static $owns = [
        'Cover',
        'Image'
    ];

    private static $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'URLSegment' => 'URL Segment',
        'Issues.Count' => 'Issues'
    ];

    private static $allowed_children = [
        Issue::class
    ];

    private static $can_be_root = false;
}
