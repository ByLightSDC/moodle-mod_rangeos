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

/** Exposes Moodle's URL validation for native curl_multi requests. */
class parallel_curl_security extends \curl {
    /**
     * Validate a URL and return the DNS resolution Moodle approved for it.
     *
     * Pinned resolution is only available when $CFG->curlsecurityblockedhosts is configured;
     * without it the security helper never records a host, so return no CURLOPT_RESOLVE
     * entries and let curl resolve normally, as Moodle's own curl wrapper does.
     *
     * @param string $url URL to validate.
     * @return array CURLOPT_RESOLVE entries, empty when curl security is not enabled.
     * @throws \moodle_exception When Moodle blocks the URL.
     */
    public function validate(string $url): array {
        $error = $this->check_securityhelper_blocklist($url);
        if ($error !== null) {
            throw new \moodle_exception('error:apiconnection', 'local_rangeos', '', $error);
        }
        try {
            return $this->get_security()->get_resolve_info();
        } catch (\coding_exception $e) {
            return [];
        }
    }
}
