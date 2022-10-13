<?php

namespace OP\Journals\Admin;

use OP\Journals\Models\Article;
use OP\Journals\Models\ArticleType;
use OP\Journals\Models\Author;
use OP\Journals\Models\Issue;
use OP\Journals\Models\Journal;
use OP\Journals\Models\Volume;
use SilverStripe\Admin\ModelAdmin;

class Admin extends ModelAdmin
{
    private static $menu_title = 'Journals';
    private static $url_segment = 'journals';

    private static $menu_icon_class = 'font-icon-book';

    private static $managed_models = [
        Journal::class,
        Volume::class,
        Issue::class,
        Article::class,
        ArticleType::class,
        Author::class
    ];
}
