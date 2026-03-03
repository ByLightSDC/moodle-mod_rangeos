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
 * Manages RangeOS environment configurations and their linked cmi5 launch profiles.
 *
 * @package    local_rangeos
 * @copyright  2026 David Ropte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class environment_manager {

    /**
     * Save (insert or update) a RangeOS environment.
     *
     * @param \stdClass $data Form data.
     * @return \stdClass The saved environment record.
     */
    public static function save_environment(\stdClass $data): \stdClass {
        global $DB;

        $now = time();

        if (!empty($data->id)) {
            // Update.
            $env = $DB->get_record('local_rangeos_environments', ['id' => $data->id], '*', MUST_EXIST);
            $env->name = $data->name;
            $env->apibaseurl = $data->apibaseurl;
            $env->gqlurl = $data->gqlurl;
            $env->gqlsubscriptionsurl = $data->gqlsubscriptionsurl ?? '';
            $env->keycloakurl = $data->keycloakurl;
            $env->keycloakrealm = $data->keycloakrealm;
            $env->keycloakclientid = $data->keycloakclientid;
            $env->keycloakscope = $data->keycloakscope ?? '';
            $env->slidelogo = $data->slidelogo ?? '';
            $env->auth_token_url = $data->auth_token_url;
            $env->auth_client_id = $data->auth_client_id;
            $env->auth_client_secret = $data->auth_client_secret;
            $env->isdefault = !empty($data->isdefault) ? 1 : 0;
            $env->timemodified = $now;

            $DB->update_record('local_rangeos_environments', $env);
        } else {
            // Insert.
            $env = new \stdClass();
            $env->name = $data->name;
            $env->apibaseurl = $data->apibaseurl;
            $env->gqlurl = $data->gqlurl;
            $env->gqlsubscriptionsurl = $data->gqlsubscriptionsurl ?? '';
            $env->keycloakurl = $data->keycloakurl;
            $env->keycloakrealm = $data->keycloakrealm;
            $env->keycloakclientid = $data->keycloakclientid;
            $env->keycloakscope = $data->keycloakscope ?? '';
            $env->slidelogo = $data->slidelogo ?? '';
            $env->auth_token_url = $data->auth_token_url;
            $env->auth_client_id = $data->auth_client_id;
            $env->auth_client_secret = $data->auth_client_secret;
            $env->isdefault = !empty($data->isdefault) ? 1 : 0;
            $env->timecreated = $now;
            $env->timemodified = $now;

            $env->id = $DB->insert_record('local_rangeos_environments', $env);
        }

        // If marked as default, clear default flag on all others.
        if ($env->isdefault) {
            $DB->execute(
                'UPDATE {local_rangeos_environments} SET isdefault = 0 WHERE id != ?',
                [$env->id]
            );
        }

        // Sync the linked cmi5_launch_profiles record.
        self::sync_launch_profile($env->id);

        return $DB->get_record('local_rangeos_environments', ['id' => $env->id]);
    }

    /**
     * Build launch parameter JSON and upsert into cmi5_launch_profiles.
     *
     * @param int $envid Environment record ID.
     */
    public static function sync_launch_profile(int $envid): void {
        global $DB;

        $env = $DB->get_record('local_rangeos_environments', ['id' => $envid], '*', MUST_EXIST);

        $params = [];
        $params['DEVOPS_API_URL'] = $env->apibaseurl;
        $params['DEVOPS_GQL_URL'] = $env->gqlurl;
        if (!empty($env->gqlsubscriptionsurl)) {
            $params['DEVOPS_GQL_SUBSCRIPTIONS_URL'] = $env->gqlsubscriptionsurl;
        }
        $params['KEYCLOAK_URL'] = $env->keycloakurl;
        $params['KEYCLOAK_REALM'] = $env->keycloakrealm;
        $params['KEYCLOAK_CLIENT_ID'] = $env->keycloakclientid;
        if (!empty($env->keycloakscope)) {
            $params['KEYCLOAK_SCOPE'] = $env->keycloakscope;
        }
        if (!empty($env->slidelogo)) {
            $params['SLIDE_LOGO'] = $env->slidelogo;
        }

        $paramjson = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $now = time();
        $profilename = 'RangeOS: ' . $env->name;

        if (!empty($env->profileid)) {
            // Update existing profile.
            $profile = $DB->get_record('cmi5_launch_profiles', ['id' => $env->profileid]);
            if ($profile) {
                $profile->name = $profilename;
                $profile->parameters = $paramjson;
                $profile->timemodified = $now;
                $DB->update_record('cmi5_launch_profiles', $profile);
                return;
            }
            // Profile was deleted externally; fall through to create.
        }

        // Create new profile.
        $profile = new \stdClass();
        $profile->name = $profilename;
        $profile->parameters = $paramjson;
        $profile->timecreated = $now;
        $profile->timemodified = $now;
        $profile->id = $DB->insert_record('cmi5_launch_profiles', $profile);

        // Store FK back.
        $DB->set_field('local_rangeos_environments', 'profileid', $profile->id, ['id' => $envid]);
    }

    /**
     * Delete an environment and its linked launch profile.
     *
     * @param int $id Environment record ID.
     */
    public static function delete_environment(int $id): void {
        global $DB;

        $env = $DB->get_record('local_rangeos_environments', ['id' => $id]);
        if (!$env) {
            return;
        }

        // Delete linked launch profile.
        if (!empty($env->profileid)) {
            $DB->delete_records('cmi5_launch_profiles', ['id' => $env->profileid]);
        }

        $DB->delete_records('local_rangeos_environments', ['id' => $id]);
    }

    /**
     * Get the profile ID of the default environment.
     *
     * @return int|null Profile ID or null if no default.
     */
    public static function get_default_profile_id(): ?int {
        global $DB;
        $env = $DB->get_record('local_rangeos_environments', ['isdefault' => 1]);
        return $env ? (int) $env->profileid : null;
    }

    /**
     * Get the default environment record.
     *
     * @return \stdClass|null
     */
    public static function get_default_environment(): ?\stdClass {
        global $DB;
        $env = $DB->get_record('local_rangeos_environments', ['isdefault' => 1]);
        return $env ?: null;
    }

    /**
     * List all environments.
     *
     * @return \stdClass[] Array of environment records.
     */
    public static function list_environments(): array {
        global $DB;
        return $DB->get_records('local_rangeos_environments', null, 'name ASC');
    }

    /**
     * Get the environment record linked to a given profile ID.
     *
     * @param int $profileid Launch profile ID.
     * @return \stdClass|null
     */
    public static function get_environment_by_profile(int $profileid): ?\stdClass {
        global $DB;
        $env = $DB->get_record('local_rangeos_environments', ['profileid' => $profileid]);
        return $env ?: null;
    }
}
