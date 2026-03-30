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
 * HTTP client for the RangeOS devops-api with Keycloak authentication.
 *
 * Authenticates via M2M client_credentials grant.  The M2M service account
 * must belong to an MTO organisation in Keycloak so that API calls carry
 * a valid mtoPath claim.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {

    /** @var string Base URL of the devops-api. */
    private string $baseurl;

    /** @var string Keycloak token endpoint. */
    private string $tokenurl;

    /** @var string OAuth2 client ID for M2M auth. */
    private string $clientid;

    /** @var string OAuth2 client secret for M2M auth. */
    private string $clientsecret;

    /** @var string|null Cached JWT access token. */
    private ?string $accesstoken = null;

    /** @var int Token expiry timestamp. */
    private int $tokenexpiry = 0;

    /**
     * Constructor.
     *
     * @param \stdClass $environment Environment record from local_rangeos_environments.
     */
    public function __construct(\stdClass $environment) {
        $this->baseurl = rtrim($environment->apibaseurl, '/');
        $this->tokenurl = $environment->auth_token_url;
        $this->clientid = $environment->auth_client_id;
        $this->clientsecret = $environment->auth_client_secret;
    }

    /**
     * Create an api_client from an environment ID.
     *
     * @param int $envid Environment record ID.
     * @return self
     */
    public static function from_environment(int $envid): self {
        global $DB;
        $env = $DB->get_record('local_rangeos_environments', ['id' => $envid], '*', MUST_EXIST);
        return new self($env);
    }

    /**
     * Authenticate for API calls via M2M client_credentials.
     *
     * @param bool $force Force re-authentication even if token is valid.
     * @throws \moodle_exception On authentication failure.
     */
    public function authenticate(bool $force = false): void {
        if (!$force && $this->accesstoken && time() < $this->tokenexpiry) {
            return;
        }

        $this->authenticate_m2m();
    }

    /**
     * Authenticate via OAuth2 client_credentials grant.
     *
     * @throws \moodle_exception On authentication failure.
     */
    private function authenticate_m2m(): void {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($this->clientid . ':' . $this->clientsecret),
            ],
        ]);

        $response = $curl->post($this->tokenurl, 'grant_type=client_credentials');

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        if ($httpcode !== 200) {
            throw new \moodle_exception('error:authentication', 'local_rangeos',
                '', "HTTP {$httpcode}: {$response}");
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \moodle_exception('error:authentication', 'local_rangeos',
                '', 'No access_token in response');
        }

        $this->accesstoken = $data['access_token'];
        // Expire 60 seconds early to avoid edge-case failures.
        $this->tokenexpiry = time() + ($data['expires_in'] ?? 300) - 60;
        $this->usingusertoken = false;
    }

    /**
     * List AU mappings.
     *
     * @param array $params Query parameters (page, pageSize, etc.).
     * @return array Decoded response.
     */
    public function list_au_mappings(array $params = []): array {
        return $this->get('/v1/cmi5/auMapping', $params);
    }

    /**
     * Get a single AU mapping by AU ID.
     *
     * @param string $auid AU IRI.
     * @return array|null Decoded response or null if not found.
     */
    public function get_au_mapping(string $auid): ?array {
        try {
            return $this->get('/v1/cmi5/auMapping/' . urlencode($auid));
        } catch (\moodle_exception $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Create an AU mapping.
     *
     * @param string $auid AU IRI.
     * @param string $name Mapping name.
     * @param array $scenarios Array of scenario objects.
     * @return array Decoded response.
     */
    public function create_au_mapping(string $auid, string $name, array $scenarios): array {
        return $this->post('/v1/cmi5/auMapping', [
            'auId' => $auid,
            'name' => $name,
            'scenarios' => $scenarios,
        ]);
    }

    /**
     * Update an AU mapping.
     *
     * @param string $auid AU IRI.
     * @param array $data Fields to update.
     * @return array Decoded response.
     */
    public function update_au_mapping(string $auid, array $data): array {
        return $this->put('/v1/cmi5/auMapping/' . urlencode($auid), $data);
    }

    /**
     * Delete an AU mapping.
     *
     * @param string $auid AU IRI.
     * @return void
     */
    public function delete_au_mapping(string $auid): void {
        $this->delete('/v1/cmi5/auMapping/' . urlencode($auid));
    }

    /**
     * List deployed scenario instances.
     *
     * @param array $params Query parameters (page, pageSize, class, etc.).
     * @return array Decoded response.
     */
    public function list_scenarios(array $params = []): array {
        return $this->get('/v1/cmi5/scenarios', $params);
    }

    /**
     * List scenario classes.
     *
     * @return array Decoded response.
     */
    public function list_scenario_classes(): array {
        return $this->get('/v1/cmi5/scenarios/classes');
    }

    /**
     * List content scenario definitions (library).
     *
     * @param array $params Query parameters (page, pageSize, search, etc.).
     * @return array Decoded response.
     */
    public function list_content_scenarios(array $params = []): array {
        return $this->get('/v1/content/range/scenarios', $params);
    }

    /**
     * Get scenario instances for a specific class.
     *
     * @param string $classid Class identifier.
     * @param array $params Additional query parameters.
     * @return array Decoded response.
     */
    public function get_class_instances(string $classid, array $params = []): array {
        $params['classId'] = $classid;
        return $this->get('/v1/cmi5/scenarios', $params);
    }

    /**
     * Create (prestage) a batch of scenario instances for a class.
     *
     * @param string $scenarioid Content scenario UUID.
     * @param string $classid Class identifier.
     * @param int $count Number of slots to prestage.
     * @return array Decoded response.
     */
    public function create_class(string $scenarioid, string $classid, int $count, string $enddate = ''): array {
        $body = [
            'classId' => $classid,
            'count' => $count,
            'endDate' => $enddate ?: gmdate('Y-m-d\TH:i:s\Z', strtotime('+6 months')),
        ];
        return $this->post('/v1/cmi5/scenarios/' . urlencode($scenarioid), $body);
    }

    /**
     * Delete (end) a scenario class.
     *
     * @param string $classid Class identifier.
     * @param string $enddate ISO 8601 end date.
     * @return void
     */
    public function delete_class(string $classid, string $enddate = ''): void {
        $body = ['classId' => $classid];
        if ($enddate !== '') {
            $body['endDate'] = $enddate;
        }
        $this->request('DELETE', '/v1/cmi5/scenarios/classes', [], $body);
    }

    /**
     * Add more seats (prestage additional instances) to an existing class.
     *
     * @param string $scenarioid Content scenario UUID.
     * @param string $classid Class identifier.
     * @param int $count Number of additional slots to prestage.
     * @return array Decoded response.
     */
    public function add_class_seats(string $scenarioid, string $classid, int $count): array {
        return $this->post('/v1/cmi5/scenarios/classes', [
            'scenarioId' => $scenarioid,
            'classId' => $classid,
            'count' => $count,
        ]);
    }

    /**
     * Delete (undeploy) a single scenario instance via the manage API.
     *
     * @param string $rangeid Range UUID where the scenario is deployed.
     * @param string $scenarioid The scenario instance UUID.
     * @return void
     */
    public function delete_scenario_instance(string $rangeid, string $scenarioid): void {
        $this->request('DELETE', '/v1/manage/range/' . urlencode($rangeid)
            . '/scenarios/' . urlencode($scenarioid));
    }

    /**
     * Perform a GET request.
     *
     * @param string $path API path.
     * @param array $params Query parameters.
     * @return array Decoded response.
     */
    private function get(string $path, array $params = []): array {
        return $this->request('GET', $path, $params);
    }

    /**
     * Perform a POST request.
     *
     * @param string $path API path.
     * @param array $body Request body.
     * @return array Decoded response.
     */
    private function post(string $path, array $body): array {
        return $this->request('POST', $path, [], $body);
    }

    /**
     * Perform a PUT request.
     *
     * @param string $path API path.
     * @param array $body Request body.
     * @return array Decoded response.
     */
    private function put(string $path, array $body): array {
        return $this->request('PUT', $path, [], $body);
    }

    /**
     * Perform a DELETE request.
     *
     * @param string $path API path.
     * @return void
     */
    private function delete(string $path): void {
        $this->request('DELETE', $path);
    }

    /**
     * Execute an authenticated HTTP request with auto-retry on 401.
     *
     * @param string $method HTTP method.
     * @param string $path API path.
     * @param array $queryparams Query parameters.
     * @param array|null $body JSON body for POST/PUT.
     * @return array Decoded response.
     * @throws \moodle_exception On HTTP errors.
     */
    private function request(string $method, string $path, array $queryparams = [],
            ?array $body = null): array {
        $this->authenticate();

        $url = $this->baseurl . $path;
        if (!empty($queryparams)) {
            $url .= '?' . http_build_query($queryparams);
        }

        $result = $this->do_request($method, $url, $body);

        // Auto-retry on 401: force re-auth (refresh user token or re-fetch M2M).
        if ($result['httpcode'] === 401) {
            $this->accesstoken = null;
            $this->tokenexpiry = 0;
            $this->authenticate(true);
            $result = $this->do_request($method, $url, $body);
        }

        if ($result['httpcode'] >= 400) {
            throw new \moodle_exception('error:apiconnection', 'local_rangeos',
                '', "HTTP {$result['httpcode']}: {$result['body']}");
        }

        if ($method === 'DELETE') {
            return [];
        }

        $decoded = json_decode($result['body'], true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('error:apiconnection', 'local_rangeos',
                '', 'Invalid JSON response');
        }

        return $decoded ?? [];
    }

    /**
     * Execute a raw HTTP request.
     *
     * @param string $method HTTP method.
     * @param string $url Full URL.
     * @param array|null $body JSON body.
     * @return array ['httpcode' => int, 'body' => string]
     */
    private function do_request(string $method, string $url, ?array $body = null): array {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
        ]);

        $headers = [
            'Authorization: Bearer ' . $this->accesstoken,
            'Accept: application/json',
        ];

        $postdata = '';
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $postdata = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $curl->setopt(['CURLOPT_HTTPHEADER' => $headers]);

        switch ($method) {
            case 'GET':
                $response = $curl->get($url);
                break;
            case 'POST':
                $response = $curl->post($url, $postdata);
                break;
            case 'PUT':
                $response = $curl->put($url, $postdata);
                break;
            case 'DELETE':
                $response = $curl->delete($url);
                break;
            default:
                throw new \coding_exception("Unsupported HTTP method: {$method}");
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        return ['httpcode' => (int) $httpcode, 'body' => $response];
    }
}
