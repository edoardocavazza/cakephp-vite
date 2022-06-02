<?php

namespace ViteHelper\Utilities;

use Cake\Core\Configure;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\Strings;

class ViteManifest
{
    protected int $devPort;
    protected string $jsSrcDirectory;
    protected string $mainJs;
    protected ?string $baseDir;
    protected string $manifestDir;
    protected \stdClass $manifest;

    public function __construct(array $config = [])
    {
        $config = array_merge(
            Configure::read('ViteHelper', []),
            $config,
        );

        $this->devPort = $config['devPort'];
        $this->jsSrcDirectory = $config['jsSrcDirectory'];
        $this->mainJs = $config['mainJs'];
        $this->baseDir = $config['baseDir'];
        $this->manifestDir = $config['manifestDir'];
        $this->manifest = $this->getManifest();
    }

    public function getCssFiles(): array
    {
        $css_paths = [];

        foreach ($this->manifest as $file) {

            if (empty($file->isEntry) || empty($file->css)) {
                continue;
            }

            foreach ($file->css as $css_path) {
                $css_paths[] = DS . ltrim($css_path, DS);
            }
        }

        return $css_paths;
    }

    public function getJsFiles(bool $only_entry=true): array
    {
        $script_paths = [];

        foreach ($this->manifest as $file) {
            /**
             * @var \stdClass $file
             */
            if (!$only_entry || !empty($file->isEntry)) {
                $script_paths[] = DS . ltrim($file->file, DS);
            }
        }

        /**
         * Legacy Polyfills must come first.
         */
        usort($script_paths, function ($tag) {
            return Strings::contains($tag, "polyfills") ? 0 : 1;
        });

        /**
         * ES-module scripts must come last.
         */
        usort($script_paths, function ($tag) {
            return !Strings::contains($tag, "legacy") ? 1 : 0;
        });

        return $script_paths;
    }

    public function getPath(): string
    {
        if ($this->baseDir) {

            return rtrim($this->baseDir, DS) . DS . ltrim($this->manifestDir, DS);
        }

        return WWW_ROOT . ltrim($this->manifestDir, DS);
    }

    public function getBuildAssetsDir(): string
    {
        $file = current($this->getJsFiles());


        if ($this->baseDir) {

            return rtrim($this->baseDir, DS) . DS . ltrim(Strings::before($file, DS, -1), DS);
        }

        return WWW_ROOT . ltrim(Strings::before($file, DS, -1), DS);
    }

    protected function getManifest(): \stdClass
    {
        $path = $this->getPath();

        try {
            $json = FileSystem::read($path);

            $json = str_replace([
                "\u0000",
            ], '', $json);

            $manifest = Json::decode($json);

        } catch (\Exception $e) {
            throw new \Exception("No valid manifest.json found at path $path. Did you build your js? Error: {$e->getMessage()}");
        }

        return $manifest;
    }
}
