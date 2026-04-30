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

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'RangeOS Integration';

// Capabilities.
$string['rangeos:manageenvironments'] = 'Manage RangeOS environments';
$string['rangeos:manageaumappings'] = 'Manage AU-to-scenario mappings';
$string['rangeos:viewaumappings'] = 'View AU-to-scenario mappings';

// Settings.
$string['settings'] = 'RangeOS settings';
$string['manageenvironments'] = 'Manage environments';
$string['manageaumappings'] = 'Manage AU mappings';

// Environment management.
$string['environments'] = 'RangeOS Environments';
$string['environment'] = 'RangeOS Environment';
$string['environment_help'] = 'Select a RangeOS environment to provide launch parameters (API URLs, Keycloak config) to this cmi5 activity. The environment\'s parameters will be merged into the AU launch data.';
$string['addenvironment'] = 'Add environment';
$string['editenvironment'] = 'Edit environment';
$string['deleteenvironment'] = 'Delete environment';
$string['deleteenvironment_confirm'] = 'Are you sure you want to delete the environment "{$a}"? The associated launch profile will also be removed.';
$string['environmentsaved'] = 'Environment saved successfully.';
$string['environmentdeleted'] = 'Environment deleted.';
$string['name'] = 'Name';
$string['name_help'] = 'A unique label for this environment (e.g. "develop-cp", "production").';
$string['apibaseurl'] = 'DevOps API base URL';
$string['apibaseurl_help'] = 'Base URL of the RangeOS devops-api (e.g. https://devops-api.example.com).';
$string['gqlurl'] = 'GraphQL URL';
$string['gqlurl_help'] = 'RangeOS GraphQL endpoint URL.';
$string['gqlsubscriptionsurl'] = 'GraphQL subscriptions URL';
$string['gqlsubscriptionsurl_help'] = 'WebSocket URL for GraphQL subscriptions.';
$string['keycloakurl'] = 'Keycloak URL';
$string['keycloakurl_help'] = 'Keycloak base URL included in AU launch parameters.';
$string['keycloakrealm'] = 'Keycloak realm';
$string['keycloakrealm_help'] = 'Keycloak realm name for the RangeOS environment.';
$string['keycloakclientid'] = 'Keycloak client ID';
$string['keycloakclientid_help'] = 'User-facing Keycloak client ID included in launch parameters.';
$string['keycloakscope'] = 'Keycloak scope';
$string['keycloakscope_help'] = 'OAuth2 scope for Keycloak authentication.';
$string['lightlogo'] = 'Light logo';
$string['lightlogo_help'] = 'Path or URL to the logo used on light-themed scenario slides.';
$string['darklogo'] = 'Dark logo';
$string['darklogo_help'] = 'Path or URL to the logo used on dark-themed scenario slides.';
$string['auth_token_url'] = 'Auth token URL';
$string['auth_token_url_help'] = 'Keycloak token endpoint for machine-to-machine (client_credentials) authentication to the devops-api.';
$string['auth_client_id'] = 'Auth client ID';
$string['auth_client_id_help'] = 'OAuth2 client_id for server-to-server authentication with the devops-api.';
$string['auth_client_secret'] = 'Auth client secret';
$string['auth_client_secret_help'] = 'OAuth2 client_secret for server-to-server authentication with the devops-api.';
$string['isdefault'] = 'Default environment';
$string['isdefault_help'] = 'When checked, this environment is automatically assigned to new cmi5 activities deployed via RapidCMI5.';
$string['testconnection'] = 'Test connection';
$string['testconnection_success'] = 'Connection successful. The devops-api responded with {$a} scenario classes.';
$string['testconnection_fail'] = 'Connection failed: {$a}';
$string['none'] = 'None';
$string['noenvironments'] = 'No RangeOS environments configured. Add one to get started.';

// Activity form integration.
$string['rangeos_integration'] = 'RangeOS Integration';
$string['aumappings'] = 'AU Mappings';
$string['manage_au_mappings'] = 'Manage AU-to-scenario mappings';

// AU mapping management.
$string['aumappings_global'] = 'AU Mappings (Global)';
$string['aumappings_activity'] = 'AU Mappings';
$string['au_iri'] = 'AU IRI';
$string['au_title'] = 'AU Title';
$string['type'] = 'Type';
$string['mapping_name'] = 'Mapping name';
$string['scenarios'] = 'Scenarios';
$string['scenario_class'] = 'Scenario class';
$string['status'] = 'Status';
$string['mapped'] = 'Mapped';
$string['unmapped'] = 'Unmapped';
$string['createmapping'] = 'Create mapping';
$string['editmapping'] = 'Edit mapping';
$string['deletemapping'] = 'Delete mapping';
$string['deletemapping_confirm'] = 'Are you sure you want to delete the AU mapping for "{$a}"?';
$string['mappingsaved'] = 'AU mapping saved.';
$string['mappingdeleted'] = 'AU mapping deleted.';
$string['nomappings'] = 'No AU mappings found.';
$string['selectenvironment'] = 'Select environment';
$string['searchscenarios'] = 'Search scenarios...';
$string['allclasses'] = 'All classes';
$string['filterbyclass'] = 'Filter by class';
$string['backtoactivity'] = 'Back to activity';
$string['backtomanagement'] = '← Back to RangeOS Management';
$string['noaus'] = 'No AUs found for this activity.';
$string['page'] = 'Page: ';

// Observer / notifications.
$string['unmappedaus_subject'] = 'Unmapped AUs detected after deployment';
$string['unmappedaus_body'] = 'The following AUs in activity "{$a->activityname}" (course: {$a->coursename}) have no scenario mapping in the RangeOS devops-api:{$a->aulist}Please assign scenario mappings to ensure these AUs can launch correctly.';

// Privacy.
$string['privacy:metadata'] = 'The RangeOS integration plugin does not store personal user data.';

// Errors.
$string['error:environmentnotfound'] = 'Environment not found.';
$string['error:apiconnection'] = 'Could not connect to RangeOS devops-api: {$a}';
$string['error:authentication'] = 'Authentication to RangeOS devops-api failed: {$a}';

// Navigation / dashboard.
$string['rangecontent'] = 'Range Content / Scenarios';
$string['manage_dashboard'] = 'RangeOS Management';
$string['manageclasses_desc'] = 'Create and manage scenario classes and their instances.';
$string['manageenvironments_desc'] = 'Configure RangeOS environment connections (API URLs, Keycloak, credentials).';
$string['manageaumappings_desc'] = 'Map activity units (AUs) to scenarios in the RangeOS devops-api.';
$string['library_aumappings_desc'] = 'Manage AU-to-scenario mappings across the content library.';

// Library AU mappings.
$string['library_aumappings'] = 'Library AU Mappings';
$string['selectpackage'] = 'Select package';

// Class mode (config patching).
$string['classmode'] = 'Class Mode';
$string['defaultclassid'] = 'Default Class ID';
$string['rangeos:managecontent'] = 'Manage RangeOS content and classes';

// Scenario classes management.
$string['manageclasses'] = 'Manage Classes';
$string['createclass'] = 'Create class';
$string['deleteclass'] = 'Delete';
$string['classid'] = 'Class ID';
$string['classinstances'] = 'Instances';
$string['viewinstances'] = 'View';
$string['noclasses'] = 'No scenario classes found.';
$string['scenarioname'] = 'Scenario';
$string['assignedto'] = 'Assigned To';
$string['instanceid'] = 'Instance ID';
$string['loading'] = 'Loading...';

// Add seats.
$string['addseats'] = 'Add seats';

// Default scenario mapping.
$string['usedefault'] = 'Use default';
$string['mapalldefaults'] = 'Map all defaults';
$string['mapalldefaults_desc'] = 'Create mappings for every unmapped AU that has a default scenario set in its RC5 config.';

// Activity environment assignment.
$string['activityenvironments'] = 'Activity Environments';
$string['activityenvironments_desc'] = 'View and change which RangeOS environment each cmi5 activity is assigned to, all from one place.';
$string['activity'] = 'Activity';
$string['filterbycourse'] = 'Filter by course';
$string['allcourses'] = 'All courses';
$string['noactivities'] = 'No cmi5 activities found.';

// Errors.
$string['error:confignotfound'] = 'config.json not found: {$a}';
$string['error:configinvalid'] = 'config.json contains invalid JSON: {$a}';
