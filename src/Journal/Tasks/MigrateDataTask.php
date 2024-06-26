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
    private static $id = 1, $op, $this_website, $subsite_id, $journal_id;

    public function run($request)
    {
        self::$op = Environment::getEnv("SS_OP_DATABASE_NAME");
        self::$subsite_id = Environment::getEnv("SS_OP_SUBSITE_ID");
        self::$this_website = Environment::getEnv("SS_DATABASE_NAME");
        self::$journal_id = Environment::getEnv("SS_OP_JOURNAL_ID");

        self::Connect(self::$this_website);
        self::ClearRecords();

        $this->copySiteConfig();
        $this->copyPages();
        $this->copyVolumes();
    }

    public function findOrCreateAuthor($authorID)
    {
        self::Connect(self::$op);
        $items = self::Records(
            "Journals_Author",
            "WHERE ID = $authorID"
        );

        self::Connect(self::$this_website);
        $author = DataObject::get_by_id(Author::class, $authorID);
        if (!$author?->ID && count($items)) {
            $author = self::CreateItem($items[0], Author::class, true, 0, false);
        }

        return $author;
    }

    public function copyAuthors($page, $ownerID)
    {
        self::Connect(self::$op);

        $ownerLabel = $page->singular_name();
        $items = self::Records(
            "Journals_Author_{$ownerLabel}s",
            "WHERE Journals_{$ownerLabel}ID = $ownerID"
        );

        self::Connect(self::$this_website);
        foreach ($items as $item) {
            $author = self::findOrCreateAuthor($item["Journals_AuthorID"]);
            if ($author?->ID) {
                $page->Authors()->add($author);
            }
        }
    }

    public function copyArticles($issueID, $parentID)
    {
        self::copyDataObject(
            Article::class,
            "Journals_Article",
            true,
            "IssueID",
            $issueID,
            $parentID,
            true,
            '',
        );
    }

    public function copyIssues($volumeID, $parentID)
    {
        self::copyDataObject(
            Issue::class,
            "Journals_Issue",
            true,
            "VolumeID",
            $volumeID,
            $parentID,
            true,
            'copyArticles',
        );
    }

    public function copyVolumes()
    {
        self::copyDataObject(
            Volume::class,
            "Journals_Volume",
            true,
            "JournalID",
            self::$journal_id,
            DataObject::get_one(Journal::class)->ID,
            false,
            'copyIssues',
        );
    }

    public function copyDataObject(
        $className,
        $tableName,
        $incrementID = true,
        $parentIDLabel = '',
        $parentID = 0,
        $parentPageID = 0,
        $hasAuthors = false,
        $methodName = '',
    ) {
        self::Connect(self::$op);
        $items = self::Records(
            $tableName,
            $parentIDLabel ? "WHERE $parentIDLabel = $parentID" : ''
        );

        self::Connect(self::$this_website);
        foreach ($items as $item) {
            $page = self::CreateItem(
                $item,
                $className,
                true,
                $parentPageID,
                $incrementID
            );
            if ($methodName) {
                $this->$methodName($item['ID'], $page->ID);
            }
            if ($hasAuthors) {
                self::copyAuthors($page, $item['ID']);
            }
        }
    }

    public static function CopyElements($record, $page)
    {
        self::Connect(self::$op);
        $id = $record['ID'];
        $result = DB::query("SELECT ElementalAreaID FROM Page WHERE ID = $id")->record();
        if (!$result) {
            self::Connect(self::$this_website);
            return;
        }
        $elementalAreaID = $result["ElementalAreaID"];

        if ($elementalAreaID) {
            self::Connect(self::$op);
            $items = self::Records(
                "Element",
                "WHERE ParentID = $elementalAreaID"
            );

            foreach ($items as $item) {
                self::Connect(self::$op);
                $id = $item["ID"];
                $content = DB::query("SELECT * FROM ElementContent WHERE ID = $id")->record();

                self::Connect(self::$this_website);
                $page->Content .= $content ? $content["HTML"] : '';
            }
        }

        self::Connect(self::$this_website);
    }

    public function copyPages()
    {
        self::Connect(self::$op);
        $records = SiteTree::get()->filter("SubsiteID", self::$subsite_id)->toArray();

        self::Connect(self::$this_website);
        foreach ($records as $record) {
            $className = $record->ClassName;
            if ($record->Title == "Journal") {
                $className = Journal::class;
            }
            self::Log("Title: $record->Title...");
            self::Log("className: $className...");
            self::CreateItem(
                $record->toMap(),
                $className,
                true,
            );
        }

        $home = DataObject::get_one(RedirectorPage::class);
        if (!$home?->ID) {
            $home = RedirectorPage::create();
            $home->Title = "Home";
        }
        $home->LinkToID = DataObject::get_one(Journal::class)->ID;
        $home->write();
        $home->publishSingle();
    }

    public function copySiteConfig()
    {
        self::Connect(self::$op);
        $subsite_id = self::$subsite_id;
        $result = DB::query("SELECT * FROM SiteConfig WHERE SubsiteID = $subsite_id");

        self::Connect(self::$this_website);
        $siteconfig = SiteConfig::create();
        $siteconfig->update($result->record());
        self::Log("Creating SiteConfig #$siteconfig->ID...");
        $siteconfig->write();
    }

    public static function ClearRecords()
    {
        foreach ([
            'SiteTree',
            'VirtualPage',
            'ErrorPage',
            'RedirectorPage',
            'Journal_Pages_Volume',
            'Journal_Pages_Issue',
            'Journal_Pages_Article',
            'Journal_Models_Author'
        ] as $tableName) {
            self::Delete($tableName);
            self::Delete("{$tableName}_Live");
            self::Delete("{$tableName}_Versions");
        }

        foreach ([
            'Issues',
            'Articles'
        ] as $relationship) {
            self::Delete("Journal_Models_Author_$relationship");
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
        self::Log("Creating $class #$item->ID...");

        if ($item->ElementalAreaID) {
            self::CopyElements($data, $item);
        }

        $item->write();

        if ($publish) {
            $item->publishSingle();
        }

        return $item;
    }

    public static function Records($tableName, $where = '')
    {
        $result = DB::query("SELECT * FROM $tableName $where");
        $records = [];
        while ($record = $result->record()) {
            $records[] = $record;
        }
        return $records;
    }

    public static function Delete($tableName)
    {
        self::Log("Deleting $tableName...");
        DB::query("DELETE FROM $tableName");
    }

    public static function Log($str, $htmltag = null)
    {
        $datestamp = '[' . date('Y-m-d H:i:s') . ']';
        if (!Director::is_cli() && !Director::is_ajax()) {
            if ($htmltag) {
                echo "<$htmltag>$datestamp $str</$htmltag><br/>";
            } else {
                echo "$datestamp $str<br/>";
            }
        } else {
            echo "$datestamp $str\n";
        }
        Injector::inst()->get(Logger::class)->info(get_class() . ': ' . $str);
    }

    public static function Connect($name)
    {
        DB::connect([
            'database' => $name,
            'type' => Environment::getEnv("SS_DATABASE_CLASS"),
            'server' => Environment::getEnv("SS_DATABASE_SERVER"),
            'username' => Environment::getEnv("SS_DATABASE_USERNAME"),
            'password' => Environment::getEnv("SS_DATABASE_PASSWORD")
        ]);
    }
}
