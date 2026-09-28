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

/**
 * Coverage for bulk AU config.json reads from library content.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_rangeos\content_patcher::get_au_configs_for_versions
 * @covers \local_rangeos\content_patcher::get_all_au_configs
 */
final class content_patcher_configs_test extends \advanced_testcase {

    /**
     * Store a library content file for a package version.
     *
     * @param int $versionid Package version ID (itemid).
     * @param string $filepath Moodle filepath, e.g. '/cyber-101/'.
     * @param string $filename File name.
     * @param string $content File contents.
     */
    private function store_file(int $versionid, string $filepath, string $filename, string $content): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'mod_cmi5',
            'filearea' => 'library_content',
            'itemid' => $versionid,
            'filepath' => $filepath,
            'filename' => $filename,
        ], $content);
    }

    /** Configs come back keyed by version and filepath, ignoring a package's other content. */
    public function test_configs_are_grouped_by_version_and_filepath(): void {
        $this->resetAfterTest();
        $this->store_file(11, '/cyber-101/', 'config.json', '{"rangeosScenarioName":"Alpha"}');
        $this->store_file(11, '/cyber-102/', 'config.json', '{"rangeosScenarioName":"Beta"}');
        $this->store_file(11, '/cyber-101/', 'index.html', '<html></html>');
        $this->store_file(22, '/net-201/', 'config.json', '{"rangeosScenarioName":"Gamma"}');

        $configs = content_patcher::get_au_configs_for_versions([11, 22]);

        $this->assertSame([11, 22], array_keys($configs));
        $this->assertSame(['/cyber-101/', '/cyber-102/'], array_keys($configs[11]));
        $this->assertSame('Alpha', $configs[11]['/cyber-101/']['rangeosScenarioName']);
        $this->assertSame('Beta', $configs[11]['/cyber-102/']['rangeosScenarioName']);
        $this->assertSame(['/net-201/' => ['rangeosScenarioName' => 'Gamma']], $configs[22]);
    }

    /** A requested version with no configs is still present, and other versions do not leak in. */
    public function test_versions_are_isolated(): void {
        $this->resetAfterTest();
        $this->store_file(11, '/cyber-101/', 'config.json', '{"rangeosScenarioName":"Alpha"}');
        $this->store_file(33, '/other/', 'config.json', '{"rangeosScenarioName":"Unwanted"}');

        $configs = content_patcher::get_au_configs_for_versions([11, 22]);

        $this->assertSame([], $configs[22]);
        $this->assertCount(1, $configs[11]);
        $this->assertArrayNotHasKey(33, $configs);
        $this->assertSame([], content_patcher::get_au_configs_for_versions([]));
    }

    /** Invalid JSON is omitted rather than returned as a null config. */
    public function test_invalid_json_is_omitted(): void {
        $this->resetAfterTest();
        $this->store_file(11, '/broken/', 'config.json', '{not json');
        $this->store_file(11, '/valid/', 'config.json', '{"rangeosScenarioName":"Alpha"}');

        $configs = content_patcher::get_au_configs_for_versions([11]);

        $this->assertSame(['/valid/'], array_keys($configs[11]));
    }

    /** The single-version wrapper matches the bulk result and tolerates unknown versions. */
    public function test_single_version_wrapper(): void {
        $this->resetAfterTest();
        $this->store_file(11, '/cyber-101/', 'config.json', '{"rangeosScenarioName":"Alpha"}');

        $this->assertSame(
            content_patcher::get_au_configs_for_versions([11])[11],
            content_patcher::get_all_au_configs(11)
        );
        $this->assertSame([], content_patcher::get_all_au_configs(99));
        $this->assertSame([], content_patcher::get_all_au_configs(0));
    }
}
