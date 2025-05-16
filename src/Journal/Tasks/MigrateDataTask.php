<?php

namespace Journal\Tasks;

use Journal\Pages\Journal as JournalPage;
use Journal\Pages\Volume as VolumePage;
use Journal\Pages\Issue as IssuePage;
use Journal\Pages\Article as ArticlePage;
use Journal\Models\Author;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Assets\File as AssetFile;
use SilverStripe\Assets\Image as AssetImage;
use SilverStripe\Assets\Folder;

class MigrateDataTask extends BuildTask
{
    protected $title       = 'Migrate Data Task';
    protected $description = 'Purges all content, then imports Journal structure with cached fields and assets, preserving original IDs';

    /** Keys to skip when reapplying cached data */
    private $skipFields = [
        'ID',
        'ClassName',
        'RecordClassName',
        'Created',
        'LastEdited',
        'Version',
        'Cover',
        'Image',
        'File',
        'Issues',
        'Articles',
        'Authors'
    ];

    public function run($request)
    {
        // 0) Purge all File records
        $allFiles   = AssetFile::get();
        $totalFiles = $allFiles->count();
        echo "➡️ Purging {$totalFiles} File records...\n";
        foreach ($allFiles as $i => $file) {
            $idx  = $i + 1;
            $name = $file->getFilename() ?: '[no filename]';
            echo "[{$idx}/{$totalFiles}] ";
            if ($file->hasExtension(Versioned::class)) {
                echo "Archiving File #{$file->ID} “{$name}”… ";
                $file->doArchive();
                echo "archived\n";
            }
            echo "[{$idx}/{$totalFiles}] Deleting File #{$file->ID}… ";
            $file->delete();
            echo "deleted\n";
        }
        echo "✅ File records purged\n\n";

        // 1) Purge all pages
        $allPages   = SiteTree::get();
        $totalPages = $allPages->count();
        echo "➡️ Purging {$totalPages} pages...\n";
        foreach ($allPages as $i => $page) {
            $idx   = $i + 1;
            $title = $page->Title ?: '[no title]';
            echo "[{$idx}/{$totalPages}] ";
            if ($page->hasExtension(Versioned::class)) {
                echo "Archiving {$page->ClassName} #{$page->ID} “{$title}”… ";
                $page->doArchive();
                echo "archived\n";
            }
            echo "[{$idx}/{$totalPages}] Deleting {$page->ClassName} #{$page->ID}… ";
            $page->delete();
            echo "deleted\n";
        }
        echo "✅ Pages purged\n\n";

        // 2) Purge all authors
        $allAuthors = Author::get();
        $totalAuth  = $allAuthors->count();
        echo "➡️ Purging {$totalAuth} authors...\n";
        foreach ($allAuthors as $i => $a) {
            $idx  = $i + 1;
            $name = $a->Name() ?: '[no name]';
            echo "[{$idx}/{$totalAuth}] ";
            if ($a->hasExtension(Versioned::class)) {
                echo "Archiving Author #{$a->ID} “{$name}”… ";
                $a->doArchive();
                echo "archived\n";
            }
            echo "[{$idx}/{$totalAuth}] Deleting Author #{$a->ID}… ";
            $a->delete();
            echo "deleted\n";
        }
        echo "✅ Authors purged\n\n";

        // 3) Load JSON dump
        $path = Environment::getEnv('SS_RECORDS_CACHE_FILE');
        if (!$path || !is_readable($path)) {
            echo "❌ Cannot read JSON at “{$path}”\n";
            return;
        }
        $dump = json_decode(file_get_contents($path), true);
        if (json_last_error()) {
            echo "❌ JSON decode error: " . json_last_error_msg() . "\n";
            return;
        }

        // 4) Count total items
        $total = 1; // Journal itself
        foreach ($dump['Volumes'] ?? [] as $v) {
            $total++;
            foreach ($v['Issues'] ?? [] as $i) {
                $total++;
                $total += count($i['Authors'] ?? []);
                foreach ($i['Articles'] ?? [] as $a) {
                    $total++;
                    $total += count($a['Authors'] ?? []);
                }
            }
        }
        echo "➡️ Importing {$total} items...\n\n";

        // 5) Gather Author db fields
        $authorFields = array_keys(Author::config()->get('db') ?: []);

        $current    = 0;
        $nextPageID = 1;

        // Helper to import a file/image with its original ID and folder
        $importAsset = function (array $meta, string $class) {
            if (empty($meta['LocalPath']) || !file_exists($meta['LocalPath'])) {
                throw new \RuntimeException("Missing source file “{$meta['Filename']}”");
            }

            // Determine relative folder under assets (strip leading "/assets")
            $rawDir  = $meta['RelativeURLDir'] ?? '';
            $relDir  = trim(preg_replace('#^/assets#', '', $rawDir), '/');
            Folder::find_or_make($relDir);

            $dest    = ($relDir ? "{$relDir}/" : '') . $meta['Filename'];
            /** @var AssetFile|AssetImage $assetObj */
            $assetObj = Injector::inst()->create($class);
            $assetObj->ID = (int)$meta['ID'];
            $assetObj->setFromLocalFile($meta['LocalPath'], $dest);
            $assetObj->write();
            if ($assetObj->hasExtension(Versioned::class)) {
                $assetObj->publishSingle();
            }
            return $assetObj;
        };

        // 6) Create Journal
        $current++;
        echo "[{$current}/{$total}] Creating Journal…\n";
        $jp = JournalPage::create();
        $jp->ID = $nextPageID;
        foreach ($dump as $k => $v) {
            if (in_array($k, $this->skipFields, true)) {
                continue;
            }
            if ($jp->hasDatabaseField($k)) {
                $jp->$k = $v;
            }
        }
        $jp->write();
        if ($jp->hasExtension(Versioned::class)) {
            $jp->publishSingle();
        }
        echo "[{$current}/{$total}] Journal #{$nextPageID} “{$jp->Title}” created\n\n";
        $nextPageID++;

        // 7) Volumes → Issues → Articles → Authors
        foreach ($dump['Volumes'] ?? [] as $volData) {
            $current++;
            echo "[{$current}/{$total}] Creating Volume…\n";
            $vp = VolumePage::create();
            $vp->ID       = $nextPageID;
            $vp->ParentID = $jp->ID;
            foreach ($volData as $k => $v) {
                if (in_array($k, $this->skipFields, true)) {
                    continue;
                }
                if ($vp->hasDatabaseField($k)) {
                    $vp->$k = $v;
                }
            }
            $vp->write();

            if (!empty($volData['Cover']['LocalPath'])) {
                echo "  Attaching Volume Cover…\n";
                $cover = $importAsset($volData['Cover'], AssetImage::class);
                $vp->CoverID = $cover->ID;
            }
            if (!empty($volData['Image']['LocalPath'])) {
                echo "  Attaching Volume Image…\n";
                $img = $importAsset($volData['Image'], AssetImage::class);
                $vp->ImageID = $img->ID;
            }
            $vp->write();
            if ($vp->hasExtension(Versioned::class)) {
                $vp->publishSingle();
            }
            echo "[{$current}/{$total}] Volume #{$nextPageID} “{$vp->Title}” created\n\n";
            $nextPageID++;

            foreach ($volData['Issues'] ?? [] as $issData) {
                $current++;
                echo "[{$current}/{$total}] Creating Issue…\n";
                $ip = IssuePage::create();
                $ip->ID       = $nextPageID;
                $ip->ParentID = $vp->ID;
                foreach ($issData as $k => $v) {
                    if (in_array($k, $this->skipFields, true)) {
                        continue;
                    }
                    if ($ip->hasDatabaseField($k)) {
                        $ip->$k = $v;
                    }
                }
                $ip->write();

                if (!empty($issData['File']['LocalPath'])) {
                    echo "  Attaching Issue File…\n";
                    $f = $importAsset($issData['File'], AssetFile::class);
                    $ip->FileID = $f->ID;
                }
                if (!empty($issData['Cover']['LocalPath'])) {
                    echo "  Attaching Issue Cover…\n";
                    $ci = $importAsset($issData['Cover'], AssetImage::class);
                    $ip->CoverID = $ci->ID;
                }
                $ip->write();
                if ($ip->hasExtension(Versioned::class)) {
                    $ip->publishSingle();
                }
                echo "[{$current}/{$total}] Issue #{$nextPageID} “{$ip->Title}” created\n\n";
                $nextPageID++;

                foreach ($issData['Authors'] ?? [] as $idx => $authData) {
                    $current++;
                    echo "[{$current}/{$total}] Creating Issue Author…\n";
                    $filter   = [];
                    foreach ($authorFields as $f) {
                        if (isset($authData[$f]) && $authData[$f] !== '') {
                            $filter[$f] = $authData[$f];
                        }
                    }
                    $existing = Author::get()->filter($filter)->first();
                    $author   = $existing ?: Author::create(
                        array_intersect_key($authData, array_flip($authorFields))
                    );
                    $author->write();
                    if ($author->hasExtension(Versioned::class)) {
                        $author->publishSingle();
                    }
                    $ip->Authors()->add($author, ['Sort' => $idx]);
                    echo "[{$current}/{$total}] Issue Author #{$author->ID} “{$author->Name()}” added\n";
                }
                echo "\n";

                foreach ($issData['Articles'] ?? [] as $artData) {
                    $current++;
                    echo "[{$current}/{$total}] Creating Article…\n";
                    $ap = ArticlePage::create();
                    $ap->ID       = $nextPageID;
                    $ap->ParentID = $ip->ID;
                    foreach ($artData as $k => $v) {
                        if (in_array($k, $this->skipFields, true)) {
                            continue;
                        }
                        if ($ap->hasDatabaseField($k)) {
                            $ap->$k = $v;
                        }
                    }
                    $ap->write();

                    if (!empty($artData['File']['LocalPath'])) {
                        echo "  Attaching Article File…\n";
                        $af = $importAsset($artData['File'], AssetFile::class);
                        $ap->FileID = $af->ID;
                    }
                    $ap->write();
                    if ($ap->hasExtension(Versioned::class)) {
                        $ap->publishSingle();
                    }
                    echo "[{$current}/{$total}] Article #{$nextPageID} “{$ap->Title}” created\n\n";
                    $nextPageID++;

                    foreach ($artData['Authors'] ?? [] as $idx => $authData) {
                        $current++;
                        echo "[{$current}/{$total}] Creating Article Author…\n";
                        $filter   = [];
                        foreach ($authorFields as $f) {
                            if (isset($authData[$f]) && $authData[$f] !== '') {
                                $filter[$f] = $authData[$f];
                            }
                        }
                        $existing = Author::get()->filter($filter)->first();
                        $author   = $existing ?: Author::create(
                            array_intersect_key($authData, array_flip($authorFields))
                        );
                        $author->write();
                        if ($author->hasExtension(Versioned::class)) {
                            $author->publishSingle();
                        }
                        $ap->Authors()->add($author, ['Sort' => $idx]);
                        echo "[{$current}/{$total}] Article Author #{$author->ID} “{$author->Name()}” added\n";
                    }
                    echo "\n";
                }
            }
        }

        echo "🎉 Import complete: {$current}/{$total} items processed\n";
    }
}
