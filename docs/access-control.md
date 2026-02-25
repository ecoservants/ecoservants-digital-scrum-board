# Access Control — EcoServants Scrum Board

## Overview

The Scrum Board enforces access control through three custom WordPress capabilities layered on top of the `es_program_groups` user meta. All permission enforcement happens server-side in REST API permission callbacks.

## Custom Capabilities

| Capability | Description |
|---|---|
| `es_scrum_view` | Read-only access to the board |
| `es_scrum_edit` | Create and modify tasks, sprints, and activity |
| `es_scrum_admin` | Unrestricted access, settings management |

Capabilities are registered by `EcoServants_Scrum_Roles::register_capabilities()` on plugin activation, and removed cleanly on deactivation.

## Role Matrix

| WordPress Role | `es_scrum_view` | `es_scrum_edit` | `es_scrum_admin` | Board Description |
|---|---|---|---|---|
| Subscriber | ✅ | ❌ | ❌ | Intern — view only, own group |
| Contributor | ✅ | ❌ | ❌ | View only |
| Author | ✅ | ✅ | ❌ | Captain — full team management |
| Editor | ✅ | ✅ | ❌ | Captain — full team management |
| Administrator | ✅ | ✅ | ✅ | Admin — all boards, all groups |

## Program Group Enforcement

Users with `es_scrum_view` but NOT `es_scrum_edit` (interns) are considered **intern-level** users. The API enforces these additional restrictions on top of capability checks:

- **Tasks** (`GET /tasks`): `program_slug` filter is auto-injected from `es_program_groups` user meta. Any `program_slug` param sent by an intern is overridden.
- **Sprints** (`GET /sprints`): Same `program_slug` injection applies.
- **User Profiles** (`GET /users/{id}/profile|tasks|activity`): Interns may only access their own profile. Attempting to view another user's profile returns `403 es_scrum_forbidden_profile`.

Captains (`es_scrum_edit`) and Admins can access tasks and sprints across all program groups.

## User Meta

Program group assignments are stored in the `es_program_groups` WordPress user meta key as an array of program slug strings.

```php
// Example: assign a user to the 'wetlands' program group
update_user_meta( $user_id, 'es_program_groups', array( 'wetlands' ) );

// Retrieve
$group = es_scrum_get_user_program_group( $user_id ); // → 'wetlands'
```

## REST Error Codes

| Error Code | Status | When |
|---|---|---|
| `rest_forbidden` | 403 | Missing `es_scrum_view` or `es_scrum_edit` capability |
| `es_scrum_forbidden_profile` | 403 | Intern trying to view another user's profile |
| `rest_cookie_invalid_nonce` | 403 | Write request with missing or invalid nonce |
| `rate_limit_exceeded` | 429 | Too many requests within the rate-limit window |

## Implementation Files

| File | Role |
|---|---|
| `includes/class-roles.php` | Capability registration, role helpers |
| `ecoservants-scrum-board.php` | Activation/deactivation hooks, global permission check |
| `includes/api/class-scrum-board-api.php` | Task API permission + intern group filter |
| `includes/api/class-sprint-api.php` | Sprint API permission + intern group filter |
| `includes/api/class-activity-log-api.php` | Activity log API permission |
| `includes/api/class-user-profile-api.php` | Profile API permission + self-only enforcement |
