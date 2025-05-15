<?php

namespace Journal\Tasks;

use Page;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\GraphQL\Schema\Logger;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;
use Journal\Models\Author;
use Journal\Pages\Article;
use Journal\Pages\Issue;
use Journal\Pages\Journal;
use Journal\Pages\Volume;

class MigrateDataTask extends BuildTask
{
    private static $id = 1;
    private static $op, $this_website, $subsite_id, $journal_id;
    private static $cache = [];

    public static function OP()
    {
        return self::$op;
    }
    public static function ThisWebsite()
    {
        return self::$this_website;
    }
    public static function SubsiteID()
    {
        return self::$subsite_id;
    }

    public function run($request)
    {
        self::$op           = Environment::getEnv("SS_OP_DATABASE_NAME");
        self::$subsite_id   = Environment::getEnv("SS_OP_SUBSITE_ID");
        self::$this_website = Environment::getEnv("SS_DATABASE_NAME");
        self::$journal_id   = Environment::getEnv("SS_OP_JOURNAL_ID");

        $this->extend("onBeforeRun");

        $this->copySiteConfig();
        $this->copyPages();
        $this->copyVolumes();

        $this->extend("onAfterRun");
    }

    public function findOrCreateAuthor($authorID)
    {
        $items = self::Records("Journals_Author", "WHERE ID = $authorID", self::$op);
        DataObject::reset();
        $author = DataObject::get_by_id(Author::class, $authorID);
        if (!$author?->ID && count($items)) {
            $author = self::CreateItem($items[0], Author::class, true, 0, false);
        }
        return $author;
    }

    public function copyAuthors($page, $ownerID)
    {
        $label = $page->singular_name();
        $items = self::Records(
            "Journals_Author_{$label}s",
            "WHERE Journals_{$label}ID = $ownerID",
            self::$op
        );
        foreach ($items as $item) {
            $a = $this->findOrCreateAuthor($item["Journals_AuthorID"]);
            if ($a?->ID) {
                $page->Authors()->add($a);
            }
        }
    }

    public function copyArticles($issueID, $parentID)
    {
        $this->copyDataObject(
            Article::class,
            "Journals_Article",
            true,
            "IssueID",
            $issueID,
            $parentID,
            true,
            ''
        );
    }

    public function copyIssues($volumeID, $parentID)
    {
        $this->copyDataObject(
            Issue::class,
            "Journals_Issue",
            true,
            "VolumeID",
            $volumeID,
            $parentID,
            true,
            'copyArticles'
        );
    }

    public function copyVolumes()
    {
        $this->copyDataObject(
            Volume::class,
            "Journals_Volume",
            true,
            "JournalID",
            self::$journal_id,
            DataObject::get_one(Journal::class)->ID,
            false,
            'copyIssues'
        );
    }

    public function copyDataObject(
        $className,
        $tableName,
        $incrementID = true,
        $parentLabel = '',
        $parentID = 0,
        $parentPageID = 0,
        $hasAuthors = false,
        $method = ''
    ) {
        $items = self::Records(
            $tableName,
            $parentLabel ? "WHERE {$parentLabel} = {$parentID}" : '',
            self::$op
        );
        foreach ($items as $item) {
            $page = self::CreateItem(
                $item,
                $className,
                true,
                $parentPageID,
                $incrementID
            );
            if ($method) {
                $this->$method($item['ID'], $page->ID);
            }
            if ($hasAuthors) {
                $this->copyAuthors($page, $item['ID']);
            }
        }
    }

    public function copySiteConfig()
    {
        // switch to OP
        DB::connect([
            'database' => self::$op,
            'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
            'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
        ]);
        $res = DB::query("SELECT * FROM SiteConfig WHERE SubsiteID = " . self::$subsite_id);

        // switch back
        DB::connect([
            'database' => self::$this_website,
            'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
            'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
        ]);
        $cfg = SiteConfig::create();
        $cfg->update($res->record());
        self::Log("Creating SiteConfig #{$cfg->ID}...");
        $cfg->write();
    }

    public function copyPages()
    {
        // load pages from OP
        DB::connect([
            'database' => self::$op,
            'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
            'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
        ]);
        $records = SiteTree::get()
            ->filter("SubsiteID", self::$subsite_id)
            ->toArray();

        // switch back
        DB::connect([
            'database' => self::$this_website,
            'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
            'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
        ]);
        $newItems = [];
        foreach ($records as $rec) {
            $cls = ($rec->Title === "Journal") ? Journal::class : $rec->ClassName;
            self::Log("Importing page '{$rec->Title}' as {$cls}...");
            $newItems[$rec->ID] = self::CreateItem($rec->toMap(), $cls, true);
        }
        foreach ($records as $rec) {
            if ($rec->ParentID) {
                $parentID = $newItems[$newItems[$rec->ID]->ParentID]->ID;
                $newItems[$rec->ID]->ParentID = $parentID;
                $newItems[$rec->ID]->write();
                $newItems[$rec->ID]->publishSingle();
            }
        }
        $home = DataObject::get_one(RedirectorPage::class);
        if ($home?->ID && $home->Title === "Home") {
            $home->LinkToID = DataObject::get_one(Journal::class)->ID;
            $home->write();
            $home->publishSingle();
        }
    }

    public static function CopyElements($record, $page)
    {
        // get elemental area from OP
        DB::connect([
            'database' => self::$op,
            'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
            'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
        ]);
        $res = DB::query("SELECT ElementalAreaID FROM Page WHERE ID = {$record['ID']}")->record();
        if (!$res) {
            // back to site
            DB::connect([
                'database' => self::$this_website,
                'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
                'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
                'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
                'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
            ]);
            return;
        }
        $areaID = $res["ElementalAreaID"];
        $items  = DB::query("SELECT * FROM Element WHERE ParentID = {$areaID}");
        // back to site
        DB::connect([
            'database' => self::$this_website,
            'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
            'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
        ]);
        foreach ($items as $item) {
            // fetch HTML from OP
            DB::connect([
                'database' => self::$op,
                'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
                'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
                'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
                'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
            ]);
            $c = DB::query("SELECT HTML FROM ElementContent WHERE ID = {$item['ID']}")->record();
            // back to site
            DB::connect([
                'database' => self::$this_website,
                'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
                'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
                'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
                'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
            ]);
            $page->Content .= $c ? $c["HTML"] : '';
        }
    }

    public static function CreateItem(
        $data,
        $class = Page::class,
        $publish = false,
        $parentID = 0,
        $incrementID = true
    ) {
        $item = $class::create();
        $item->update($data);
        if ($parentID) {
            $item->ParentID = $parentID;
        }
        $item->ClassName = $class;
        if ($incrementID) {
            $item->ID = self::$id++;
        }
        self::Log("Creating {$class} #{$item->ID}...");

        if (!$item->ElementalAreaID && $class === Page::class) {
            $elems = self::Records("Page", "WHERE ID = {$data['ID']}", self::$op);
            if (count($elems)) {
                $item->ElementalAreaID = $elems[0]["ElementalAreaID"];
            }
        }
        if ($item->ElementalAreaID) {
            self::CopyElements($data, $item);
        }
        $item->write();
        if ($publish) {
            $item->publishSingle();
        }
        return $item;
    }

    public static function Records($table, $where = '', $dbName = null)
    {
        $cacheFile = Environment::getEnv("SS_RECORDS_CACHE_FILE");
        if (!$cacheFile) {
            throw new \RuntimeException("Env SS_RECORDS_CACHE_FILE not set");
        }

        if (!self::$cache) {
            self::$cache = file_exists($cacheFile)
                ? json_decode(file_get_contents($cacheFile), true)
                : [];
        }

        $key = "{$dbName}|{$table}|{$where}";
        if (isset(self::$cache[$key])) {
            // restore target site DB
            DB::connect([
                'database' => self::$this_website,
                'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
                'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
                'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
                'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
            ]);
            return self::$cache[$key];
        }

        if ($dbName) {
            DB::connect([
                'database' => $dbName,
                'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
                'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
                'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
                'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
            ]);
        }

        $result = DB::query("SELECT * FROM {$table} {$where}");
        $records = [];
        while ($r = $result->record()) {
            $records[] = $r;
        }

        self::$cache[$key] = $records;
        file_put_contents($cacheFile, json_encode(self::$cache, JSON_PRETTY_PRINT));

        // reset to target site DB
        DB::connect([
            'database' => self::$this_website,
            'type'     => Environment::getEnv("SS_DATABASE_CLASS"),
            'server'   => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD"),
        ]);

        return $records;
    }

    public static function Log($str, $htmltag = null)
    {
        $stamp = '[' . date('Y-m-d H:i:s') . ']';
        $out = Director::is_cli() || Director::is_ajax()
            ? "{$stamp} {$str}\n"
            : ($htmltag
                ? "<{$htmltag}>{$stamp} {$str}</{$htmltag}><br/>"
                : "{$stamp} {$str}<br/>"
            );
        echo $out;
        Injector::inst()->get(Logger::class)->info(__CLASS__ . ': ' . $str);
    }
}
