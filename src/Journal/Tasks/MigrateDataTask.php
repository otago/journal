<?php

namespace Journal\Tasks;

use Journal\Pages\Journal as JournalPage;
use Journal\Pages\Volume as VolumePage;
use Journal\Pages\Issue as IssuePage;
use Journal\Pages\Article as ArticlePage;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Versioned\Versioned;

class MigrateDataTask extends BuildTask
{
    protected $title = 'Migrate Data Task';
    protected $description = 'Wipes all pages and recreates Journal, Volume, Issue & Article pages with manually assigned sequential IDs';

    public function run($request)
    {
        // 1) Tear down all existing pages
        $all = SiteTree::get();
        foreach ($all as $old) {
            if ($old->hasExtension(Versioned::class)) {
                $old->doArchive();
            }
            $old->delete();
        }
        echo "🔹 Cleared all existing pages\n";

        // 2) Load the JSON dump
        $path = Environment::getEnv('SS_RECORDS_CACHE_FILE');
        if (!$path || !file_exists($path) || !is_readable($path)) {
            echo "❗ Cannot read JSON file at {$path}\n";
            return;
        }
        $dump = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "❗ JSON decode error: " . json_last_error_msg() . "\n";
            return;
        }

        // 3) Start manual ID counter
        $nextId = 1;

        // 4) Create Journal page with ID = 1
        $journal = JournalPage::create();
        $journal->ID    = $nextId;
        $journal->Title = $dump['Title'] ?? 'Untitled Journal';
        $journal->write();
        if ($journal->hasExtension(Versioned::class)) {
            $journal->publishSingle();
        }
        echo "{$nextId}️⃣ Created Journal #{$journal->ID}\n";
        $nextId++;

        // 5) Create Volume pages
        if (!empty($dump['Volumes']) && is_array($dump['Volumes'])) {
            foreach ($dump['Volumes'] as $volData) {
                $vol = VolumePage::create();
                $vol->ID        = $nextId;
                $vol->ParentID  = $journal->ID;
                $vol->Title     = $volData['Title'] ?? 'Untitled Volume';
                if (isset($volData['URLSegment'])) {
                    $vol->URLSegment = $volData['URLSegment'];
                }
                $vol->write();
                if ($vol->hasExtension(Versioned::class)) {
                    $vol->publishSingle();
                }
                echo "{$nextId}️⃣ Created Volume #{$vol->ID}\n";
                $nextId++;

                // 6) Create Issue pages
                if (!empty($volData['Issues']) && is_array($volData['Issues'])) {
                    foreach ($volData['Issues'] as $issData) {
                        $iss = IssuePage::create();
                        $iss->ID         = $nextId;
                        $iss->ParentID   = $vol->ID;
                        $iss->Title      = $issData['Title'] ?? 'Untitled Issue';
                        if (isset($issData['Number'])) {
                            $iss->Number = $issData['Number'];
                        }
                        if (isset($issData['URLSegment'])) {
                            $iss->URLSegment = $issData['URLSegment'];
                        }
                        $iss->write();
                        if ($iss->hasExtension(Versioned::class)) {
                            $iss->publishSingle();
                        }
                        echo "{$nextId}️⃣ Created Issue #{$iss->ID}\n";
                        $nextId++;

                        // 7) Create Article pages
                        if (!empty($issData['Articles']) && is_array($issData['Articles'])) {
                            foreach ($issData['Articles'] as $artData) {
                                $art = ArticlePage::create();
                                $art->ID        = $nextId;
                                $art->ParentID  = $iss->ID;
                                $art->Title     = $artData['Title'] ?? 'Untitled Article';
                                if (isset($artData['URLSegment'])) {
                                    $art->URLSegment = $artData['URLSegment'];
                                }
                                $art->write();
                                if ($art->hasExtension(Versioned::class)) {
                                    $art->publishSingle();
                                }
                                echo "{$nextId}️⃣ Created Article #{$art->ID}\n";
                                $nextId++;
                            }
                        }
                    }
                }
            }
        }

        // 8) Final summary
        $lastId = $nextId - 1;
        echo "🎉 All pages created with manual sequential IDs up to {$lastId}\n";
    }
}
