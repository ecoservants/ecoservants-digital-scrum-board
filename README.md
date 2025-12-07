# EcoServants Digital Scrum Board

This project is actively developed by interns in the **EcoServants Data Analytics Internship Program**.

The EcoServants Digital Scrum Board is a WordPress plugin that provides a Trello/Jira-style Scrum board
integrated with EcoServants' existing WordPress user accounts and `es_program_groups` user meta.

## EcoServants Programs (Reference)

The following program groups currently exist in the EcoServants WordPress environment and are stored in each user’s `es_program_groups` meta field:

- Data Analysis
- Data Entry
- Tech Development
- Grant Writing
- Nonprofit Management
- Nonprofit Marketing
- Community Engagement
- Content Creation
- Creative Writing
- Copywriting

## Overview

- WordPress plugin located in the `plugin/` directory
- Uses custom database tables for Tasks, Sprints, Comments, Activity log
- Supports local or external MySQL database
- Includes branded admin page and JS mount point

## Structure

```text
ecoservants-digital-scrum-board/
├── plugin/                   
│   ├── ecoservants-scrum-board.php
│   └── public/
│       └── js/
│           └── es-scrum-app.js
├── docs/
│   ├── system-architecture.md
│   └── database-schema.md
└── README.md
```

## Getting Started (WordPress)

1. Clone or download this repository.
2. Copy the `plugin/` folder into your WordPress installation at:
   `wp-content/plugins/ecoservants-scrum-board/`
3. Activate "EcoServants Digital Scrum Board" under Plugins.
4. A new menu item “EcoServants Scrum” will appear.

## Getting Started (Intern Development)

Interns should:

- Fork this repository
- Create feature branches
- Build REST API endpoints
- Implement role-based permissions
- Develop the React-based board UI

The starter JS (`public/js/es-scrum-app.js`) shows how to call `/wp-json/es-scrum/v1/ping`.

## License

This software is proprietary and confidential. Unauthorized copying,
modification, distribution, or use of this software is strictly prohibited.
Licensed only for use within EcoServants and by authorized interns and contributors.

© Ecological Servants Project (EcoServants)
