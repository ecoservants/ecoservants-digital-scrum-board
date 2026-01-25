# ⚙️ Setup & Installation Guide

Welcome to the **EcoServants Digital Scrum Board**! Follow this guide to set up the development environment locally.

## Prerequisites

- **Node.js** (v18+ recommended)
- **npm** (comes with Node.js)
- **WordPress Environment** (LocalWP, XAMPP, or Docker)

## 📥 Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/ecoservants/ecoservants-digital-scrum-board.git
   cd ecoservants-digital-scrum-board
   ```

2. **Install Dependencies**
   Install the necessary Node.js packages for the React frontend and build scripts.
   ```bash
   npm install
   ```

3. **Install the Plugin in WordPress**
   - Copy the `plugin/` folder into your WordPress plugins directory:
     `wp-content/plugins/ecoservants-scrum-board/`
   - Alternatively, you can symlink it for easier development:
     ```bash
     ln -s /path/to/repo/plugin /path/to/wp/wp-content/plugins/ecoservants-scrum-board
     ```

## 🚀 Running the Project

### Development Mode
To start the build watcher for React development (hot reloading for JS assets):
```bash
npm start
```

### Production Build
To create a production-optimized build of the assets:
```bash
npm run build
```

## 🔧 Environment Variables

Currently, the plugin uses standard WordPress configuration (`wp-config.php`). Ensure your `WP_DEBUG` is set to `true` in your local environment for better error reporting.

```php
define( 'WP_DEBUG', true );
```
