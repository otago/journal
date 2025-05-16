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
    protected $description = 'Purges all content, then imports Journal structure with cached fields and assets, dynamically handling Author fields';

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
        foreach (AssetFile::get() as $file) {
            if ($file->hasExtension(Versioned::class)) {
                $file->doArchive();
            }
            $file->delete();
        }

        // 1) Purge all pages
        foreach (SiteTree::get() as $page) {
            if ($page->hasExtension(Versioned::class)) {
                $page->doArchive();
            }
            $page->delete();
        }

        // 2) Purge all authors
        foreach (Author::get() as $a) {
            if ($a->hasExtension(Versioned::class)) {
                $a->doArchive();
            }
            $a->delete();
        }

        // 3) Load JSON
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

        // 4) Count items
        $total = 1;
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

        // Pre-cook list of Author db fields
        $authorFields = array_keys(Author::config()->get('db') ?? []);

        $current    = 0;
        $nextPageID = 1;

        // Journal
        $current++;
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
        echo "[{$current}/{$total}] Journal #{$nextPageID} “{$jp->Title}”\n";
        $nextPageID++;

        // Volumes → Issues → Articles → Authors
        foreach ($dump['Volumes'] ?? [] as $volData) {
            $current++;
            $vp = VolumePage::create();
            $vp->ID       = $nextPageID;
            $vp->ParentID = $jp->ID;
            foreach ($volData as $k => $v) {
                if (in_array($k, $this->skipFields, true)) continue;
                if ($vp->hasDatabaseField($k)) {
                    $vp->$k = $v;
                }
            }
            $vp->write();
            // Cover
            if (!empty($volData['Cover']['LocalPath'])) {
                $img = AssetImage::create();
                $img->setFromLocalFile($volData['Cover']['LocalPath'], basename($volData['Cover']['LocalPath']));
                $img->write();
                if ($img->hasExtension(Versioned::class)) {
                    $img->publishSingle();
                }
                $vp->CoverID = $img->ID;
            }
            // Image
            if (!empty($volData['Image']['LocalPath'])) {
                $img = AssetImage::create();
                $img->setFromLocalFile($volData['Image']['LocalPath'], basename($volData['Image']['LocalPath']));
                $img->write();
                if ($img->hasExtension(Versioned::class)) {
                    $img->publishSingle();
                }
                $vp->ImageID = $img->ID;
            }
            $vp->write();
            if ($vp->hasExtension(Versioned::class)) {
                $vp->publishSingle();
            }
            echo "[{$current}/{$total}] Volume #{$nextPageID} “{$vp->Title}”\n";
            $nextPageID++;

            foreach ($volData['Issues'] ?? [] as $issData) {
                $current++;
                $ip = IssuePage::create();
                $ip->ID       = $nextPageID;
                $ip->ParentID = $vp->ID;
                foreach ($issData as $k => $v) {
                    if (in_array($k, $this->skipFields, true)) continue;
                    if ($ip->hasDatabaseField($k)) {
                        $ip->$k = $v;
                    }
                }
                $ip->write();
                // File
                if (!empty($issData['File']['LocalPath'])) {
                    $f = AssetFile::create();
                    $f->setFromLocalFile($issData['File']['LocalPath'], basename($issData['File']['LocalPath']));
                    $f->write();
                    if ($f->hasExtension(Versioned::class)) $f->publishSingle();
                    $ip->FileID = $f->ID;
                }
                // Cover
                if (!empty($issData['Cover']['LocalPath'])) {
                    $img = AssetImage::create();
                    $img->setFromLocalFile($issData['Cover']['LocalPath'], basename($issData['Cover']['LocalPath']));
                    $img->write();
                    if ($img->hasExtension(Versioned::class)) $img->publishSingle();
                    $ip->CoverID = $img->ID;
                }
                $ip->write();
                if ($ip->hasExtension(Versioned::class)) $ip->publishSingle();
                echo "[{$current}/{$total}] Issue #{$nextPageID} “{$ip->Title}”\n";
                $nextPageID++;

                foreach ($issData['Authors'] ?? [] as $idx => $authData) {
                    $current++;
                    // build filter from all DB fields on Author
                    $filter = [];
                    foreach ($authorFields as $field) {
                        if (isset($authData[$field]) && $authData[$field] !== '') {
                            $filter[$field] = $authData[$field];
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
                }

                foreach ($issData['Articles'] ?? [] as $artData) {
                    $current++;
                    $ap = ArticlePage::create();
                    $ap->ID       = $nextPageID;
                    $ap->ParentID = $ip->ID;
                    foreach ($artData as $k => $v) {
                        if (in_array($k, $this->skipFields, true)) continue;
                        if ($ap->hasDatabaseField($k)) {
                            $ap->$k = $v;
                        }
                    }
                    $ap->write();
                    // File
                    if (!empty($artData['File']['LocalPath'])) {
                        $f = AssetFile::create();
                        $f->setFromLocalFile($artData['File']['LocalPath'], basename($artData['File']['LocalPath']));
                        $f->write();
                        if ($f->hasExtension(Versioned::class)) $f->publishSingle();
                        $ap->FileID = $f->ID;
                    }
                    $ap->write();
                    if ($ap->hasExtension(Versioned::class)) $ap->publishSingle();
                    echo "[{$current}/{$total}] Article #{$nextPageID} “{$ap->Title}”\n";
                    $nextPageID++;

                    foreach ($artData['Authors'] ?? [] as $idx => $authData) {
                        $current++;
                        $filter = [];
                        foreach ($authorFields as $field) {
                            if (isset($authData[$field]) && $authData[$field] !== '') {
                                $filter[$field] = $authData[$field];
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
                    }
                }
            }
        }

        echo "\n🎉 Import complete: {$current}/{$total} items processed\n";
    }
}
