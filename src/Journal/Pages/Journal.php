<?php

namespace Journal\Pages;

use Page;
use Journal\Controllers\JournalController;

class Journal extends Page
{
    private static $table_name = 'Journal_Pages_Journal';

    private static $allowed_children = [
        Volume::class
    ];

    public function getControllerName()
    {
        return JournalController::class;
    }
}
