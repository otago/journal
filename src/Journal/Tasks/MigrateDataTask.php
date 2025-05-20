<?php

namespace Journal\Tasks;

use Journal\Pages\Journal as JournalPage;
use Journal\Pages\Volume as VolumePage;
use Journal\Pages\Issue as IssuePage;
use Journal\Pages\Article as ArticlePage;
use Journal\Models\Author;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Assets\File as AssetFile;
use SilverStripe\Assets\Image as AssetImage;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;

class MigrateDataTask extends BuildTask
{
    protected $title       = 'Migrate Data Task';
    protected $description = 'Restores backup pages (with classname mapping) then imports Journal & its Volumes/Issues/Articles, skipping existing by title';

    private static $skipFields = [
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

    /**
     * @config
     * @var array Map of old classname => new classname
     */
    private static $class_map = [];

    public function run($request)
    {
        // Load classname map from YAML
        $classMap = Config::inst()->get(self::class, 'class_map') ?: [];

        // Load JSON
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

        // 1) Restore backup pages
        echo "➡️ Restoring backup pages...\n";
        foreach ($dump['Pages'] ?? [] as $pageData) {
            // classname mapping
            $orig = $pageData['ClassName'] ?? SiteTree::class;
            $class = $classMap[$orig] ?? $orig;
            if (!class_exists($class)) {
                echo "  ⚠️ Unknown class {$class}, fallback to SiteTree\n";
                $class = SiteTree::class;
            }

            $id = (int)($pageData['ID'] ?? 0);
            if (DataObject::get_by_id($class, $id)) {
                echo "  ↳ Skipped existing {$class} #{$id}\n";
                continue;
            }

            /** @var SiteTree $page */
            $page = Injector::inst()->create($class);
            $page->ID = $id;
            foreach ($pageData as $k => $v) {
                if (in_array($k, self::$skipFields, true) || $k === 'ParentID') {
                    continue;
                }
                if ($page->hasDatabaseField($k)) {
                    $page->$k = $v;
                }
            }
            $page->ParentID = isset($pageData['ParentID']) ? (int)$pageData['ParentID'] : 0;
            $page->write();

            if ($page->hasExtension(Versioned::class)) {
                if (!empty($pageData['isPublished'])) {
                    $page->publishSingle();
                } else {
                    $page->doUnpublish();
                }
            }

            echo "  ✓ Restored {$class} #{$id}\n";
        }
        echo "✅ Pages restored.\n\n";

        // 2) Import Journal structure
        $jp = SiteTree::get()->filter('ClassName', JournalPage::class)->first();
        if ($jp) {
            echo "➡️ Found JournalPage #{$jp->ID}\n\n";
        } else {
            echo "➡️ Creating JournalPage…\n";
            $jp            = JournalPage::create();
            $jp->Title     = 'Journal';
            $jp->URLSegment = 'journal';
            $jp->write();
            $jp->publishSingle();
            echo "  ✓ JournalPage #{$jp->ID}\n\n";
        }

        $authorFields = array_keys(Author::config()->get('db') ?: []);
        $current = 0;
        $total   = 1;
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

        $importAsset = function (array $meta, string $class) {
            if (empty($meta['LocalPath']) || !file_exists($meta['LocalPath'])) {
                throw new \RuntimeException("Missing asset “{$meta['Filename']}”");
            }
            $rel = trim(preg_replace('#^/assets#', '', ($meta['RelativeURLDir'] ?? '')), '/');
            Folder::find_or_make($rel);
            $dest = ($rel ? "$rel/" : '') . $meta['Filename'];
            $obj  = Injector::inst()->create($class);
            $obj->ID = (int)$meta['ID'];
            $obj->setFromLocalFile($meta['LocalPath'], $dest);
            $obj->write();
            $obj->publishSingle();
            return $obj;
        };

        foreach ($dump['Volumes'] ?? [] as $volData) {
            $current++;
            echo "[{$current}/{$total}] Volume…\n";
            if (VolumePage::get()->filter('Title', $volData['Title'])->first()) {
                echo "  ↳ Skipped existing Volume “{$volData['Title']}”\n\n";
                continue;
            }
            $vp = VolumePage::create();
            $vp->Title = $volData['Title'];
            foreach ($volData as $k => $v) {
                if (in_array($k, self::$skipFields, true)) continue;
                if ($vp->hasDatabaseField($k)) $vp->$k = $v;
            }
            $vp->ParentID = $jp->ID;
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
            $vp->publishSingle();
            echo "  ✓ Volume “{$vp->Title}”\n\n";

            foreach ($volData['Issues'] ?? [] as $issData) {
                $current++;
                echo "[{$current}/{$total}] Issue…\n";
                if (IssuePage::get()->filter('Title', $issData['Title'])->first()) {
                    echo "  ↳ Skipped existing Issue “{$issData['Title']}”\n\n";
                    continue;
                }
                $ip = IssuePage::create();
                $ip->Title = $issData['Title'];
                foreach ($issData as $k => $v) {
                    if (in_array($k, self::$skipFields, true)) continue;
                    if ($ip->hasDatabaseField($k)) $ip->$k = $v;
                }
                $ip->ParentID = $vp->ID;
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
                $ip->publishSingle();
                echo "  ✓ Issue “{$ip->Title}”\n\n";

                foreach ($issData['Authors'] ?? [] as $idx => $authData) {
                    $current++;
                    echo "[{$current}/{$total}] Issue Author…\n";
                    $filter = [];
                    foreach ($authorFields as $f) {
                        if (!empty($authData[$f])) $filter[$f] = $authData[$f];
                    }
                    $author = Author::get()->filter($filter)->first();
                    if (!$author) {
                        $author = Author::create(array_intersect_key($authData, array_flip($authorFields)));
                        $author->write();
                        $author->publishSingle();
                    }
                    $ip->Authors()->add($author, ['Sort' => $idx]);
                    echo "  • Author “{$author->Name()}”\n";
                }
                echo "\n";

                foreach ($issData['Articles'] ?? [] as $artData) {
                    $current++;
                    echo "[{$current}/{$total}] Article…\n";
                    if (ArticlePage::get()->filter('Title', $artData['Title'])->first()) {
                        echo "  ↳ Skipped existing Article “{$artData['Title']}”\n\n";
                        continue;
                    }
                    $ap = ArticlePage::create();
                    $ap->Title = $artData['Title'];
                    foreach ($artData as $k => $v) {
                        if (in_array($k, self::$skipFields, true)) continue;
                        if ($ap->hasDatabaseField($k)) $ap->$k = $v;
                    }
                    $ap->ParentID = $ip->ID;
                    $ap->write();

                    if (!empty($artData['File']['LocalPath'] ?? '')) {
                        echo "  • File\n";
                        $f = $importAsset($artData['File'], AssetFile::class);
                        $ap->FileID = $f->ID;
                    }
                    $ap->write();
                    $ap->publishSingle();
                    echo "  ✓ Article “{$ap->Title}”\n\n";

                    foreach ($artData['Authors'] ?? [] as $idx => $authData) {
                        $current++;
                        echo "[{$current}/{$total}] Article Author…\n";
                        $filter = [];
                        foreach ($authorFields as $f) {
                            if (!empty($authData[$f])) $filter[$f] = $authData[$f];
                        }
                        $author = Author::get()->filter($filter)->first();
                        if (!$author) {
                            $author = Author::create(array_intersect_key($authData, array_flip($authorFields)));
                            $author->write();
                            $author->publishSingle();
                        }
                        $ap->Authors()->add($author, ['Sort' => $idx]);
                        echo "  • Author “{$author->Name()}”\n";
                    }
                    echo "\n";
                }
            }
        }

        echo "🎉 Import complete: {$current}/{$total} items processed\n";
    }
}
