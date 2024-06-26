<?php

namespace Journal\Pages;

use Page;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;

class Volume extends Page
{
    private static $table_name = 'Journal_Pages_Volume';

    private static $has_one = [
        'Cover' => Image::class,
        'Image' => Image::class,
    ];

    private static $owns = [
        'Cover',
        'Image'
    ];

    private static $allowed_children = [
        Issue::class
    ];

    private static $can_be_root = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab(
            'Root.Volume',
            [
                UploadField::create('Cover'),
                UploadField::create('Image'),
            ]
        );

        return $fields;
    }
}
