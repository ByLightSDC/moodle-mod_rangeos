# local_rangeos — RangeOS Integration for Moodle

Moodle local plugin that integrates [RangeOS](https://rangeos.engineering) cyber range environments with cmi5 learning activities. Provides environment configuration, AU-to-scenario mapping, and class (prestaged scenario batch) management through the RangeOS devops-api.

## Requirements

- Moodle 4.5+ (tested on Moodle 5.0)
- [mod_cmi5](https://github.com/dropte/moodle-cpt-plugin) v2026022600+
- A RangeOS environment with devops-api and Keycloak

## Installation

Copy the `local/rangeos` directory into your Moodle installation's `local/` directory, then run the Moodle upgrade:

```bash
php admin/cli/upgrade.php --non-interactive
```

Or install via the Moodle admin UI at **Site administration > Plugins > Install plugins**.

## Features

### Environment Management

Configure one or more RangeOS environments with API endpoints, Keycloak credentials, and display settings. Each environment stores:

- DevOps API base URL
- GraphQL and WebSocket endpoints
- Keycloak realm, client, and M2M credentials
- Branding (slide logo)

Navigate to **Site administration > Plugins > Local plugins > RangeOS Integration > Manage environments**.

### AU-to-Scenario Mappings

Map cmi5 Assignable Units (AUs) to RangeOS scenarios. Supports three levels:

- **Global mappings** — apply across all activities
- **Library mappings** — per content library version, with class mode toggle
- **Activity mappings** — per individual cmi5 activity instance

The library mappings page also provides a **Class Mode toggle** that patches the AU's `config.json` in Moodle file storage to enable/disable the cmi5 player's class ID prompt — no content package rebuild required.

### Class Management

Create and manage classes — batches of pre-deployed (prestaged) scenario instances identified by a class ID string. When a student launches a class-mode AU, they're assigned one of the available prestaged scenarios instead of waiting for on-demand deployment.

- Create classes with a specified scenario, class ID, and seat count
- View instance details: status (Ready/NotReady), seat assignment, student identity
- Add seats to existing classes
- Delete individual instances
- Student usernames (Keycloak UUIDs) are resolved to Moodle display names and emails

Navigate to **Site administration > Plugins > Local plugins > RangeOS Integration > Manage Classes**.

## Capabilities

| Capability | Description | Default roles |
|---|---|---|
| `local/rangeos:manageenvironments` | Create, edit, delete environments | Manager |
| `local/rangeos:manageaumappings` | Create, edit, delete AU mappings | Manager |
| `local/rangeos:viewaumappings` | View AU mappings and class data | Manager, Editing teacher |
| `local/rangeos:managecontent` | Patch AU configs, manage classes | Manager |

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

Copyright 2026 Bylight
