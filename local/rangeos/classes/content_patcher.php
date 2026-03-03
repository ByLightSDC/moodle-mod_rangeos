<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/**
 * Read and modify AU config.json files stored in Moodle's file API.
 *
 * Library content is stored in the mod_cmi5 library_content filearea under
 * the system context, with the version ID as itemid. The AU URL tells us
 * which directory to find config.json in.
 *
 * @package    local_rangeos
 * @copyright  2026 David Ropte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_patcher {

    /**
     * Read and parse an AU's config.json from library content.
     *
     * @param int $versionid The package version ID (itemid in file storage).
     * @param string $auurl The AU URL from cmi5_package_aus.url (e.g. "cyber-101/index.html").
     * @return array|null Decoded config.json contents, or null if not found.
     */
    public static function get_au_config(int $versionid, string $auurl): ?array {
        $file = self::get_config_file($versionid, $auurl);
        if (!$file) {
            return null;
        }

        $content = $file->get_content();
        $decoded = json_decode($content, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Patch fields in an AU's config.json.
     *
     * Reads the existing file, merges the provided patches, then writes it back.
     * Moodle file API requires delete + recreate to update content.
     *
     * @param int $versionid The package version ID.
     * @param string $auurl The AU URL from cmi5_package_aus.url.
     * @param array $patches Key-value pairs to merge into config.json.
     * @throws \moodle_exception If config.json cannot be found or is invalid JSON.
     */
    public static function patch_au_config(int $versionid, string $auurl, array $patches): void {
        $file = self::get_config_file($versionid, $auurl);
        if (!$file) {
            throw new \moodle_exception('error:confignotfound', 'local_rangeos', '',
                "config.json not found for version {$versionid}, AU URL: {$auurl}");
        }

        $content = $file->get_content();
        $config = json_decode($content, true);
        if ($config === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('error:configinvalid', 'local_rangeos', '',
                "config.json contains invalid JSON for version {$versionid}");
        }

        // Merge patches into top-level config.
        foreach ($patches as $key => $value) {
            $config[$key] = $value;
        }

        // Also patch promptClass inside slide scenario blocks.
        // Slides contain :::scenario JSON blocks with their own promptClass field
        // that the player reads at runtime.
        if (array_key_exists('promptClassId', $patches)) {
            $promptclass = $patches['promptClassId'];
            if (!empty($config['slides'])) {
                foreach ($config['slides'] as &$slide) {
                    if (empty($slide['content'])) {
                        continue;
                    }
                    // Match :::scenario\n```json\n{...}\n```\n::: blocks.
                    $slide['content'] = preg_replace_callback(
                        '/:::scenario\s*```json\s*(\{.*?\})\s*```\s*:::/s',
                        function ($matches) use ($promptclass) {
                            $scenariodata = json_decode($matches[1], true);
                            if ($scenariodata !== null) {
                                $scenariodata['promptClass'] = $promptclass;
                                $newjson = json_encode($scenariodata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                return ":::scenario\n```json\n{$newjson}\n```\n:::";
                            }
                            return $matches[0];
                        },
                        $slide['content']
                    );
                }
                unset($slide);
            }
        }

        $newcontent = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Moodle file API: must delete and recreate to update content.
        $filerecord = [
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
        ];

        $file->delete();

        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord, $newcontent);
    }

    /**
     * Get the stored_file object for an AU's config.json.
     *
     * Derives the config.json path from the AU URL. For example, if the AU URL is
     * "cyber-101/index.html", config.json is at filepath "/cyber-101/" filename "config.json".
     *
     * @param int $versionid The package version ID.
     * @param string $auurl The AU URL from cmi5_package_aus.url.
     * @return \stored_file|null The file object, or null if not found.
     */
    private static function get_config_file(int $versionid, string $auurl): ?\stored_file {
        $fs = get_file_storage();
        $syscontext = \context_system::instance();

        // Derive directory from AU URL.
        $dir = dirname($auurl);
        if ($dir === '' || $dir === '.') {
            $filepath = '/';
        } else {
            $filepath = '/' . ltrim($dir, '/');
            if (substr($filepath, -1) !== '/') {
                $filepath .= '/';
            }
        }

        $file = $fs->get_file(
            $syscontext->id,
            'mod_cmi5',
            'library_content',
            $versionid,
            $filepath,
            'config.json'
        );

        if (!$file || $file->is_directory()) {
            return null;
        }

        return $file;
    }
}
