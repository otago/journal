<?php

namespace Journal\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\Versioned\Versioned;

class MigrateDataTask extends BuildTask
{
    protected $title = 'Migrate Data Task';
    protected $description = 'Archives then deletes all non-error pages from the SiteTree';

    /**
     * @param HTTPRequest|null $request
     */
    public function run($request)
    {
        // Get all pages except SilverStripe error pages
        $pages = SiteTree::get()->exclude('ClassName', ErrorPage::class);
        $count = $pages->count();

        if ($count === 0) {
            echo "ℹ️ No non-error pages found to archive and delete.\n";
            return;
        }

        // Archive and delete each page
        foreach ($pages as $page) {
            $class = $page->ClassName;
            $id    = $page->ID;

            if ($page->hasExtension(Versioned::class)) {
                // Archive this page (all its versions)
                $page->doArchive();
                echo "🗄️ Archived {$class} #{$id}\n";
            }

            // Now delete the record
            $page->delete();
            echo "🗑️ Deleted {$class} #{$id}\n";
        }

        echo "✅ Archived and deleted {$count} non-error page(s) from the site tree.\n";
    }
}
