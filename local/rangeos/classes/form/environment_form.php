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

namespace local_rangeos\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing RangeOS environment configurations.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class environment_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // General settings.
        $mform->addElement('header', 'generalhdr', get_string('general'));

        $mform->addElement('text', 'name', get_string('name', 'local_rangeos'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required');
        $mform->addHelpButton('name', 'name', 'local_rangeos');

        $mform->addElement('advcheckbox', 'isdefault', get_string('isdefault', 'local_rangeos'));
        $mform->addHelpButton('isdefault', 'isdefault', 'local_rangeos');

        // AU launch parameters (sent to the AU via LMS.LaunchData).
        $mform->addElement('header', 'launchparamshdr', get_string('rangeos_integration', 'local_rangeos'));

        $mform->addElement('text', 'apibaseurl', get_string('apibaseurl', 'local_rangeos'),
            ['size' => 60, 'placeholder' => 'https://devops-api.develop-cp.rangeos.engineering']);
        $mform->setType('apibaseurl', PARAM_URL);
        $mform->addRule('apibaseurl', get_string('required'), 'required');
        $mform->addHelpButton('apibaseurl', 'apibaseurl', 'local_rangeos');

        $mform->addElement('text', 'gqlurl', get_string('gqlurl', 'local_rangeos'),
            ['size' => 60, 'placeholder' => 'https://gql.develop-cp.rangeos.engineering/graphql']);
        $mform->setType('gqlurl', PARAM_URL);
        $mform->addRule('gqlurl', get_string('required'), 'required');
        $mform->addHelpButton('gqlurl', 'gqlurl', 'local_rangeos');

        $mform->addElement('text', 'gqlsubscriptionsurl',
            get_string('gqlsubscriptionsurl', 'local_rangeos'),
            ['size' => 60, 'placeholder' => 'wss://gql.develop-cp.rangeos.engineering/graphql']);
        $mform->setType('gqlsubscriptionsurl', PARAM_URL);
        $mform->addHelpButton('gqlsubscriptionsurl', 'gqlsubscriptionsurl', 'local_rangeos');

        $mform->addElement('text', 'keycloakurl', get_string('keycloakurl', 'local_rangeos'),
            ['size' => 60, 'placeholder' => 'https://keycloak.develop-cp.rangeos.engineering']);
        $mform->setType('keycloakurl', PARAM_URL);
        $mform->addRule('keycloakurl', get_string('required'), 'required');
        $mform->addHelpButton('keycloakurl', 'keycloakurl', 'local_rangeos');

        $mform->addElement('text', 'keycloakrealm', get_string('keycloakrealm', 'local_rangeos'),
            ['size' => 40, 'placeholder' => 'rangeos']);
        $mform->setType('keycloakrealm', PARAM_TEXT);
        $mform->addRule('keycloakrealm', get_string('required'), 'required');
        $mform->addHelpButton('keycloakrealm', 'keycloakrealm', 'local_rangeos');

        $mform->addElement('text', 'keycloakclientid',
            get_string('keycloakclientid', 'local_rangeos'),
            ['size' => 40, 'placeholder' => 'rangeos-web']);
        $mform->setType('keycloakclientid', PARAM_TEXT);
        $mform->addRule('keycloakclientid', get_string('required'), 'required');
        $mform->addHelpButton('keycloakclientid', 'keycloakclientid', 'local_rangeos');

        $mform->addElement('text', 'keycloakscope', get_string('keycloakscope', 'local_rangeos'),
            ['size' => 40, 'placeholder' => 'openid profile email']);
        $mform->setType('keycloakscope', PARAM_TEXT);
        $mform->addHelpButton('keycloakscope', 'keycloakscope', 'local_rangeos');

        $mform->addElement('text', 'lightlogo', get_string('lightlogo', 'local_rangeos'),
            ['size' => 60, 'placeholder' => '/images/logo-light.svg']);
        $mform->setType('lightlogo', PARAM_TEXT);
        $mform->addHelpButton('lightlogo', 'lightlogo', 'local_rangeos');

        $mform->addElement('text', 'darklogo', get_string('darklogo', 'local_rangeos'),
            ['size' => 60, 'placeholder' => '/images/logo-dark.svg']);
        $mform->setType('darklogo', PARAM_TEXT);
        $mform->addHelpButton('darklogo', 'darklogo', 'local_rangeos');

        // M2M authentication credentials (server-side only, not in launch params).
        $mform->addElement('header', 'authhdr', get_string('authentication', 'admin'));

        $mform->addElement('text', 'auth_token_url',
            get_string('auth_token_url', 'local_rangeos'),
            ['size' => 60, 'placeholder' => 'https://keycloak.develop-cp.rangeos.engineering/realms/rangeos/protocol/openid-connect/token']);
        $mform->setType('auth_token_url', PARAM_URL);
        $mform->addRule('auth_token_url', get_string('required'), 'required');
        $mform->addHelpButton('auth_token_url', 'auth_token_url', 'local_rangeos');

        $mform->addElement('text', 'auth_client_id',
            get_string('auth_client_id', 'local_rangeos'),
            ['size' => 40, 'placeholder' => 'moodle-m2m']);
        $mform->setType('auth_client_id', PARAM_TEXT);
        $mform->addRule('auth_client_id', get_string('required'), 'required');
        $mform->addHelpButton('auth_client_id', 'auth_client_id', 'local_rangeos');

        $mform->addElement('passwordunmask', 'auth_client_secret',
            get_string('auth_client_secret', 'local_rangeos'), ['size' => 40]);
        $mform->setType('auth_client_secret', PARAM_RAW);
        $mform->addRule('auth_client_secret', get_string('required'), 'required');
        $mform->addHelpButton('auth_client_secret', 'auth_client_secret', 'local_rangeos');

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check unique name.
        global $DB;
        $existing = $DB->get_record('local_rangeos_environments', ['name' => $data['name']]);
        if ($existing && (empty($data['id']) || (int) $existing->id !== (int) $data['id'])) {
            $errors['name'] = get_string('error', 'moodle') . ': name already exists.';
        }

        return $errors;
    }
}
