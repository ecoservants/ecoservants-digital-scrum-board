# EcoServants Digital Scrum Board – Database Schema

All Scrum-related data is stored in dedicated tables, either in:

- The local WordPress database (default), or
- An external MySQL database configured in the plugin settings.

## Table Prefix

- Local mode:
  - `{$wpdb->prefix}es_scrum_` (e.g. `wp_es_scrum_tasks`)
- External mode:
  - Configurable prefix via plugin settings (e.g. `es_scrum_`)

## Tables

### Tasks – `{prefix}tasks`

- `id` BIGINT UNSIGNED, primary key
- `title` VARCHAR(255)
- `description` LONGTEXT
- `program_slug` VARCHAR(100)
- `sprint_id` BIGINT UNSIGNED, nullable
- `status` VARCHAR(50)
- `priority` VARCHAR(20)
- `type` VARCHAR(20)
- `reporter_id` BIGINT UNSIGNED
- `assignee_id` BIGINT UNSIGNED, nullable
- `story_points` INT, nullable
- `tags` TEXT, nullable
- `due_date` DATETIME, nullable
- `created_at` DATETIME
- `updated_at` DATETIME

### Sprints – `{prefix}sprints`

- `id` BIGINT UNSIGNED, primary key
- `name` VARCHAR(255)
- `program_slug` VARCHAR(100)
- `start_date` DATETIME, nullable
- `end_date` DATETIME, nullable
- `status` VARCHAR(50)
- `goal` TEXT, nullable
- `created_by` BIGINT UNSIGNED
- `created_at` DATETIME

### Comments – `{prefix}comments`

- `id` BIGINT UNSIGNED, primary key
- `task_id` BIGINT UNSIGNED
- `user_id` BIGINT UNSIGNED
- `body` LONGTEXT
- `created_at` DATETIME

### Activity Log – `{prefix}activity_log`

- `id` BIGINT UNSIGNED, primary key
- `task_id` BIGINT UNSIGNED
- `user_id` BIGINT UNSIGNED
- `action` VARCHAR(100)
- `from_value` TEXT, nullable
- `to_value` TEXT, nullable
- `created_at` DATETIME

These schemas are already implemented in the plugin activation routine and can be
extended by interns as needed (e.g., adding indexes, extra fields, etc.).
