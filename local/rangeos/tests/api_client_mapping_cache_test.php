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
 * Tests for concurrent, cached AU mapping reads.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/** API client test double which avoids real authentication and HTTP calls. */
class api_client_mapping_cache_test_double extends api_client {
    /** @var array Waves passed to the concurrent request method. */
    public array $waves = [];

    /** @var int Number of authentication attempts. */
    public int $authenticationcount = 0;

    /** @var int Number of content-scenario API calls. */
    public int $scenariocallcount = 0;

    /** Do not contact the token endpoint. */
    public function authenticate(bool $force = false): void {
        $this->authenticationcount++;
    }

    /** Return deterministic mapping responses. */
    protected function do_parallel_au_mapping_requests(array $auids): array {
        $this->waves[] = $auids;
        $responses = [];
        foreach ($auids as $auid) {
            if ($auid === 'urn:test:missing') {
                $responses[$auid] = ['httpcode' => 404, 'body' => '', 'error' => ''];
            } else {
                $responses[$auid] = [
                    'httpcode' => 200,
                    'body' => json_encode(['auId' => $auid, 'scenarios' => []]),
                    'error' => '',
                ];
            }
        }
        return $responses;
    }

    /** Return a deterministic content-scenario response. */
    public function list_content_scenarios(array $params = []): array {
        $this->scenariocallcount++;
        return [
            'data' => [
                ['uuid' => 'scenario-1', 'name' => 'Scenario One'],
            ],
        ];
    }
}

/**
 * @covers \local_rangeos\api_client::get_au_mappings_by_ids
 * @covers \local_rangeos\api_client::get_cached_content_scenarios
 */
final class api_client_mapping_cache_test extends \advanced_testcase {
    /** Cache hits avoid HTTP, misses are fetched in waves no larger than five. */
    public function test_concurrency_cap_and_cache_hits(): void {
        $this->resetAfterTest();
        \cache_helper::purge_by_definition('local_rangeos', 'aumappings');

        $environment = (object) [
            'id' => 42,
            'apibaseurl' => 'https://rangeos.example.test',
            'auth_token_url' => 'https://auth.example.test/token',
            'auth_client_id' => 'client',
            'auth_client_secret' => 'secret',
        ];
        $client = new api_client_mapping_cache_test_double($environment);
        $auids = [];
        for ($i = 0; $i < 6; $i++) {
            $auids[] = 'urn:test:au:' . $i;
        }
        $auids[] = 'urn:test:missing';

        $first = $client->get_au_mappings_by_ids($auids, 99);
        $this->assertSame([5, 2], array_map('count', $client->waves));
        $this->assertSame(1, $client->authenticationcount);
        $this->assertNull($first['urn:test:missing']);
        $this->assertSame('urn:test:au:0', $first['urn:test:au:0']['auId']);
        $firststats = $client->get_last_mapping_read_stats();
        $this->assertSame(0, $firststats['cachehits']);
        $this->assertSame(7, $firststats['cachemisses']);
        $this->assertSame(2, $firststats['waves']);

        $second = $client->get_au_mappings_by_ids($auids, 5);
        $this->assertSame($first, $second);
        $this->assertSame([5, 2], array_map('count', $client->waves));
        $this->assertSame(1, $client->authenticationcount);
        $secondstats = $client->get_last_mapping_read_stats();
        $this->assertSame(7, $secondstats['cachehits']);
        $this->assertSame(0, $secondstats['cachemisses']);
        $this->assertSame(0, $secondstats['waves']);
    }

    /** The content-scenario catalog is reused for the same environment and parameters. */
    public function test_content_scenario_cache(): void {
        $this->resetAfterTest();
        \cache_helper::purge_by_definition('local_rangeos', 'contentscenarios');

        $environment = (object) [
            'id' => 43,
            'apibaseurl' => 'https://rangeos.example.test',
            'auth_token_url' => 'https://auth.example.test/token',
            'auth_client_id' => 'client',
            'auth_client_secret' => 'secret',
        ];
        $client = new api_client_mapping_cache_test_double($environment);

        $first = $client->get_cached_content_scenarios(['limit' => 1000]);
        $this->assertFalse($client->was_last_content_scenario_cache_hit());
        $this->assertSame(1, $client->scenariocallcount);

        $second = $client->get_cached_content_scenarios(['limit' => 1000]);
        $this->assertTrue($client->was_last_content_scenario_cache_hit());
        $this->assertSame(1, $client->scenariocallcount);
        $this->assertSame($first, $second);

        $client->get_cached_content_scenarios(['limit' => 500]);
        $this->assertFalse($client->was_last_content_scenario_cache_hit());
        $this->assertSame(2, $client->scenariocallcount);
    }
}
