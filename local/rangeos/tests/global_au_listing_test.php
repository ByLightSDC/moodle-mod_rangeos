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
 * Regression tests for the RangeOS offset/limit pagination contract.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/** @covers \local_rangeos\global_au_listing */
final class global_au_listing_test extends \advanced_testcase {
    /** Remote pages must not be sliced a second time. */
    public function test_second_page_uses_offset_and_limit(): void {
        $client = $this->getMockBuilder(api_client::class)->disableOriginalConstructor()
            ->onlyMethods(['list_au_mappings'])->getMock();
        $records = [['auId' => 'remote-only-au']];
        $client->expects($this->once())->method('list_au_mappings')
            ->with(['offset' => 25, 'limit' => 25, 'sortBy' => 'dateCreated', 'sort' => 'asc'])
            ->willReturn(['data' => $records, 'totalCount' => 26]);
        $result = global_au_listing::get_page($client, 1, 25);
        $this->assertSame($records, $result['mappings']);
        $this->assertSame(26, $result['total']);
        $this->assertSame(1, $result['page']);
    }

    /** Stale page links are corrected using the API's total count. */
    public function test_past_end_page_is_refetched(): void {
        $client = $this->getMockBuilder(api_client::class)->disableOriginalConstructor()
            ->onlyMethods(['list_au_mappings'])->getMock();
        $offsets = [];
        $client->expects($this->exactly(2))->method('list_au_mappings')
            ->willReturnCallback(function(array $params) use (&$offsets): array {
                $offsets[] = $params['offset'];
                return ['data' => $params['offset'] === 50 ? [['auId' => 'last']] : [], 'totalCount' => 51];
            });
        $result = global_au_listing::get_page($client, 99, 25);
        $this->assertSame([2475, 50], $offsets);
        $this->assertSame(2, $result['page']);
        $this->assertSame([['auId' => 'last']], $result['mappings']);
    }

    /** Empty environments never produce a phantom page. */
    public function test_empty_environment(): void {
        $client = $this->getMockBuilder(api_client::class)->disableOriginalConstructor()
            ->onlyMethods(['list_au_mappings'])->getMock();
        $client->expects($this->once())->method('list_au_mappings')
            ->willReturn(['data' => [], 'totalCount' => 0]);
        $result = global_au_listing::get_page($client, 99, 100);
        $this->assertSame(0, $result['page']);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['mappings']);
    }

    /** Servers that ignore offset/limit still produce one page of rows. */
    public function test_unpaged_response_is_sliced_locally(): void {
        $client = $this->getMockBuilder(api_client::class)->disableOriginalConstructor()
            ->onlyMethods(['list_au_mappings'])->getMock();
        $records = [];
        for ($i = 0; $i < 493; $i++) {
            $records[] = ['auId' => 'au-' . $i];
        }
        $client->method('list_au_mappings')->willReturn(['data' => $records, 'totalCount' => 493]);

        $result = global_au_listing::get_page($client, 0, 25);
        $this->assertCount(25, $result['mappings']);
        $this->assertSame('au-0', $result['mappings'][0]['auId']);
        $this->assertSame(493, $result['total']);

        $result = global_au_listing::get_page($client, 1, 25);
        $this->assertSame('au-25', $result['mappings'][0]['auId']);

        $result = global_au_listing::get_page($client, 99, 100);
        $this->assertSame(4, $result['page']);
        $this->assertCount(93, $result['mappings']);
    }

    /** A bare list without a total count is still paginated. */
    public function test_unpaged_bare_list(): void {
        $client = $this->getMockBuilder(api_client::class)->disableOriginalConstructor()
            ->onlyMethods(['list_au_mappings'])->getMock();
        $records = array_map(fn($i) => ['auId' => 'au-' . $i], range(0, 59));
        $client->expects($this->once())->method('list_au_mappings')->willReturn($records);
        $result = global_au_listing::get_page($client, 2, 25);
        $this->assertSame(60, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertCount(10, $result['mappings']);
    }

    /** Invalid URL parameters cannot request an unbounded page. */
    public function test_invalid_parameters(): void {
        $client = $this->getMockBuilder(api_client::class)->disableOriginalConstructor()
            ->onlyMethods(['list_au_mappings'])->getMock();
        $client->expects($this->once())->method('list_au_mappings')
            ->with(['offset' => 0, 'limit' => 25, 'sortBy' => 'dateCreated', 'sort' => 'asc'])
            ->willReturn(['data' => [], 'totalCount' => 0]);
        $result = global_au_listing::get_page($client, -1, 0);
        $this->assertSame(0, $result['page']);
        $this->assertSame(25, $result['pagesize']);
        foreach ([25, 50, 100] as $size) {
            $this->assertSame($size, global_au_listing::page_size($size));
        }
        $this->assertSame(25, global_au_listing::page_size(-1));
        $this->assertSame(25, global_au_listing::page_size(999));
    }
}
