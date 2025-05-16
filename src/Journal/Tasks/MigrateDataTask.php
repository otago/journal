<?php

namespace Journal\Tasks;

use Journal\Pages\Journal as JournalPage;
use Journal\Pages\Volume as VolumePage;
use Journal\Pages\Issue as IssuePage;
use Journal\Pages\Article as ArticlePage;
use Journal\Models\Author;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Environment;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Assets\File as AssetFile;
use SilverStripe\Assets\Image as AssetImage;

class MigrateDataTask extends BuildTask
{
    protected $title       = 'Migrate Data Task';
    protected $description = 'Purges all content, then imports Journal structure with Authors plus assets, publishing after assets';

    public function run($request)
    {
        // 0) Purge all File records
        $allFiles   = AssetFile::get();
        $totalFiles = $allFiles->count();
        echo "➡️ Purging {$totalFiles} File records...\n";
        foreach ($allFiles as $i => $file) {
            $idx  = $i + 1;
            $name = $file->getFilename() ?: '[no filename]';
            if ($file->hasExtension(Versioned::class)) {
                echo "[{$idx}/{$totalFiles}] Archiving File #{$file->ID} “{$name}”…\n";
                $file->doArchive();
            }
            echo "[{$idx}/{$totalFiles}] Deleting File #{$file->ID}…\n";
            $file->delete();
        }
        echo "✅ File records purged\n\n";

        // 1) Purge all pages
        $pages      = SiteTree::get();
        $totalPages = $pages->count();
        echo "➡️ Purging {$totalPages} pages...\n";
        foreach ($pages as $i => $page) {
            $idx = $i + 1;
            if ($page->hasExtension(Versioned::class)) {
                echo "[{$idx}/{$totalPages}] Archiving {$page->ClassName} #{$page->ID}…\n";
                $page->doArchive();
            }
            echo "[{$idx}/{$totalPages}] Deleting page #{$page->ID}…\n";
            $page->delete();
        }
        echo "✅ Pages purged\n\n";

        // 2) Purge all authors
        $authors   = Author::get();
        $totalAuth = $authors->count();
        echo "➡️ Purging {$totalAuth} authors...\n";
        foreach ($authors as $i => $a) {
            $idx = $i + 1;
            if ($a->hasExtension(Versioned::class)) {
                echo "[{$idx}/{$totalAuth}] Archiving Author #{$a->ID}…\n";
                $a->doArchive();
            }
            echo "[{$idx}/{$totalAuth}] Deleting Author #{$a->ID}…\n";
            $a->delete();
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

        // 4) Count items safely
        $totalItems = 1; // journal
        foreach ($dump['Volumes'] ?? [] as $v) {
            $totalItems++;
            foreach ($v['Issues'] ?? [] as $i) {
                $totalItems++;
                $totalItems += count($i['Authors'] ?? []);
                foreach ($i['Articles'] ?? [] as $a) {
                    $totalItems++;
                    $totalItems += count($a['Authors'] ?? []);
                }
            }
        }
        echo "➡️ Importing {$totalItems} items...\n";

        // 5) Import workflow
        $current    = 0;
        $nextPageID = 1;
        $assetsDir  = rtrim(Environment::getEnv('SS_DUMP_ASSETS_DIR') ?: '', '/');

        // Journal
        $current++;
        $journal        = JournalPage::create();
        $journal->ID    = $nextPageID;
        $journal->Title = $dump['Title'] ?? 'Untitled Journal';
        $journal->write();
        if ($journal->hasExtension(Versioned::class)) {
            $journal->publishSingle();
        }
        echo "[{$current}/{$totalItems}] Journal created (#{$nextPageID})\n";
        $nextPageID++;

        // Volumes → Issues → Articles → Authors
        foreach ($dump['Volumes'] ?? [] as $volData) {
            // Volume
            $current++;
            $vol             = VolumePage::create();
            $vol->ID         = $nextPageID;
            $vol->ParentID   = $journal->ID;
            $vol->Title      = $volData['Title'] ?? '';
            $vol->URLSegment = $volData['URLSegment'] ?? '';
            $vol->write();

            // Cover
            if (!empty($volData['Cover']['LocalPath'])) {
                $src = $volData['Cover']['LocalPath'];
                if (file_exists($src)) {
                    $img = AssetImage::create();
                    $img->setFromLocalFile($src, basename($src));
                    $img->write();
                    if ($img->hasExtension(Versioned::class)) {
                        $img->publishSingle();
                    }
                    $vol->CoverID = $img->ID;
                    $vol->write();
                }
            }

            // Image
            if (!empty($volData['Image']['LocalPath'])) {
                $src = $volData['Image']['LocalPath'];
                if (file_exists($src)) {
                    $img = AssetImage::create();
                    $img->setFromLocalFile($src, basename($src));
                    $img->write();
                    if ($img->hasExtension(Versioned::class)) {
                        $img->publishSingle();
                    }
                    $vol->ImageID = $img->ID;
                    $vol->write();
                }
            }

            // Publish Volume
            if ($vol->hasExtension(Versioned::class)) {
                $vol->publishSingle();
            }
            echo "[{$current}/{$totalItems}] Volume created (#{$nextPageID})\n";
            $nextPageID++;

            // Issues
            foreach ($volData['Issues'] ?? [] as $issData) {
                $current++;
                $iss             = IssuePage::create();
                $iss->ID         = $nextPageID;
                $iss->ParentID   = $vol->ID;
                $iss->Title      = $issData['Title'] ?? '';
                $iss->Number     = $issData['Number'] ?? 0;
                $iss->URLSegment = $issData['URLSegment'] ?? '';
                $iss->write();

                // File
                if (!empty($issData['File']['LocalPath'])) {
                    $src = $issData['File']['LocalPath'];
                    if (file_exists($src)) {
                        $file = AssetFile::create();
                        $file->setFromLocalFile($src, basename($src));
                        $file->write();
                        if ($file->hasExtension(Versioned::class)) {
                            $file->publishSingle();
                        }
                        $iss->FileID = $file->ID;
                        $iss->write();
                    }
                }

                // Cover
                if (!empty($issData['Cover']['LocalPath'])) {
                    $src = $issData['Cover']['LocalPath'];
                    if (file_exists($src)) {
                        $img = AssetImage::create();
                        $img->setFromLocalFile($src, basename($src));
                        $img->write();
                        if ($img->hasExtension(Versioned::class)) {
                            $img->publishSingle();
                        }
                        $iss->CoverID = $img->ID;
                        $iss->write();
                    }
                }

                // Publish Issue
                if ($iss->hasExtension(Versioned::class)) {
                    $iss->publishSingle();
                }
                echo "[{$current}/{$totalItems}] Issue created (#{$nextPageID})\n";
                $nextPageID++;

                // Issue Authors
                foreach ($issData['Authors'] ?? [] as $idx => $authData) {
                    $current++;
                    $fn     = $authData['FirstName'] ?? '';
                    $sn     = $authData['Surname']  ?? '';
                    $or     = $authData['ORCID']    ?? '';
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
                }

                // Articles
                foreach ($issData['Articles'] ?? [] as $artData) {
                    $current++;
                    $art             = ArticlePage::create();
                    $art->ID         = $nextPageID;
                    $art->ParentID   = $iss->ID;
                    $art->Title      = $artData['Title'] ?? '';
                    $art->URLSegment = $artData['URLSegment'] ?? '';
                    $art->write();

                    // File
                    if (!empty($artData['File']['LocalPath'])) {
                        $src = $artData['File']['LocalPath'];
                        if (file_exists($src)) {
                            $file = AssetFile::create();
                            $file->setFromLocalFile($src, $artData['File']['Filename']);
                            $file->write();
                            if ($file->hasExtension(Versioned::class)) {
                                $file->publishSingle();
                            }
                            $art->FileID = $file->ID;
                            $art->write();
                        }
                    }

                    // Publish Article
                    if ($art->hasExtension(Versioned::class)) {
                        $art->publishSingle();
                    }
                    echo "[{$current}/{$totalItems}] Article created (#{$nextPageID})\n";
                    $nextPageID++;

                    // Article Authors
                    foreach ($artData['Authors'] ?? [] as $idx => $authData) {
                        $current++;
                        $fn     = $authData['FirstName'] ?? '';
                        $sn     = $authData['Surname']  ?? '';
                        $or     = $authData['ORCID']    ?? '';
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
                    }
                }
            }
        }

        echo "🎉 Import complete: {$current}/{$totalItems} items processed\n";
    }
}
