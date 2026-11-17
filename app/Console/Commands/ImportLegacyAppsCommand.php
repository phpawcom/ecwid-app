<?php

namespace App\Console\Commands;

use App\Models\EcwidApp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecwid:import-legacy {path=legacy : Folder holding the v1 app}')]
#[Description('Register the Ecwid apps from the v1 config.php files as Thawani apps')]
class ImportLegacyAppsCommand extends Command
{
    public function handle(): int
    {
        $root = base_path($this->argument('path'));
        $configs = ['.' => "$root/config.php"];
        foreach (glob("$root/stores/*/config.php") as $file) {
            $configs['stores/'.basename(dirname($file))] = $file;
        }

        foreach ($configs as $legacyPath => $file) {
            if (! is_file($file)) {
                continue;
            }

            // Read the values without executing the old file.
            preg_match_all("/\\\$AppConfig\['(\w+)'\]\s*=\s*'([^']*)'/", file_get_contents($file), $m);
            $config = array_combine($m[1], $m[2]);

            if (empty($config['ecwid_app']) || empty($config['ecwid_secret'])) {
                $this->warn("Skipped $file: no ecwid_app/ecwid_secret.");

                continue;
            }

            $app = EcwidApp::updateOrCreate(['client_id' => $config['ecwid_app']], [
                'client_secret' => $config['ecwid_secret'],
                'gateway' => 'thawani',
                'name' => $legacyPath === '.' ? 'Thawani' : 'Thawani ('.basename($legacyPath).')',
                'legacy_path' => $legacyPath,
                'is_active' => true,
            ]);

            $this->components->twoColumnDetail($app->client_id, $legacyPath);
        }

        return self::SUCCESS;
    }
}
