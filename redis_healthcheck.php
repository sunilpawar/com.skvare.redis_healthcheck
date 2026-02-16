<?php

/**
 * CiviCRM Redis Health Check Extension
 *
 * This extension provides comprehensive Redis cache monitoring and diagnostics
 * through the CiviCRM System Status Check interface.
 */

require_once 'redis_healthcheck.civix.php';

use CRM_RedisHealthcheck_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function redis_healthcheck_civicrm_config(&$config): void {
  _redis_healthcheck_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function redis_healthcheck_civicrm_install(): void {
  _redis_healthcheck_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function redis_healthcheck_civicrm_enable(): void {
  _redis_healthcheck_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_check
 *
 * Performs comprehensive Redis health checks and returns status information
 */
function redis_healthcheck_civicrm_check(&$messages) {
  $redisHealthCheck = new CRM_RedisHealthcheck_Check($messages);
  $checks = $redisHealthCheck->performChecks();

  $messages = array_merge($messages, $checks);
}
