<?php

namespace OP\Journals\Admin;

use OP\Journals\Models\Journal;
use SilverStripe\Admin\ModelAdmin;

class Admin extends ModelAdmin
{
    private static $menu_title = 'Journals';
    private static $url_segment = 'journals';

    private static $menu_icon_class = 'font-icon-book';

    private static $managed_models = [
        Journal::class
    ];
}
