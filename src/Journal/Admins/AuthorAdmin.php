<?php

namespace Journal\Admins;

use SilverStripe\Admin\ModelAdmin;
use Journal\Models\Author;

class Admin extends ModelAdmin
{
    private static $menu_title = 'Authors';
    private static $url_segment = 'authors';

    private static $menu_icon_class = 'font-icon-book';

    private static $managed_models = [
        Author::class
    ];
}
