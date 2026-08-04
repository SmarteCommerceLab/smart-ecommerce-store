<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Audit {
	const OPTION = 'ses_audit_log';
	const LIMIT = 50;

	public static function write($event, array $context = array()) {
		$log = get_option(self::OPTION, array());
		$log = is_array($log) ? $log : array();
		array_unshift($log, array(
			'time' => gmdate('c'),
			'event' => sanitize_key($event),
			'user_id' => get_current_user_id(),
			'context' => self::sanitize($context),
		));
		update_option(self::OPTION, array_slice($log, 0, self::LIMIT), false);
	}

	private static function sanitize(array $context) {
		$clean = array();
		foreach ($context as $key => $value) {
			if (is_scalar($value) || null === $value) {
				$clean[sanitize_key($key)] = sanitize_text_field((string) $value);
			}
		}
		return $clean;
	}
}

