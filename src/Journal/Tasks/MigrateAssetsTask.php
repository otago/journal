<?php

namespace Journal\Tasks;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Journal\Pages\Article;
use Journal\Pages\Issue;
use Journal\Pages\Volume;

class MigrateAssetsTask extends BuildTask
{
    private static $op, $this_website, $op_public_directory;

    public static function OP()
    {
        return self::$op;
    }

    public static function ThisWebsite()
    {
        return self::$this_website;
    }

    public function run($request)
    {
        self::$op = Environment::getEnv("SS_OP_DATABASE_NAME");
        self::$this_website = Environment::getEnv("SS_DATABASE_NAME");
        self::$op_public_directory = Environment::getEnv("SS_OP_PUBLIC_DIRECTORY");

        $this->extend("onBeforeRun");

        self::DeleteAllFiles();
        self::CreateUploadsFolder();

        $this->extend("onBeforeCopy");

        self::Volumes();
        self::Issues();
        self::Articles();

        $this->extend("onAfterRun");
    }

    public static function Articles()
    {
        MigrateDataTask::Connect(self::$this_website);
        foreach (Article::get() as $article) {
            self::UpdateAssets(
                [
                    'FileID' => $article->FileID,
                ]
            );
        }
    }

    public static function Issues()
    {
        MigrateDataTask::Connect(self::$this_website);
        foreach (Issue::get() as $issue) {
            self::UpdateAssets(
                [
                    'FileID' => $issue->FileID,
                    'CoverID' => $issue->CoverID,
                ]
            );
        }
    }

    public static function Volumes()
    {
        MigrateDataTask::Connect(self::$this_website);
        foreach (Volume::get() as $volume) {
            self::UpdateAssets(
                [
                    'CoverID' => $volume->CoverID,
                    'ImageID' => $volume->ImageID,
                ]
            );
        }
    }

    public static function UpdateAssets($relationships)
    {
        foreach ($relationships as $value) {
            self::SyncAsset($value);
        }
    }

    public static function SyncAsset($assetID, $className = File::class)
    {
        if (!$assetID || $assetID == "0") {
            return;
        }

        MigrateDataTask::Connect(self::$op);
        $record = DataObject::get_by_id($className, $assetID);
        $sourceUrl = $record?->File?->getSourceURL();

        if (!$sourceUrl) {
            MigrateDataTask::Log("Failed to find sourceUrl for file with ID #$assetID.");
            return;
        }

        MigrateDataTask::Connect(self::$this_website);
        $url = $record->File ? Controller::curr()->join_links(self::$op_public_directory, $sourceUrl) : '';
        $record = $record->toMap();
        $folder = Folder::find_or_make('Uploads');
        $file = MigrateDataTask::CreateItem(
            $record,
            $record['ClassName'],
            true,
            $folder->ID,
            false
        );
        if ($url && file_exists($url)) {
            MigrateDataTask::Log("Coping #$file->ID from URL $url...");
            $file->setFromLocalFile($url);
        } else {
            $live_url = Controller::curr()->join_links(
                Environment::getEnv("SS_OP_LIVE_URL"),
                $sourceUrl
            );
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: PHP'
                ]
            ];
            $context = stream_context_create($options);

            MigrateDataTask::Log("Coping #$file->ID from URL $live_url...");
            $content = file_get_contents($live_url, false, $context);

            if ($content === FALSE) {
                MigrateDataTask::Log("Failed to open stream.");
            } else {
                $file->setFromString($content, "Uploads/$file->Name");
                $file->ID = $record["ID"];
                $file->write();
                $file->publishSingle();
            }
        }
    }

    public static function CreateUploadsFolder()
    {
        $folder = Folder::find_or_make('Uploads');
        DB::query("UPDATE `File` SET ID = 1 WHERE ID = $folder->ID");
        DB::query("UPDATE `File_Live` SET ID = 1 WHERE ID = $folder->ID");
        DB::query("UPDATE `File_Versions` SET RecordID = 1 WHERE RecordID = $folder->ID");
    }

    public static function DeleteAllFiles()
    {
        MigrateDataTask::Connect(self::$this_website);
        foreach (File::get() as $file) {
            MigrateDataTask::Log("Deleting file #$file->ID $file->Title...");
            $file->delete();
        }

        MigrateDataTask::Delete("File");
        MigrateDataTask::Delete("File_Live");
        MigrateDataTask::Delete("File_Versions");
    }
}
