<?php
/**
 * Temporary file to flush rewrite rules
 * 
 * Instructions:
 * 1. Access this file in your browser: http://localhost:10011/wp-content/plugins/livelang/flush-rewrite-rules.php
 * 2. You should see "Rewrite rules flushed successfully!"
 * 3. Delete this file after use
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You must be an administrator to flush rewrite rules.');
}

// Flush rewrite rules
flush_rewrite_rules(true);

echo '<h1>✅ Rewrite rules flushed successfully!</h1>';
echo '<p>Now test your language switcher on a page like <a href="' . home_url('/cart/') . '">' . home_url('/cart/') . '</a></p>';
echo '<p><strong>Important:</strong> Delete this file (flush-rewrite-rules.php) after use for security.</p>';
