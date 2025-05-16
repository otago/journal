<?php

namespace Journal\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;

class MigrateDataTask extends BuildTask
{
    protected $title = 'Migrate Data Task';
    protected $description = 'Reads and dumps the JSON data from the file defined by SS_RECORDS_CACHE_FILE';

    /**
     * @param HTTPRequest|null $request
     */
    public function run($request)
    {
        // 1. Read cache file path from .env
        $cachePath = Environment::getEnv('SS_RECORDS_CACHE_FILE');
        if (!$cachePath) {
            echo "❗ SS_RECORDS_CACHE_FILE is not defined in .env\n";
            return;
        }

        // 2. Ensure the file exists and is readable
        if (!file_exists($cachePath) || !is_readable($cachePath)) {
            echo "❗ Cannot read JSON file at {$cachePath}\n";
            return;
        }

        // 3. Load and decode the JSON
        $json = file_get_contents($cachePath);
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "❗ JSON decode error: " . json_last_error_msg() . "\n";
            return;
        }

        // 4. Dump the loaded data (you can customize this to handle it differently)
        echo "✅ Loaded JSON data from {$cachePath}:\n";
        print_r($data);
    }
}
