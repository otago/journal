<?php

namespace OP\Journals;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Environment;
use SilverStripe\Assets\File as AssetFile;
use OP\Journals\Journal;
use OP\Journals\Volume;
use OP\Journals\Issue;
use OP\Journals\Article;

class MigrateTask extends BuildTask
{
    private static $segment = 'MigrateTask';

    protected $title       = 'Migrate Task';
    protected $description = 'Exports Journal #1 to JSON, caches assets locally (or via live fallback), with retry under staffhub/profilepics';

    public function run($request)
    {
        // Read env vars
        $cachePath    = Environment::getEnv('SS_RECORDS_CACHE_FILE');
        $assetsFolder = Environment::getEnv('SS_DUMP_ASSETS_DIR');
        $liveDomain   = rtrim(Environment::getEnv('SS_LIVE_DOMAIN') ?: '', '/');
        if (!$cachePath) {
            throw new \RuntimeException("SS_RECORDS_CACHE_FILE not defined");
        }
        if (!$assetsFolder) {
            throw new \RuntimeException("SS_DUMP_ASSETS_DIR not defined");
        }
        if (!is_dir($assetsFolder) && !mkdir($assetsFolder, 0755, true)) {
            throw new \RuntimeException("Cannot create assets folder at {$assetsFolder}");
        }

        // Load Journal#1
        $journal = Journal::get()->byID(1);
        if (!$journal) {
            throw new \RuntimeException("No Journal record found with ID=1");
        }

        // Dump Journal data
        echo "[INFO] Dumping Journal data...\n";
        $data = $journal->toMap();

        // Asset caching helper
        $cacheAsset = function ($record, $relation) use ($assetsFolder, $liveDomain) {
            $idField = $relation . 'ID';
            if (!$record->exists() || !$record->$idField) {
                return null;
            }
            $asset    = $record->$relation();
            $relative = $asset->getFilename();
            $basename = basename($relative);
            $destPath = "{$assetsFolder}/{$basename}";

            if (file_exists($destPath)) {
                echo "[SKIP] {$relation} #{$asset->ID} already cached\n";
            } else {
                echo "[CACHE] {$relation} #{$asset->ID} -> ";
                // try local stream
                $in = $asset->getStream();
                if ($in) {
                    $out   = fopen($destPath, 'wb');
                    $bytes = @stream_copy_to_stream($in, $out);
                    fclose($in);
                    fclose($out);
                    if ($bytes !== false) {
                        echo "local ({$bytes} bytes)\n";
                        return ['ID' => $asset->ID, 'Filename' => $basename, 'LocalPath' => $destPath];
                    } else {
                        echo "local failed, ";
                    }
                } else {
                    echo "no local stream, ";
                }
                // fallback live asset path
                $attempts = [
                    "/assets/{$relative}",
                    "/assets/staffhub/profilepics/{$basename}"
                ];
                foreach ($attempts as $path) {
                    if (!$liveDomain) {
                        break;
                    }
                    $url    = "{$liveDomain}{$path}";
                    echo "download from {$url} -> ";
                    $stream = @fopen($url, 'rb');
                    if ($stream) {
                        $out   = fopen($destPath, 'wb');
                        $bytes = @stream_copy_to_stream($stream, $out);
                        fclose($stream);
                        fclose($out);
                        if ($bytes !== false) {
                            echo "success ({$bytes} bytes)\n";
                            return ['ID' => $asset->ID, 'Filename' => $basename, 'LocalPath' => $destPath];
                        }
                        echo "write failed, ";
                    } else {
                        echo "fetch failed, ";
                    }
                }
                // all attempts failed
                throw new \RuntimeException("Failed to cache {$relation} #{$asset->ID}");
            }

            return ['ID' => $asset->ID, 'Filename' => $basename, 'LocalPath' => $destPath];
        };

        // Process Volumes
        echo "[INFO] Processing Volumes...\n";
        $dumpVolumes = [];
        $volumes     = $journal->Volumes();
        $totalV      = $volumes->count();
        foreach ($volumes as $i => $vol) {
            $idx = $i + 1;
            echo "[V{$idx}/{$totalV}] Volume “{$vol->Title}”\n";
            $v = $vol->toMap();
            $v['Cover'] = $cacheAsset($vol, 'Cover');
            $v['Image'] = $cacheAsset($vol, 'Image');

            // Issues
            $dumpIssues = [];
            $issues      = $vol->Issues();
            $totalI      = $issues->count();
            foreach ($issues as $j => $iss) {
                $jdx = $j + 1;
                echo "  [V{$idx}.I{$jdx}/V{$totalV}.I{$totalI}] Issue “{$iss->Title}”\n";
                $im = $iss->toMap();
                $im['File']  = $cacheAsset($iss, 'File');
                $im['Cover'] = $cacheAsset($iss, 'Cover');

                // Articles
                $dumpArts = [];
                $arts      = $iss->Articles();
                $totalA    = $arts->count();
                foreach ($arts as $k => $art) {
                    $kdx = $k + 1;
                    echo "    [V{$idx}.I{$jdx}.A{$kdx}/{$totalV}.{$totalI}.{$totalA}] Article “{$art->Title}”\n";
                    $am         = $art->toMap();
                    $am['File'] = $cacheAsset($art, 'File');
                    $auths      = [];
                    foreach ($art->Authors()->sort('Sort') as $author) {
                        $auths[] = $author->toMap();
                    }
                    $am['Authors'] = $auths;
                    $dumpArts[]   = $am;
                }
                $im['Articles'] = $dumpArts;
                $dumpIssues[]   = $im;
            }
            $v['Issues']   = $dumpIssues;
            $dumpVolumes[] = $v;
        }
        $data['Volumes'] = $dumpVolumes;

        // Write JSON
        echo "[INFO] Writing JSON to {$cachePath}... ";
        $fp   = fopen($cachePath, 'wb');
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if (!$fp || $json === false || fwrite($fp, $json) === false) {
            throw new \RuntimeException("Failed to write JSON to {$cachePath}");
        }
        fclose($fp);
        echo "OK\n";

        echo "[SUCCESS] Export complete. JSON: {$cachePath}, Assets: {$assetsFolder}\n";
    }
}
