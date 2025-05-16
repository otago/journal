<?php

namespace Journal\Tasks;

use Journal\Pages\Journal as JournalPage;
use Journal\Pages\Volume as VolumePage;
use Journal\Pages\Issue as IssuePage;
use Journal\Pages\Article as ArticlePage;
use Journal\Models\Author;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Assets\File as AssetFile;
use SilverStripe\Assets\File;

class MigrateDataTask extends BuildTask
{
    protected $title       = 'Migrate Data Task';
    protected $description = 'Purges all content, including files, then imports Journal structure with Authors and local Article files';

    public function run($request)
    {
        // 0) Purge all File records
        $files      = File::get();
        $totalFiles = $files->count();
        echo "➡️ Purging {$totalFiles} File records...\n";
        foreach ($files as $i => $file) {
            $idx  = $i + 1;
            $name = $file->getFilename() ?: '[no filename]';
            if ($file->hasExtension(Versioned::class)) {
                echo "[{$idx}/{$totalFiles}] Archiving File #{$file->ID} “{$name}”…\n";
                $file->doArchive();
                echo "[{$idx}/{$totalFiles}] Archived\n";
            }
            echo "[{$idx}/{$totalFiles}] Deleting File #{$file->ID} “{$name}”…\n";
            $file->delete();
            echo "[{$idx}/{$totalFiles}] Deleted\n";
        }
        echo "✅ File records purged\n\n";

        // 1) Purge all pages
        $pages      = SiteTree::get();
        $totalPages = $pages->count();
        echo "➡️ Purging {$totalPages} pages...\n";
        foreach ($pages as $i => $page) {
            $idx   = $i + 1;
            $cls   = $page->ClassName;
            $id    = $page->ID;
            $title = $page->Title ?: '[no title]';

            if ($page->hasExtension(Versioned::class)) {
                echo "[{$idx}/{$totalPages}] Archiving {$cls} #{$id} “{$title}”…\n";
                $page->doArchive();
                echo "[{$idx}/{$totalPages}] Archived\n";
            }
            echo "[{$idx}/{$totalPages}] Deleting {$cls} #{$id}…\n";
            $page->delete();
            echo "[{$idx}/{$totalPages}] Deleted\n";
        }
        echo "✅ Pages purged\n\n";

        // 2) Purge all authors
        $authors   = Author::get();
        $totalAuth = $authors->count();
        echo "➡️ Purging {$totalAuth} authors...\n";
        foreach ($authors as $i => $a) {
            $idx  = $i + 1;
            $name = $a->Name() ?: '[no name]';
            if ($a->hasExtension(Versioned::class)) {
                echo "[{$idx}/{$totalAuth}] Archiving Author #{$a->ID} “{$name}”…\n";
                $a->doArchive();
                echo "[{$idx}/{$totalAuth}] Archived\n";
            }
            echo "[{$idx}/{$totalAuth}] Deleting Author #{$a->ID} “{$name}”…\n";
            $a->delete();
            echo "[{$idx}/{$totalAuth}] Deleted\n";
        }
        echo "✅ Authors purged\n\n";

        // 3) Load JSON
        $path = Environment::getEnv('SS_RECORDS_CACHE_FILE');
        if (!$path || !is_readable($path)) {
            echo "❌ Cannot read JSON at “{$path}”\n";
            return;
        }
        $dump = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "❌ JSON decode error: " . json_last_error_msg() . "\n";
            return;
        }
        echo "✅ Loaded JSON dump\n\n";

        // 4) Count items
        $count = 1; // journal
        foreach ($dump['Volumes'] ?? [] as $vol) {
            $count++;
            foreach ($vol['Issues'] ?? [] as $iss) {
                $count++;
                $count += count($iss['Authors'] ?? []);
                foreach ($iss['Articles'] ?? [] as $art) {
                    $count++;
                    $count += count($art['Authors'] ?? []);
                }
            }
        }
        echo "➡️ Importing {$count} items...\n";

        // 5) Import
        $current   = 0;
        $nextId    = 1;
        $assetsDir = rtrim(Environment::getEnv('SS_DUMP_ASSETS_DIR') ?: '', '/');

        // Journal
        $current++;
        $journal       = JournalPage::create();
        $journal->ID   = $nextId;
        $journal->Title = $dump['Title'] ?? 'Untitled Journal';
        $journal->write();
        if ($journal->hasExtension(Versioned::class)) {
            $journal->publishSingle();
        }
        echo "[{$current}/{$count}] Journal #{$nextId} “{$journal->Title}” created\n";
        $nextId++;

        // Volumes → Issues → Articles → Authors
        foreach ($dump['Volumes'] ?? [] as $volData) {
            // Volume
            $current++;
            $vol             = VolumePage::create();
            $vol->ID         = $nextId;
            $vol->ParentID   = $journal->ID;
            $vol->Title      = $volData['Title'] ?? 'Untitled Volume';
            $vol->URLSegment = $volData['URLSegment'] ?? null;
            $vol->write();
            if ($vol->hasExtension(Versioned::class)) {
                $vol->publishSingle();
            }
            echo "[{$current}/{$count}] Volume #{$nextId} “{$vol->Title}” created\n";
            $nextId++;

            foreach ($volData['Issues'] ?? [] as $issData) {
                // Issue
                $current++;
                $iss             = IssuePage::create();
                $iss->ID         = $nextId;
                $iss->ParentID   = $vol->ID;
                $iss->Title      = $issData['Title'] ?? 'Untitled Issue';
                $iss->Number     = $issData['Number'] ?? 0;
                $iss->URLSegment = $issData['URLSegment'] ?? null;
                $iss->write();
                if ($iss->hasExtension(Versioned::class)) {
                    $iss->publishSingle();
                }
                echo "[{$current}/{$count}] Issue #{$nextId} “{$iss->Title}” created\n";
                $nextId++;

                // Issue Authors
                foreach ($issData['Authors'] ?? [] as $idx => $authData) {
                    $current++;
                    $fn     = $authData['FirstName'] ?? '';
                    $sn     = $authData['Surname'] ?? '';
                    $or     = $authData['ORCID'] ?? '';
                    $filter = $or
                        ? ['ORCID' => $or]
                        : ['FirstName' => $fn, 'Surname' => $sn];
                    $author = Author::get()->filter($filter)->first()
                        ?: Author::create(['FirstName' => $fn, 'Surname' => $sn, 'ORCID' => $or]);
                    $author->write();
                    if ($author->hasExtension(Versioned::class)) {
                        $author->publishSingle();
                    }
                    $iss->Authors()->add($author, ['Sort' => $idx]);
                    echo "[{$current}/{$count}]  • Issue Author #{$author->ID} “{$author->Name()}” created\n";
                }

                // Articles
                foreach ($issData['Articles'] ?? [] as $artData) {
                    $current++;
                    $art             = ArticlePage::create();
                    $art->ID         = $nextId;
                    $art->ParentID   = $iss->ID;
                    $art->Title      = $artData['Title'] ?? 'Untitled Article';
                    $art->URLSegment = $artData['URLSegment'] ?? null;
                    $art->write();
                    if ($art->hasExtension(Versioned::class)) {
                        $art->publishSingle();
                    }
                    echo "[{$current}/{$count}] Article #{$nextId} “{$art->Title}” created\n";

                    // Local file attachment
                    if (!empty($artData['File']) && isset($artData['File']['LocalPath'])) {
                        $srcPath = $artData['File']['LocalPath'];
                        if (file_exists($srcPath)) {
                            $file = AssetFile::create();
                            $file->setFromLocalFile($srcPath, $artData['File']['Filename']);
                            $file->write();
                            if ($file->hasExtension(Versioned::class)) {
                                $file->publishSingle();
                            }
                            $art->FileID = $file->ID;
                            $art->write();
                            echo "[    ]   • Attached file #{$file->ID} “{$file->Filename}” from {$srcPath}\n";
                        } else {
                            echo "[    ]   ⚠️ Missing file at {$srcPath}\n";
                        }
                    }

                    $nextId++;

                    // Article Authors
                    foreach ($artData['Authors'] ?? [] as $idx => $authData) {
                        $current++;
                        $fn     = $authData['FirstName'] ?? '';
                        $sn     = $authData['Surname'] ?? '';
                        $or     = $authData['ORCID'] ?? '';
                        $filter = $or
                            ? ['ORCID' => $or]
                            : ['FirstName' => $fn, 'Surname' => $sn];
                        $author = Author::get()->filter($filter)->first()
                            ?: Author::create(['FirstName' => $fn, 'Surname' => $sn, 'ORCID' => $or]);
                        $author->write();
                        if ($author->hasExtension(Versioned::class)) {
                            $author->publishSingle();
                        }
                        $art->Authors()->add($author, ['Sort' => $idx]);
                        echo "[{$current}/{$count}]  • Article Author #{$author->ID} “{$author->Name()}” created\n";
                    }
                }
            }
        }

        echo "🎉 Import complete: {$current}/{$count} items processed\n";
    }
}
