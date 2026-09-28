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
$string['manageenvironments'] = 'Manage Environments';
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
$string['searchenvironments'] = 'Search environments';
$string['searchenvironments_placeholder'] = 'Environment or API URL...';
$string['environments_count'] = 'Showing {$a} environments.';
$string['noenvironmentsmatch'] = 'No matching environments';
$string['noenvironmentsmatch_desc'] = 'No environment matches the current search.';
$string['noenvironments'] = 'No environments yet';
$string['noenvironments_desc'] = 'Add a RangeOS environment to connect this site to a range. Every page here needs one before it can show anything.';
$string['noprofile'] = 'No launch profile';
$string['deleteenvironment_confirmtitle'] = 'Delete environment?';

// Activity form integration.
$string['rangeos_integration'] = 'RangeOS Integration';
$string['aumappings'] = 'AU Mappings';
$string['manage_au_mappings'] = 'Manage AU-to-scenario mappings';

// AU mapping management.
$string['aumappings_activity'] = 'AU Mappings';
$string['au_iri'] = 'AU Id (IRI)';
$string['au_title'] = 'AU Title';
$string['type'] = 'Type';
$string['scenarios'] = 'Mapped Scenario';
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
$string['selectenvironment'] = 'Select environment';
$string['searchscenarios'] = 'Search scenarios...';
$string['allclasses'] = 'All classes';
$string['filterbyclass'] = 'Filter by class';
$string['backtoactivity'] = 'Back to activity';
$string['backtomanagement'] = '← Back to RangeOS Management';
$string['aumapping_count'] = 'Showing {$a} activity units.';
$string['noaus'] = 'No activity units to map';
$string['noaus_desc'] = 'This cmi5 activity has no activity units, so there is nothing to map to a scenario.';
$string['page'] = 'Page: ';
$string['cmi5package'] = 'cmi5 package';
$string['defaultscenario_missing'] = 'Not in this environment';
$string['usedefault_unavailable'] = 'Use default is unavailable: "{$a}" is not in this environment.';

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
$string['searchprojects'] = 'Search RapidCMI5 Projects';
$string['searchprojects_placeholder'] = 'Start typing a project name...';
$string['searchprojects_invalid'] = 'Select a project from the search suggestions, or clear the field to show all projects.';

// Mapping dialog.
$string['mappingname'] = 'Name';
$string['auidlabel'] = 'AU ID';
$string['defaultscenario_fromconfig'] = 'Default scenario from course config:';
$string['defaultscenario_notfound'] = 'The default scenario "{$a}" was not found in this environment.';
$string['searchscenarios_label'] = 'Search scenarios';
$string['searchscenarios_placeholder'] = 'Search scenarios by name...';
$string['searching'] = 'Searching...';
$string['noscenariosfound'] = 'No scenarios found.';
$string['scenariosearchfailed'] = 'Could not search scenarios.';
$string['noscenariosselected'] = 'No scenarios selected.';
$string['selectascenario'] = 'Select at least one scenario.';
$string['removescenario'] = 'Remove {$a}';
$string['scenarioauthor'] = 'Author: {$a}';
$string['scenariocreated'] = 'Created: {$a}';
$string['scenarioupdated'] = 'Updated: {$a}';
$string['scenariopage'] = 'Page {$a->page} of {$a->pages} · {$a->total} scenarios';
$string['classmodeupdated'] = 'Class mode updated.';

// Class mode (config patching).
$string['classmode'] = 'Class Mode';
$string['defaultclassid'] = 'Default Class ID';
$string['rangeos:managecontent'] = 'Manage RangeOS content and classes';

// Scenario classes management.
$string['manageclasses'] = 'Manage Classes';
$string['createclass'] = 'Create Class';
$string['deleteclass'] = 'Delete';
$string['classid'] = 'Class Id';
$string['classinstances'] = 'Seats';
$string['viewinstances'] = 'View';
$string['noclasses'] = 'No scenario classes yet';
$string['noclasses_desc'] = 'Create a class to prestage scenario seats, then assign them to learners.';
$string['scenarioname'] = 'Scenario';
$string['assignedto'] = 'Assigned To';
$string['instanceid'] = 'Instance ID';
$string['loading'] = 'Loading...';
$string['seat'] = 'Seat';
$string['seatnumber'] = 'Seat {$a}';
$string['seatssummary'] = '{$a->assigned} of {$a->total} assigned';
$string['seatsloadfailed'] = 'Could not load';
$string['classtitle'] = 'Class: {$a}';
$string['noinstances'] = 'No seats found for this class.';
$string['unassignedseat'] = 'Unassigned';
$string['unknownuser'] = 'Unknown user';
$string['removeseat'] = 'Remove this seat';
$string['deleteseat'] = 'Delete seat';
$string['deleteseat_confirmtitle'] = 'Delete this seat?';
$string['deleteseat_confirm'] = 'The seat and its prestaged scenario instance are removed permanently. This cannot be undone.';
$string['seatdeleted'] = 'Seat deleted.';
$string['deleteclass_confirmtitle'] = 'Delete class?';
$string['deleteclass_confirm'] = 'Deleting "{$a}" ends every prestaged scenario in the class. This cannot be undone.';
$string['deleteclass_notimplemented'] = 'Deleting a class is not yet supported here. Use the devops-api directly.';

// Create class dialog.
$string['classid_placeholder'] = 'e.g. cyber-101-spring-2026';
$string['activityscenario'] = 'Activity / scenario';
$string['loadingactivities'] = 'Loading local activities...';
$string['selectactivityscenario'] = 'Select an activity scenario';
$string['noactivityscenarios'] = 'No activities with scenario mappings found';
$string['activitiesloadfailed'] = 'Could not load activities';
$string['numberofseats'] = 'Number of seats';
$string['enddate'] = 'End date';
$string['enddate_default'] = 'Defaults to six months from today when left empty.';
$string['creating'] = 'Creating...';
$string['createclass_required'] = 'Class ID, activity scenario and seat count are all required.';
$string['classqueued'] = 'Class "{$a->classid}" is queued for deployment with {$a->count} seats. Deployment can take several minutes; refresh to check progress.';
$string['scenariouuid'] = 'UUID: {$a}';

// Add seats.
$string['addseats'] = 'Add Seats';
$string['addseats_title'] = 'Add seats: {$a}';
$string['additionalseats'] = 'Additional seats';
$string['adding'] = 'Adding...';
$string['addseats_noscenario'] = 'The scenario for this class could not be determined. View its seats first, then try again.';
$string['addseats_countminimum'] = 'Enter a seat count of 1 or more.';
$string['seatsqueued'] = '{$a->count} seats queued for "{$a->classid}". Deployment can take several minutes; refresh to check progress.';

// Default scenario mapping.
$string['usedefault'] = 'Use default';
$string['mapalldefaults'] = 'Map all defaults';
$string['defaultscenario'] = 'Default scenario';
$string['mapalldefaults_desc'] = 'Create mappings for every unmapped AU that has a default scenario set in its RC5 config.';
$string['mapalldefaults_confirmtitle'] = 'Map all default scenarios?';
$string['mapalldefaults_confirm'] = 'This creates a mapping for every unmapped AU that has a default scenario in its RC5 config. AUs that are already mapped are left unchanged.';
$string['mapalldefaults_results'] = 'Map all defaults: results';
$string['mapalldefaults_created'] = 'Created ({$a})';
$string['mapalldefaults_failed'] = 'Failed ({$a})';
$string['mapalldefaults_nonecreated'] = 'No new mappings were created.';
$string['mapalldefaults_skipped'] = '{$a} AUs skipped, already mapped.';
$string['mapalldefaults_reason'] = 'Reason';
$string['courseidlabel'] = 'Course ID: {$a}';
$string['nolocalcourse'] = 'No local course';

// Activity environment assignment.
$string['activityenvironments'] = 'Activity Environments';
$string['activityenvironments_desc'] = 'View and change which RangeOS environment each cmi5 activity is assigned to, all from one place.';
$string['activity'] = 'Activity';
$string['noactivities'] = 'No cmi5 activities';
$string['noactivities_desc'] = 'Deploy a cmi5 activity before assigning environments to one.';
$string['noactivitiesmatch'] = 'No matching activities';
$string['noactivitiesmatch_desc'] = 'No activity matches the current search and environment filter.';
$string['searchactivities'] = 'Search activities';
$string['searchactivities_placeholder'] = 'Activity or course name...';
$string['filterbyenvironment'] = 'Environment';
$string['allenvironments'] = 'All environments';
$string['unassignedenvironment'] = 'Not assigned';
$string['clearfilters'] = 'Clear filters';
$string['activityenvironments_count'] = 'Showing {$a->first}–{$a->last} of {$a->total} activities.';
$string['environmentassigned'] = 'Saved';
$string['environmentassignfailed'] = 'Not saved';

// Errors.
$string['error:confignotfound'] = 'config.json not found: {$a}';
$string['error:configinvalid'] = 'config.json contains invalid JSON: {$a}';

// Shared management presentation.
$string['dashboardoverview'] = 'Overview';
$string['dashboardnavigation'] = 'RangeOS management navigation';
$string['dashboarddescription'] = 'Manage your range environments, scenario classes, and learning activity mappings.';
$string['dashboardexplore'] = 'Open management page';

// Library mapping scope.
$string['librarymapping_scope'] = 'Showing AUs from the latest versions of local library packages.';
$string['nolibraryaus'] = 'No activity units found';
$string['nolibraryaus_desc'] = 'No library package in this view contains an activity unit. Clear the project filter to look across the whole library.';
$string['librarymapping_count'] = 'Showing {$a->first}–{$a->last} of {$a->total} library AUs.';
