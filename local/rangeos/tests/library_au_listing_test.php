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
 * Regression coverage for local library scope and pagination.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_rangeos\library_au_listing
 */
final class library_au_listing_test extends \advanced_testcase {
    /**
     * Create a library package with current and historical AUs, without deployed activities.
     *
     * @param string $title Package title.
     * @param int $count Number of current AUs.
     * @return int Package ID.
     */
    private function create_package(string $title, int $count): int {
        global $DB;

        $packageid = $DB->insert_record('cmi5_packages', ['title' => $title]);
        foreach ([1, 2] as $versionnumber) {
            $versionid = $DB->insert_record('cmi5_package_versions', [
                'packageid' => $packageid,
                'versionnumber' => $versionnumber,
                'createdby' => 2,
            ]);
            for ($i = 0; $i < ($versionnumber === 2 ? $count : 1); $i++) {
                $DB->insert_record('cmi5_package_aus', [
                    'versionid' => $versionid,
                    'auid' => "urn:test:version:$versionnumber:au:$i",
                    'title' => "AU $i",
                    'url' => "au$i/index.html",
                    'sortorder' => $i,
                ]);
            }
        }
        $DB->set_field('cmi5_packages', 'latestversion', $versionid, ['id' => $packageid]);
        return $packageid;
    }

    /** Current package AUs are included without a local activity or remote mapping. */
    public function test_library_scope_and_shared_iris(): void {
        $this->resetAfterTest();
        $first = $this->create_package('A', 3);
        $second = $this->create_package('B', 2);
        $page = library_au_listing::get_page(0, 0, 20);
        $this->assertSame(5, $page['total']);
        $this->assertCount(5, $page['aus']);
        $this->assertEquals([$first, $first, $first, $second, $second],
            array_values(array_column($page['aus'], 'packageid')));
        foreach ($page['aus'] as $au) {
            $this->assertStringContainsString('version:2:', $au->auid);
        }
    }

    /** Package filtering happens before counting and pages never overlap. */
    public function test_package_pagination(): void {
        $this->resetAfterTest();
        $packageid = $this->create_package('A', 45);
        $this->create_package('B', 3);
        $ids = [];
        foreach ([20, 20, 5] as $number => $expectedcount) {
            $page = library_au_listing::get_page($packageid, $number, 20);
            $this->assertSame(45, $page['total']);
            $this->assertSame($number, $page['page']);
            $this->assertCount($expectedcount, $page['aus']);
            $ids = array_merge($ids, array_keys($page['aus']));
        }
        $this->assertCount(45, array_unique($ids));
        foreach ([50, 100] as $size) {
            $page = library_au_listing::get_page($packageid, 0, $size);
            $this->assertCount(45, $page['aus']);
            $this->assertSame($size, $page['pagesize']);
        }
    }

    /** Invalid sizes/pages and empty packages produce a valid bounded result. */
    public function test_bounds_and_empty_packages(): void {
        $this->resetAfterTest();
        $packageid = $this->create_package('A', 21);
        $page = library_au_listing::get_page($packageid, 999, 20);
        $this->assertSame(1, $page['page']);
        $this->assertCount(1, $page['aus']);
        foreach ([0, -1, 999] as $size) {
            $page = library_au_listing::get_page($packageid, -1, $size);
            $this->assertSame(0, $page['page']);
            $this->assertSame(20, $page['pagesize']);
            $this->assertCount(20, $page['aus']);
        }
        $emptyid = $this->create_package('Empty', 0);
        $page = library_au_listing::get_page($emptyid, 999, 20);
        $this->assertSame(0, $page['total']);
        $this->assertSame(0, $page['page']);
        $this->assertSame([], $page['aus']);
    }
}
