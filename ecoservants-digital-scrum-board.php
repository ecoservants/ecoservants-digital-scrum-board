<?php
/**
 * Plugin Name: EcoServants Digital Scrum Board
 * Description: REST API and backend for the task management system.
 * Version: 1.0.0
 * Author: EcoServants
 * Text Domain: ecoservants-scrum
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 1. Load the API Controller file you just created
require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-scrum-board-api.php';

// 2. Initialize the API when WordPress is ready
add_action( 'rest_api_init', function () {
    if ( class_exists( 'EcoServants_Scrum_Board_API' ) ) {
        $controller = new EcoServants_Scrum_Board_API();
        $controller->register_routes();
    }
} );
