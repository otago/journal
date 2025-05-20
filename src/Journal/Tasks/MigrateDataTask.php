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
use SilverStripe\ORM\DB;

class MigrateDataTask extends BuildTask
{
    protected $title       = 'Migrate Data Task';
    protected $description = 'Purges, restores backup pages (fallback to SiteTree for unknown classes), then imports Journal structure preserving IDs—including authors’ IDs';

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
        // 0) Purge all Files
        $files = AssetFile::get();
        echo "➡️ Purging " . $files->count() . " files...\n";
        foreach ($files as $i => $f) {
            $idx = $i + 1;
            if ($f->hasExtension(Versioned::class)) {
                $f->doArchive();
            }
            echo "[" . $idx . "/" . $files->count() . "] Deleting File #{$f->ID}\n";
            $f->delete();
        }

        // 1) Purge all Pages
        $pages = SiteTree::get();
        echo "➡️ Purging " . $pages->count() . " pages...\n";
        foreach ($pages as $i => $p) {
            $idx = $i + 1;
            if ($p->hasExtension(Versioned::class)) {
                $p->doArchive();
            }
            echo "[" . $idx . "/" . $pages->count() . "] Deleting Page #{$p->ID}\n";
            $p->delete();
        }

        // 2) Purge all Authors
        $authors = Author::get();
        echo "➡️ Purging " . $authors->count() . " authors...\n";
        foreach ($authors as $i => $a) {
            $idx = $i + 1;
            if ($a->hasExtension(Versioned::class)) {
                $a->doArchive();
            }
            echo "[" . $idx . "/" . $authors->count() . "] Deleting Author #{$a->ID}\n";
            $a->delete();
        }

        // 3) Load JSON backup
        $path = Environment::getEnv('SS_RECORDS_CACHE_FILE');
        if (!$path || !is_readable($path)) {
            echo "❌ Cannot read JSON at “{$path}”\n";
            return;
        }
        $dump = json_decode(file_get_contents($path), true);
        if (!$dump) {
            echo "❌ JSON decode error: " . json_last_error_msg() . "\n";
            return;
        }

        // 3a) Restore all backup pages first
        echo "➡️ Restoring backup pages...\n";
        foreach ($dump['Pages'] ?? [] as $pageData) {
            $class = $pageData['ClassName'] ?? SiteTree::class;
            if (!class_exists($class)) {
                echo "  ⚠️ Unknown class {$class}, using SiteTree\n";
                $class = SiteTree::class;
            }
            /** @var SiteTree */
            $page = Injector::inst()->create($class);
            $page->ID = (int)($pageData['ID'] ?? 0);

            // Set DB fields except relations
            foreach ($pageData as $k => $v) {
                if (in_array($k, $this->skipFields, true) || $k === 'ParentID') {
                    continue;
                }
                if ($page->hasDatabaseField($k)) {
                    $page->$k = $v;
                }
            }

            // Restore hierarchy
            $page->ParentID = isset($pageData['ParentID']) ? (int)$pageData['ParentID'] : 0;
            $page->write();

            // Publish or unpublish based on backup flag
            if ($page->hasExtension(Versioned::class)) {
                if (!empty($pageData['isPublished'])) {
                    $page->publishSingle();
                } else {
                    $page->doUnpublish();
                }
            }

            echo "  ✓ Restored {$class} #{$page->ID}\n";
        }
        echo "✅ Backup pages restored.\n\n";

        // 4) Count items for journal import progress
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
        echo "➡️ Importing journal items ({$total} steps)...\n\n";

        // Determine next new page ID
        $maxID = (int)DB::query('SELECT MAX("ID") FROM "SiteTree"')->value();
        $nextPageID = $maxID + 1;

        // Find the restored JournalPage root
        $jp = SiteTree::get()
            ->filter('ClassName', JournalPage::class)
            ->first();

        if ($jp) {
            echo "➡️ Found existing JournalPage #{$jp->ID}, using it as root\n\n";
        } else {
            // Create fresh if not in backup
            echo "➡️ Creating JournalPage…\n";
            $jp = JournalPage::create();
            $jp->ID = $nextPageID++;
            $jp->Title = 'Journal';
            $jp->URLSegment = 'journal';
            $jp->write();
            if ($jp->hasExtension(Versioned::class)) {
                $jp->publishSingle();
            }
            echo "  ✓ Created JournalPage #{$jp->ID}\n\n";
        }

        $authorFields = array_keys(Author::config()->get('db') ?: []);
        $current = 0;

        // Helper to import asset
        $importAsset = function (array $meta, string $class) {
            if (empty($meta['LocalPath']) || !file_exists($meta['LocalPath'])) {
                throw new \RuntimeException("Missing asset “{$meta['Filename']}”");
            }
            $relDir = trim(preg_replace('#^/assets#', '', ($meta['RelativeURLDir'] ?? '')), '/');
            Folder::find_or_make($relDir);
            $dest = ($relDir ? "{$relDir}/" : '') . $meta['Filename'];

            $obj = Injector::inst()->create($class);
            $obj->ID = (int)$meta['ID'];
            $obj->setFromLocalFile($meta['LocalPath'], $dest);
            $obj->write();
            if ($obj->hasExtension(Versioned::class)) {
                $obj->publishSingle();
            }
            return $obj;
        };

        // 5) Volumes → Issues → Articles → Authors
        foreach ($dump['Volumes'] ?? [] as $volData) {
            $current++;
            echo "[{$current}/{$total}] Volume…\n";
            $vp = VolumePage::create();
            $vp->ID = $nextPageID++;
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

            if (!empty($volData['Cover']['LocalPath'] ?? '')) {
                echo "  • Cover\n";
                $c = $importAsset($volData['Cover'], AssetImage::class);
                $vp->CoverID = $c->ID;
            }
            if (!empty($volData['Image']['LocalPath'] ?? '')) {
                echo "  • Image\n";
                $i = $importAsset($volData['Image'], AssetImage::class);
                $vp->ImageID = $i->ID;
            }
            $vp->write();
            if ($vp->hasExtension(Versioned::class)) {
                $vp->publishSingle();
            }
            echo "  ✓ VolumePage #{$vp->ID}\n\n";

            foreach ($volData['Issues'] ?? [] as $issData) {
                $current++;
                echo "[{$current}/{$total}] Issue…\n";
                $ip = IssuePage::create();
                $ip->ID = $nextPageID++;
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

                if (!empty($issData['File']['LocalPath'] ?? '')) {
                    echo "  • File\n";
                    $f = $importAsset($issData['File'], AssetFile::class);
                    $ip->FileID = $f->ID;
                }
                if (!empty($issData['Cover']['LocalPath'] ?? '')) {
                    echo "  • Cover\n";
                    $ci = $importAsset($issData['Cover'], AssetImage::class);
                    $ip->CoverID = $ci->ID;
                }
                $ip->write();
                if ($ip->hasExtension(Versioned::class)) {
                    $ip->publishSingle();
                }
                echo "  ✓ IssuePage #{$ip->ID}\n\n";

                foreach ($issData['Authors'] ?? [] as $idx => $authData) {
                    $current++;
                    echo "[{$current}/{$total}] Issue Author…\n";
                    $filter = [];
                    foreach ($authorFields as $f) {
                        if (!empty($authData[$f])) {
                            $filter[$f] = $authData[$f];
                        }
                    }
                    $existing = Author::get()->filter($filter)->first();
                    if ($existing) {
                        $author = $existing;
                    } else {
                        $author = Author::create(
                            array_intersect_key($authData, array_flip($authorFields))
                        );
                        $author->ID = (int)$authData['ID'];
                        $author->write();
                        if ($author->hasExtension(Versioned::class)) {
                            $author->publishSingle();
                        }
                    }
                    $ip->Authors()->add($author, ['Sort' => $idx]);
                    echo "  • Author #{$author->ID}\n";
                }
                echo "\n";

                foreach ($issData['Articles'] ?? [] as $artData) {
                    $current++;
                    echo "[{$current}/{$total}] Article…\n";
                    $ap = ArticlePage::create();
                    $ap->ID = $nextPageID++;
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

                    if (!empty($artData['File']['LocalPath'] ?? '')) {
                        echo "  • File\n";
                        $f = $importAsset($artData['File'], AssetFile::class);
                        $ap->FileID = $f->ID;
                    }
                    $ap->write();
                    if ($ap->hasExtension(Versioned::class)) {
                        $ap->publishSingle();
                    }
                    echo "  ✓ ArticlePage #{$ap->ID}\n\n";

                    foreach ($artData['Authors'] ?? [] as $idx => $authData) {
                        $current++;
                        echo "[{$current}/{$total}] Article Author…\n";
                        $filter = [];
                        foreach ($authorFields as $f) {
                            if (!empty($authData[$f])) {
                                $filter[$f] = $authData[$f];
                            }
                        }
                        $existing = Author::get()->filter($filter)->first();
                        if ($existing) {
                            $author = $existing;
                        } else {
                            $author = Author::create(
                                array_intersect_key($authData, array_flip($authorFields))
                            );
                            $author->ID = (int)$authData['ID'];
                            $author->write();
                            if ($author->hasExtension(Versioned::class)) {
                                $author->publishSingle();
                            }
                        }
                        $ap->Authors()->add($author, ['Sort' => $idx]);
                        echo "  • Author #{$author->ID}\n";
                    }
                    echo "\n";
                }
            }
        }

        echo "🎉 Import complete: {$current}/{$total} journal items processed\n";
    }
}
