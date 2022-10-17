<?php

namespace OP\Journals\Admin;

use OP\Journals\Pages\Article;
use OP\Journals\Models\ArticleType;
use OP\Journals\Pages\Author;
use OP\Journals\Pages\Issue;
use OP\Journals\Pages\Journal;
use OP\Journals\Pages\Volume;
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
        Author::class,
        ArticleType::class,
    ];
}
