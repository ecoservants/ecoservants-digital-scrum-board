# EcoServants Digital Scrum Board – System Architecture

## Components

- **WordPress Plugin (PHP)**  
  - Located in `plugin/`
  - Registers admin menu pages
  - Creates and uses custom database tables for Scrum data
  - Provides REST API endpoints under the namespace `es-scrum/v1`
  - Integrates with WordPress users and the `es_program_groups` user meta

- **Scrum Database Layer**  
  - Custom tables:
    - Tasks
    - Sprints
    - Comments
    - Activity log
  - Configurable storage mode:
    - Local WordPress database
    - External MySQL database (via plugin settings)

- **JavaScript App (to be extended by interns)**  
  - Located in `plugin/public/js/es-scrum-app.js`
  - Mounts into the admin page container `#es-scrum-board-app`
  - Calls the REST API (`/wp-json/es-scrum/v1/ping` and future endpoints)
  - Will eventually become a full React Scrum board UI

## Data Flow

1. A logged-in admin visits the **EcoServants Scrum** admin page.
2. WordPress renders the page with:
   - EcoServants branding and logo
   - A `<div id="es-scrum-board-app"></div>` container
   - Enqueued JavaScript for the Scrum board app.
3. The JS app:
   - Mounts onto the container
   - Fetches data from `es-scrum/v1` REST endpoints
   - Displays tasks, sprints and assignments.

## Role Integration

The plugin is designed to respect the following EcoServants hierarchy:

- Board Management
- Nonprofit Management Interns
- Team Captains
- Regular users

These roles will be enforced at the REST API and UI level as the project evolves.
