<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
delete_option('ses_audit_log');
delete_transient('ses_public_catalog_v1');
delete_transient('ses_catalog_failure_v1');

