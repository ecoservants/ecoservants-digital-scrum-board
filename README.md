# EcoServants Digital Scrum Board

> A WordPress plugin for agile task management, built for EcoServants interns.

This project is actively developed by interns in the **EcoServants Data Analytics and Tech Development Internship Programs**. It provides a Trello/Jira-style Scrum board integrated directly into the WordPress environment.

---

## 📚 Documentation

- **[⚙️ Setup Guide](docs/setup.md)**: Installation, dependencies, and environment setup.
- **[🧑💻 User Guide](docs/usage.md)**: How to create, move, and assign tasks.
- **[📡 API Documentation](docs/api.md)**: REST API endpoints reference.
- **[🧪 Testing](docs/testing.md)**: How to run tests and verify changes.

---

## 🛠 Tech Stack

- **Backend**: PHP (WordPress Plugin), MySQL
- **Frontend**: React.js, WordPress `@wordpress/scripts`
- **State Management**: React Hooks
- **Styling**: SCSS / CSS

## ✨ Features

- **Draggable Task Board**: Visualize work in columns (To Do, In Progress, Review, Done).
- **WordPress Integration**: Uses existing WP Users and Roles for authentication.
- **Custom Post Meta**: Stores `es_program_groups` for team segmentation.
- **REST API**: Custom endpoints for headless interaction.

## 📂 Folder Structure

```text
ecoservants-digital-scrum-board/
├── docs/                 # Documentation files
│   ├── setup.md
│   ├── usage.md
│   ├── api.md
│   └── testing.md
├── plugin/               # Main WordPress plugin code
│   ├── ecoservants-scrum-board.php
│   └── public/
├── src/                  # React source code
├── build/                # Compiled assets (generated)
└── README.md             # This file
```

## 🤝 Contributing

1. **Fork** the repository.
2. **Clone** it locally (see [Setup Guide](docs/setup.md)).
3. Create a **Feature Branch** (`git checkout -b feature/amazing-feature`).
4. **Commit** your changes.
5. **Push** to the branch.
6. Open a **Pull Request**.

## 📄 License

Proprietary and confidential. © Ecological Servants Project (EcoServants).
Licensed only for use within EcoServants and by authorized interns.
