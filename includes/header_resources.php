<?php
// includes/header_resources.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!defined('APP_BASE_URL')) {
    $protocol_hr = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host_hr = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script_path_for_fallback = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
    $fallback_base_dir_hr = '';
    if (!empty($script_path_for_fallback)) {
        $path_parts = explode('/', trim($script_path_for_fallback, '/'));
        if (count($path_parts) > 0) {
            // Try to construct a base path assuming the first segment is the project folder
            // This is a guess and might need adjustment based on actual deployment.
            // If SCRIPT_NAME is /pms/index.php, $path_parts[0] is 'pms'.
            // If SCRIPT_NAME is /index.php (in webroot), this logic might not create a subfolder.
            $project_folder_guess = $path_parts[0];
            if ($project_folder_guess !== 'index.php' && $project_folder_guess !== basename($script_path_for_fallback)) {
                 $fallback_base_dir_hr = '/' . $project_folder_guess;
            }
        }
    }
    define('APP_BASE_URL', rtrim($protocol_hr . $host_hr . $fallback_base_dir_hr, '/'));
    error_log("WARNING: APP_BASE_URL was not defined prior to header_resources.php. Fallback used: " . APP_BASE_URL . " (SCRIPT_NAME: " . $script_path_for_fallback . ")");
}

$app_display_name_for_header = defined('APP_NAME') ? APP_NAME : 'نظام إدارة العقارات';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; echo htmlspecialchars($app_display_name_for_header); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden; 
        }
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }
        .sidebar {
            min-width: 280px;
            max-width: 280px;
            background-color: #343a40;
            color: #fff;
            transition: margin-right 0.3s ease-in-out, transform 0.3s ease-in-out;
            min-height: 100vh;
            position: fixed; 
            top: 0;
            right: 0; 
            bottom: 0;
            z-index: 1030; 
            overflow-y: auto;
            padding-top: 0; /* Remove padding-top if header is part of sidebar */
        }
        .sidebar .nav-link {
            color: #adb5bd; padding: 0.75rem 1.25rem; display: flex;
            align-items: center; font-size: 0.95rem;
        }
        .sidebar .nav-link .bi {
            margin-left: 0.7rem; font-size: 1.2rem; width: 20px; text-align: center;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff; background-color: #495057;
        }
        .sidebar .sidebar-header {
            padding: 1.25rem; /* Adjusted padding */
            text-align: center;
            border-bottom: 1px solid #495057;
            margin-bottom: 1rem;
            background-color: #343a40; /* Same as sidebar */
            position: sticky; /* Make header sticky within sidebar */
            top: 0;
            z-index: 10; /* Above sidebar content */
        }
        .sidebar .sidebar-header h3 { color: #fff; margin: 0; font-size: 1.5rem; }
        .sidebar .sidebar-header small { font-size: 0.8rem; }


        .main-content {
            flex-grow: 1;
            padding: 20px;
            padding-top: 70px; /* Add padding-top to avoid overlap with fixed toggle button */
            transition: margin-right 0.3s ease-in-out;
            min-height: 100vh;
            margin-right: 280px; 
            width: calc(100% - 280px); 
        }
        
        .sidebar-toggle-btn {
            position: fixed;
            top: 15px;
            right: 15px; 
            z-index: 1035; /* Above sidebar when closed, below when open if sidebar is higher */
            background-color: #495057; /* Darker button */
            color: white;
            border: none;
            border-radius: 0.3rem;
            padding: 0.5rem 0.75rem; /* Slightly larger padding */
            display: none; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .sidebar-toggle-btn:hover, .sidebar-toggle-btn:focus {
            background-color: #5a6268;
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .content-header {
            background-color: #28a745; color: white; padding: 1rem 1.5rem;
            margin-bottom: 1.5rem; border-radius: 0.3rem;
        }
        .content-header h1 { margin: 0; font-size: 1.75rem; }
        .card-header h5.card-title { font-size: 1.1rem; font-weight: 600; }
        .modal-header { background-color: #28a745; color: white; }
        .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        .alert-container {
            position: fixed; top: 80px; /* Adjusted top to be below toggle button */
            left: 50%; transform: translateX(-50%); /* Centered */
            z-index: 1060; width: auto; min-width: 300px; max-width: 90%;
        }

        @media (max-width: 991.98px) { 
            .sidebar {
                margin-right: -280px; 
                /* transform: translateX(280px); Use margin for better layout flow with fixed elements */
            }
            .sidebar.active {
                margin-right: 0;
                /* transform: translateX(0); */
            }
            .main-content {
                margin-right: 0; 
                width: 100%;
            }
            .sidebar-toggle-btn {
                display: block; 
            }
        }
    </style>
</head>
<body class="bg-light">
    <button class="btn sidebar-toggle-btn" type="button" id="sidebarToggle">
        <i class="bi bi-list fs-5"></i> </button>
    <div class="wrapper"> 
        <div class="main-content" id="mainContent">
            <div class="alert-container">
                <?php
                if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
                    echo '<div class="alert alert-' . htmlspecialchars($_SESSION['message_type']) . ' alert-dismissible fade show" role="alert">';
                    echo htmlspecialchars($_SESSION['message']);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                }
                ?>
            </div>
        