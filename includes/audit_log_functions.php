<?php
// includes/audit_log_functions.php

if (session_status() == PHP_SESSION_NONE) {
    // This should ideally be handled by session_manager.php being included first
    // session_start(); 
}

/**
 * Logs an action to the audit_log table.
 *
 * @param mysqli $mysqli The mysqli connection object.
 * @param string $action_type A key describing the action (e.g., CREATE_USER, UPDATE_PROPERTY, LOGIN_SUCCESS).
 * @param int|null $target_id The ID of the entity affected by the action (e.g., user_id, property_id).
 * @param string|null $target_table The name of the table where the target_id resides.
 * @param array|null $details Additional details about the action (e.g., old and new values, stored as JSON).
 * @return bool True on success, false on failure.
 */
function log_audit_action(mysqli $mysqli, string $action_type, ?int $target_id = null, ?string $target_table = null, ?array $details = null): bool {
    if (!isset($_SESSION['user_id']) && $action_type !== 'LOGIN_ATTEMPT_FAILED' && $action_type !== 'LOGIN_SUCCESS') {
        // For most actions, a user should be logged in.
        // Exceptions are login attempts themselves.
        // For anonymous actions, user_id will be NULL in the DB.
    }
    
    $user_id = $_SESSION['user_id'] ?? null; // Can be null for system actions or failed logins before session is set
    $details_json = ($details !== null) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $sql = "INSERT INTO audit_log (user_id, action_type, target_table, target_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        // target_id can be null, so use 'i' but pass null if it is.
        // user_id can be null.
        // details can be null.
        $stmt->bind_param("isssiss", 
            $user_id, 
            $action_type, 
            $target_table, 
            $target_id, 
            $details_json, 
            $ip_address, 
            $user_agent
        );
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Audit Log Execution Error: " . $stmt->error . " - SQL: " . $sql . " - Params: user_id=$user_id, action_type=$action_type, target_table=$target_table, target_id=$target_id");
            $stmt->close();
            return false;
        }
    } else {
        error_log("Audit Log Prepare Error: " . $mysqli->error . " - SQL: " . $sql);
        return false;
    }
}

// تعريف ثوابت لأنواع الإجراءات لتجنب الأخطاء الإملائية
define('AUDIT_LOGIN_SUCCESS', 'LOGIN_SUCCESS');
define('AUDIT_LOGIN_ATTEMPT_FAILED', 'LOGIN_ATTEMPT_FAILED');
define('AUDIT_LOGOUT', 'LOGOUT');

define('AUDIT_CREATE_USER', 'CREATE_USER');
define('AUDIT_EDIT_USER', 'EDIT_USER');
define('AUDIT_DELETE_USER', 'DELETE_USER');

define('AUDIT_CREATE_OWNER', 'CREATE_OWNER');
define('AUDIT_EDIT_OWNER', 'EDIT_OWNER');
define('AUDIT_DELETE_OWNER', 'DELETE_OWNER');

define('AUDIT_CREATE_PROPERTY', 'CREATE_PROPERTY');
define('AUDIT_EDIT_PROPERTY', 'EDIT_PROPERTY');
define('AUDIT_DELETE_PROPERTY', 'DELETE_PROPERTY');

define('AUDIT_CREATE_UNIT', 'CREATE_UNIT');
define('AUDIT_EDIT_UNIT', 'EDIT_UNIT');
define('AUDIT_DELETE_UNIT', 'DELETE_UNIT');

define('AUDIT_CREATE_TENANT', 'CREATE_TENANT');
define('AUDIT_EDIT_TENANT', 'EDIT_TENANT');
define('AUDIT_DELETE_TENANT', 'DELETE_TENANT');

define('AUDIT_CREATE_LEASE', 'CREATE_LEASE');
define('AUDIT_EDIT_LEASE', 'EDIT_LEASE');
define('AUDIT_DELETE_LEASE', 'DELETE_LEASE');
define('AUDIT_TERMINATE_LEASE', 'TERMINATE_LEASE'); // If you add this action

define('AUDIT_CREATE_INVOICE', 'CREATE_INVOICE');
define('AUDIT_EDIT_INVOICE', 'EDIT_INVOICE'); // Usually invoices are not edited, but credit/debit notes are issued.
define('AUDIT_DELETE_INVOICE', 'DELETE_INVOICE'); // Or VOID_INVOICE
define('AUDIT_SEND_INVOICE_ZATCA', 'SEND_INVOICE_ZATCA');

define('AUDIT_CREATE_PAYMENT', 'CREATE_PAYMENT');
define('AUDIT_EDIT_PAYMENT', 'EDIT_PAYMENT');
define('AUDIT_DELETE_PAYMENT', 'DELETE_PAYMENT');

define('AUDIT_CREATE_UTILITY_READING', 'CREATE_UTILITY_READING');
define('AUDIT_EDIT_UTILITY_READING', 'EDIT_UTILITY_READING');
define('AUDIT_DELETE_UTILITY_READING', 'DELETE_UTILITY_READING');

define('AUDIT_UPDATE_APP_SETTINGS', 'UPDATE_APP_SETTINGS');

// For CRUD on types (roles, property_types, etc.)
define('AUDIT_CREATE_ROLE', 'CREATE_ROLE');
define('AUDIT_EDIT_ROLE', 'EDIT_ROLE');
define('AUDIT_DELETE_ROLE', 'DELETE_ROLE');

define('AUDIT_CREATE_PROPERTY_TYPE', 'CREATE_PROPERTY_TYPE');
define('AUDIT_EDIT_PROPERTY_TYPE', 'EDIT_PROPERTY_TYPE');
define('AUDIT_DELETE_PROPERTY_TYPE', 'DELETE_PROPERTY_TYPE');
// ... add more for unit_types, tenant_types, lease_types, payment_methods etc.
define('AUDIT_CREATE_UNIT_TYPE', 'CREATE_UNIT_TYPE');
define('AUDIT_CREATE_TENANT_TYPE', 'CREATE_TENANT_TYPE');
define('AUDIT_CREATE_LEASE_TYPE', 'CREATE_LEASE_TYPE');
define('AUDIT_CREATE_PAYMENT_METHOD', 'CREATE_PAYMENT_METHOD');
// ... similarly for EDIT and DELETE

?>