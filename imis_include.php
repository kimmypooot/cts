<?php
// imis/imis_include.php

/**
 * IMIS Unified Include System
 * This file provides a unified way to include header.php, footer.php, and logout.php
 * from anywhere within the IMIS directory structure.
 * 
 * Usage:
 * include_once 'path/to/imis_include.php';
 * imis_include('header');
 * imis_include('footer');
 * imis_include('logout');
 */

// Define the base configuration
if (!defined('IMIS_BASE_PATH')) {
    // Auto-detect the IMIS root directory
    $current_dir = __DIR__;

    // Find the 'imis' directory by traversing up the directory tree
    while (!is_dir($current_dir . '/imis') && $current_dir !== dirname($current_dir)) {
        $current_dir = dirname($current_dir);
    }

    // If we found the parent directory containing 'imis', set the base path
    if (is_dir($current_dir . '/imis')) {
        define('IMIS_BASE_PATH', $current_dir . '/imis');
    } else {
        // Fallback: try to find imis in the current path
        $path_parts = explode('/', str_replace('\\', '/', __DIR__));
        $imis_index = array_search('imis', $path_parts);

        if ($imis_index !== false) {
            $imis_path = implode('/', array_slice($path_parts, 0, $imis_index + 1));
            define('IMIS_BASE_PATH', $imis_path);
        } else {
            die('Error: Could not locate IMIS base directory');
        }
    }
}

// Define the include directory path
if (!defined('IMIS_INC_PATH')) {
    define('IMIS_INC_PATH', IMIS_BASE_PATH . '/inc');
}

/**
 * Universal include function for IMIS common files
 * 
 * @param string $file The file to include ('header', 'footer', or 'logout')
 * @param bool $once Whether to use include_once (default: true)
 * @return bool True if file was included successfully, false otherwise
 */
function imis_include($file, $once = true)
{
    // Sanitize the file name
    $file = strtolower(trim($file));

    // Map of allowed files
    $allowed_files = [
        'header' => 'header.php',
        'header_js' => 'header_js.php',
        'footer' => 'footer.php',
        'footer_db' => 'footer_db.php',
        'logout' => 'logout.php'
    ];

    // Check if the requested file is allowed
    if (!array_key_exists($file, $allowed_files)) {
        error_log("IMIS Include Error: File '$file' is not allowed");
        return false;
    }

    // Build the full file path
    $file_path = IMIS_INC_PATH . '/' . $allowed_files[$file];

    // Check if file exists
    if (!file_exists($file_path)) {
        error_log("IMIS Include Error: File '$file_path' does not exist");
        return false;
    }

    // Include the file
    try {
        if ($once) {
            include_once $file_path;
        } else {
            include $file_path;
        }
        return true;
    } catch (Exception $e) {
        error_log("IMIS Include Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the IMIS base path
 * 
 * @return string The absolute path to the IMIS directory
 */
function imis_get_base_path()
{
    return IMIS_BASE_PATH;
}

/**
 * Get the IMIS include path
 * 
 * @return string The absolute path to the IMIS inc directory
 */
function imis_get_inc_path()
{
    return IMIS_INC_PATH;
}

/**
 * Helper function to get relative path from current location to IMIS inc directory
 * Useful for debugging or when you need the relative path
 * 
 * @return string Relative path to inc directory
 */
function imis_get_relative_inc_path()
{
    $current_dir = getcwd();
    $inc_path = IMIS_INC_PATH;

    // Convert to relative path
    $relative_path = str_replace($current_dir . '/', '', $inc_path);
    return $relative_path;
}

// Optional: Auto-include header if IMIS_AUTO_HEADER is defined
// if (defined('IMIS_AUTO_HEADER') && IMIS_AUTO_HEADER === true) {
//     imis_include('header');
// }

// // Optional: Register shutdown function to auto-include footer if IMIS_AUTO_FOOTER is defined
// if (defined('IMIS_AUTO_FOOTER') && IMIS_AUTO_FOOTER === true) {
//     register_shutdown_function(function () {
//         imis_include('footer');
//     });
// }
