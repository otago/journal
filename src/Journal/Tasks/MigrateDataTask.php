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
    protected $title       = 'Migrate Data Task';
    protected $description = 'Wipes all pages with progress on archiving, then imports Journal structure with progress reporting';

    public function run($request)
    {
        // 1) Purge everything with archiving progress
        $all       = SiteTree::get();
        $totalPurge = $all->count();
        $i = 0;
        foreach ($all as $old) {
            $i++;
            $cls   = $old->ClassName;
            $id    = $old->ID;
            $title = $old->Title;

            if ($old->hasExtension(Versioned::class)) {
                $old->doArchive();
                echo "[Archive {$i}/{$totalPurge}] 🗄️ Archived {$cls} #{$id} “{$title}”\n";
            }

            $old->delete();
            echo "[Delete  {$i}/{$totalPurge}] 🗑️ Deleted  {$cls} #{$id} “{$title}”\n";
        }
        echo "🔹 Cleared all existing pages\n";

        // 2) Load JSON dump
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

        // 3) Count total items to import
        $total = 1; // journal itself
        if (!empty($dump['Volumes'])) {
            foreach ($dump['Volumes'] as $vol) {
                $total++;
                if (!empty($vol['Issues'])) {
                    foreach ($vol['Issues'] as $iss) {
                        $total++;
                        if (!empty($iss['Articles'])) {
                            $total += count($iss['Articles']);
                        }
                    }
                }
            }
        }

        // 4) Import with manual IDs and progress
        $nextId  = 1;
        $current = 0;

        // Journal
        $current++;
        $journal = JournalPage::create();
        $journal->ID    = $nextId;
        $journal->Title = $dump['Title'] ?? 'Untitled Journal';
        $journal->write();
        if ($journal->hasExtension(Versioned::class)) {
            $journal->publishSingle();
        }
        echo "[{$current}/{$total}] {$nextId}️⃣ Journal #{$journal->ID} “{$journal->Title}” created\n";
        $nextId++;

        // Volumes → Issues → Articles
        if (!empty($dump['Volumes'])) {
            foreach ($dump['Volumes'] as $volData) {
                // Volume
                $current++;
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
                echo "[{$current}/{$total}] {$nextId}️⃣ Volume #{$vol->ID} “{$vol->Title}” created\n";
                $nextId++;

                if (!empty($volData['Issues'])) {
                    foreach ($volData['Issues'] as $issData) {
                        // Issue
                        $current++;
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
                        echo "[{$current}/{$total}] {$nextId}️⃣ Issue #{$iss->ID} “{$iss->Title}” created\n";
                        $nextId++;

                        if (!empty($issData['Articles'])) {
                            foreach ($issData['Articles'] as $artData) {
                                // Article
                                $current++;
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
                                echo "[{$current}/{$total}] {$nextId}️⃣ Article #{$art->ID} “{$art->Title}” created\n";
                                $nextId++;
                            }
                        }
                    }
                }
            }
        }

        // 5) Final summary
        $lastId = $nextId - 1;
        echo "🎉 Import complete: {$current}/{$total} items processed, IDs up to {$lastId}\n";
    }
}
