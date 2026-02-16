<?php
/**
 * CRM_RedisHealthcheck_Check
 *
 * Comprehensive Redis health check implementation with consolidated display
 */
class CRM_RedisHealthcheck_Check {

  /**
   * Redis connection instance
   * @var Redis
   */
  private $redis = NULL;

  /**
   * Configuration from CiviCRM constants
   * @var array
   */
  private $config = [];

  /**
   * @var array
   */
  private $messages;

  /**
   * Constructor - Initialize configuration
   */
  public function __construct($messages) {
    $this->messages = $messages;
    $this->loadConfiguration();
  }

  /**
   * Load Redis configuration from CiviCRM constants
   */
  private function loadConfiguration() {
    $this->config = [
      'enabled' => defined('CIVICRM_DB_CACHE_CLASS') &&
        in_array(CIVICRM_DB_CACHE_CLASS, ['Redis', 'redis']),
      'class' => defined('CIVICRM_DB_CACHE_CLASS') ? CIVICRM_DB_CACHE_CLASS : 'N/A',
      'host' => defined('CIVICRM_DB_CACHE_HOST') ? CIVICRM_DB_CACHE_HOST : 'localhost',
      'port' => defined('CIVICRM_DB_CACHE_PORT') ? CIVICRM_DB_CACHE_PORT : 6379,
      'password' => defined('CIVICRM_DB_CACHE_PASSWORD') ? CIVICRM_DB_CACHE_PASSWORD : NULL,
      'timeout' => defined('CIVICRM_DB_CACHE_TIMEOUT') ? CIVICRM_DB_CACHE_TIMEOUT : 3600,
      'prefix' => defined('CIVICRM_DB_CACHE_PREFIX') ? CIVICRM_DB_CACHE_PREFIX : 'civicrm_',
    ];
  }

  /**
   * Perform all Redis health checks
   * @return array CiviCRM status check messages (single grouped message)
   */
  public function performChecks() {
    // If Redis is not enabled, return early
    if (!$this->config['enabled']) {
      return $this->createWarningMessage(
        'Redis Health Check',
        'Redis caching is not enabled in CiviCRM configuration',
        'CIVICRM_DB_CACHE_CLASS is not set to Redis'
      );
    }

    // Attempt connection
    $connectionStatus = $this->checkConnection();

    if (!$connectionStatus['connected']) {
      return $this->createErrorMessage(
        'Redis Health Check',
        'Unable to connect to Redis server',
        $connectionStatus['error']
      );
    }

    // Collect all check data
    $allCheckData = [];
    $overallStatus = 'ok';

    try {
      // Reachability
      $reachData = $this->getRedisReachability();
      $allCheckData['reachability'] = $reachData;
      if ($reachData['status'] === 'error') {
        $overallStatus = 'error';
      }
      elseif ($reachData['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
      }

      // Configuration
      $configData = $this->getConfiguration();
      $allCheckData['configuration'] = $configData;
      if ($configData['status'] === 'error') {
        $overallStatus = 'error';
      }
      elseif ($configData['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
      }

      // Memory
      $memoryData = $this->getMemoryUsage();
      $allCheckData['memory'] = $memoryData;
      if ($memoryData['status'] === 'error') {
        $overallStatus = 'error';
      }
      elseif ($memoryData['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
      }

      // Keys
      $keysData = $this->getKeyHealth();
      $allCheckData['keys'] = $keysData;
      if ($keysData['status'] === 'error') {
        $overallStatus = 'error';
      }
      elseif ($keysData['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
      }

      // Performance
      $perfData = $this->getPerformanceMetrics();
      $allCheckData['performance'] = $perfData;
      if ($perfData['status'] === 'error') {
        $overallStatus = 'error';
      }
      elseif ($perfData['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
      }

      // Eviction
      $evictionData = $this->getEvictionPolicy();
      $allCheckData['eviction'] = $evictionData;
      if ($evictionData['status'] === 'error') {
        $overallStatus = 'error';
      }
      elseif ($evictionData['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
      }

      // Persistence
      $persistenceData = $this->getPersistence();
      $allCheckData['persistence'] = $persistenceData;

      // Connections
      $connectionsData = $this->getClientConnections();
      $allCheckData['connections'] = $connectionsData;
      if ($connectionsData['status'] === 'error') {
        $overallStatus = 'error';
      }
      elseif ($connectionsData['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
      }

    }
    catch (Exception $e) {
      $this->disconnectRedis();
      return $this->createErrorMessage(
        'Redis Health Check',
        'An error occurred during health checks',
        $e->getMessage()
      );
    }

    // Close connection
    $this->disconnectRedis();

    // Create a single consolidated message with all data
    $this->createConsolidatedMessage($allCheckData, $overallStatus);
    return $this->messages;
  }

  /**
   * Check Redis connection
   * @return array Connection status and error details
   */
  private function checkConnection() {
    try {
      if (!extension_loaded('redis')) {
        return [
          'connected' => FALSE,
          'error' => 'Redis PHP extension is not installed'
        ];
      }

      $this->redis = new Redis();
      $timeout = 5;

      $connected = @$this->redis->connect(
        $this->config['host'],
        $this->config['port'],
        $timeout
      );

      if (!$connected) {
        return [
          'connected' => FALSE,
          'error' => "Failed to connect to Redis at {$this->config['host']}:{$this->config['port']}"
        ];
      }

      // Authenticate if password is set
      if (!empty($this->config['password'])) {
        $auth = @$this->redis->auth($this->config['password']);
        if (!$auth) {
          return [
            'connected' => FALSE,
            'error' => 'Redis authentication failed (invalid password)'
          ];
        }
      }

      // Verify connection with PING
      $ping = @$this->redis->ping();
      if ($ping !== TRUE && $ping !== 'PONG') {
        return [
          'connected' => FALSE,
          'error' => 'Redis PING command failed'
        ];
      }

      return [
        'connected' => TRUE,
        'error' => NULL
      ];

    }
    catch (Exception $e) {
      return [
        'connected' => FALSE,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get Redis reachability and latency
   * @return array
   */
  private function getRedisReachability() {
    try {
      $startTime = microtime(TRUE);
      $result = $this->redis->ping();
      $latency = (microtime(TRUE) - $startTime) * 1000;

      $status = ($latency < 50) ? 'ok' : 'warning';
      $statusText = ($latency < 50) ? 'Good' : 'High Latency';

      return [
        'title' => 'Connection & Latency',
        'status' => $status,
        'summary' => "Reachable - Response time: " . number_format($latency, 2) . 'ms',
        'details' => [
        'Status' => $statusText,
        'Response Time' => number_format($latency, 2) . 'ms',
        'Host' => $this->config['host'],
        'Port' => $this->config['port'],
      ]
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Connection & Latency',
        'status' => 'error',
        'summary' => 'Failed to check reachability',
        'details' => ['Error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Get Redis configuration
   * @return array
   */
  private function getConfiguration() {
    try {
      $info = $this->redis->info('server');

      return [
        'title' => 'Configuration',
        'status' => 'ok',
        'summary' => 'Configured correctly',
        'details' => [
          'Cache Backend' => $this->config['class'],
          'Redis Version' => $info['redis_version'],
          'Server Mode' => $info['redis_mode'] ?? 'standalone',
          'Key Prefix' => $this->config['prefix'],
          'Cache Timeout (TTL)' => $this->config['timeout'] . ' seconds',
          'Password Protected' => empty($this->config['password']) ? 'No' : 'Yes',
        ]
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Configuration',
        'status' => 'error',
        'summary' => 'Failed to retrieve configuration',
        'details' => ['Error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Get Redis memory usage
   * @return array
   */
  private function getMemoryUsage() {
    try {
      $info = $this->redis->info('memory');

      $usedMemory = $info['used_memory'];
      $maxMemory = $info['maxmemory'] ?? 0;

      $usedFormatted = $this->formatBytes($usedMemory);
      $maxFormatted = ($maxMemory > 0) ? $this->formatBytes($maxMemory) : 'Unlimited';

      $details = [
        'Used Memory' => $usedFormatted,
        'Max Memory' => $maxFormatted,
      ];

      $status = 'ok';
      if ($maxMemory > 0) {
        $percentage = ($usedMemory / $maxMemory) * 100;
        $details['Usage'] = number_format($percentage, 2) . '%';

        if ($percentage > 90) {
          $status = 'error';
          $summary = "CRITICAL - {$usedFormatted} / {$maxFormatted} ({$percentage}%)";
          $summary = "CRITICAL - {$usedFormatted} / {$maxFormatted} ({$percentage}%)";

        }
        elseif ($percentage > 80) {
          $status = 'warning';
          $summary = "WARNING - {$usedFormatted} / {$maxFormatted} ({$percentage}%)";
        }
        else {
          $summary = "{$usedFormatted} / {$maxFormatted} ({$percentage}%)";
        }
      }
      else {
        $summary = $usedFormatted . ' (unlimited max)';
      }

      $fragmentation = $info['mem_fragmentation_ratio'] ?? 1.0;
      $details['Fragmentation Ratio'] = number_format($fragmentation, 2);

      if ($fragmentation > 1.5) {
        $details['⚠ Warning'] = 'High fragmentation detected. Consider restarting Redis.';
        if ($status !== 'error') {
          $status = 'warning';
        }
      }

      return [
        'title' => 'Memory Usage',
        'status' => $status,
        'summary' => $summary,
        'details' => $details
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Memory Usage',
        'status' => 'error',
        'summary' => 'Failed to retrieve memory information',
        'details' => ['Error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Get Redis key health
   * @return array
   */
  private function getKeyHealth() {
    try {
      $dbInfo = $this->redis->info('keyspace');

      $db0 = $dbInfo['db0'] ?? NULL;

      if ($db0) {
        preg_match('/keys=(\d+)/', $db0, $matches);
        $totalKeys = $matches[1] ?? 0;
      }
      else {
        $totalKeys = 0;
      }

      $civiCRMKeys = $this->countKeysByPattern($this->config['prefix'] . '*');

      $details = [
        'Total Keys in Database' => $totalKeys,
        'CiviCRM Keys (prefix: ' . $this->config['prefix'] . ')' => $civiCRMKeys,
      ];

      if ($totalKeys > 0) {
        $civiPercentage = ($civiCRMKeys / $totalKeys) * 100;
        $details['CiviCRM Percentage'] = number_format($civiPercentage, 2) . '%';
      }

      // Test write/read operation
      $testKey = $this->config['prefix'] . '_healthcheck_test_' . time();
      $testValue = 'healthcheck_' . sha1(time());
      $testStatus = 'error';

      try {
        $this->redis->setEx($testKey, 10, $testValue);
        $readValue = $this->redis->get($testKey);

        if ($readValue === $testValue) {
          $details['Write/Read Test'] = '✓ PASSED';
          $testStatus = 'ok';
        }
        else {
          $details['Write/Read Test'] = '✗ FAILED (values do not match)';
        }

        $this->redis->del($testKey);

      }
      catch (Exception $e) {
        $details['Write/Read Test'] = '✗ FAILED - ' . $e->getMessage();
      }

      return [
        'title' => 'Key Health',
        'status' => $testStatus,
        'summary' => "Keys: {$totalKeys} (CiviCRM: {$civiCRMKeys})",
        'details' => $details
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Key Health',
        'status' => 'error',
        'summary' => 'Failed to retrieve key information',
        'details' => ['Error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Get Redis performance metrics
   * @return array
   */
  private function getPerformanceMetrics() {
    try {
      $stats = $this->redis->info('stats');
      $server = $this->redis->info('server');

      $totalConnections = $stats['total_connections_received'] ?? 0;
      $totalCommands = $stats['total_commands_processed'] ?? 0;
      $connectedClients = $stats['connected_clients'] ?? 0;
      $uptime = $server['uptime_in_seconds'] ?? 1;

      $commandsPerSecond = ($uptime > 0) ? ($totalCommands / $uptime) : 0;

      $details = [
        'Total Connections' => $totalConnections,
        'Connected Clients' => $connectedClients,
        'Total Commands Processed' => $totalCommands,
        'Commands/Second' => number_format($commandsPerSecond, 2),
        'Server Uptime' => $this->formatUptime($uptime),
      ];

      if (isset($stats['keyspace_hits']) && isset($stats['keyspace_misses'])) {
        $hits = $stats['keyspace_hits'];
        $misses = $stats['keyspace_misses'];
        $total = $hits + $misses;

        if ($total > 0) {
          $hitRatio = ($hits / $total) * 100;
          $details['Cache Hit Ratio'] = number_format($hitRatio, 2) . '%';
          $details['Hits / Misses'] = $hits . ' / ' . $misses;
        }
      }

      return [
        'title' => 'Performance Metrics',
        'status' => 'ok',
        'summary' => "Clients: {$connectedClients}, Throughput: " . number_format($commandsPerSecond, 2) . ' cmds/sec',
        'details' => $details
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Performance Metrics',
        'status' => 'error',
        'summary' => 'Failed to retrieve performance metrics',
        'details' => ['Error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Get eviction policy
   * @return array
   */
  private function getEvictionPolicy() {
    try {
      $config = @$this->redis->config('GET', 'maxmemory-policy');

      if (is_array($config) && isset($config['maxmemory-policy'])) {
        $policy = $config['maxmemory-policy'];
      }
      else {
        $info = $this->redis->info('memory');
        $policy = $info['maxmemory_policy'] ?? 'noeviction';
      }

      $details = [
        'Eviction Policy' => $policy,
      ];

      $stats = $this->redis->info('stats');
      $evictedKeys = $stats['evicted_keys'] ?? 0;

      $status = 'ok';
      if ($evictedKeys > 0) {
        $details['Keys Evicted'] = $evictedKeys;
        $status = 'warning';
      }

      if ($policy === 'noeviction') {
        $details['⚠ Note'] = 'Keys will NOT be evicted when memory limit is reached.';
      }

      return [
        'title' => 'Eviction Policy',
        'status' => $status,
        'summary' => "{$policy} (" . ($evictedKeys > 0 ? "{$evictedKeys} keys evicted" : 'No evictions') . ')',
        'details' => $details
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Eviction Policy',
        'status' => 'error',
        'summary' => 'Failed to retrieve eviction policy',
        'details' => ['Error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Get persistence configuration
   * @return array
   */
  private function getPersistence() {
    try {
      $info = $this->redis->info('persistence');

      $rdbStatus = $info['rdb_last_save_time'] ?? NULL;
      $aofEnabled = $info['aof_enabled'] ?? 0;

      $details = [
        'RDB (Snapshots)' => (isset($info['rdb_last_save_time']) ? 'Enabled' : 'Disabled'),
      ];

      if ($rdbStatus) {
        $lastSave = date('Y-m-d H:i:s', $rdbStatus);
        $details['Last RDB Save'] = $lastSave;
      }

      $details['AOF (Append Only File)'] = ($aofEnabled ? 'Enabled' : 'Disabled');

      if ($aofEnabled && isset($info['aof_last_rewrite_time_sec'])) {
        $details['Last AOF Rewrite'] = $info['aof_last_rewrite_time_sec'] . ' seconds ago';
      }

      $details['ℹ Note'] = 'For CiviCRM caching, persistence is optional. Enable if durability is required.';

      return [
        'title' => 'Persistence',
        'status' => 'ok',
        'summary' => ($aofEnabled || $rdbStatus) ? 'Enabled' : 'Disabled',
        'details' => $details
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Persistence',
        'status' => 'warning',
        'summary' => 'Unable to determine',
        'details' => ['Error' => 'Persistence status could not be determined: ' . $e->getMessage()]
      ];
    }
  }

  /**
   * Get client connections
   * @return array
   */
  private function getClientConnections() {
    try {
      $stats = $this->redis->info('stats');
      $server = $this->redis->info('server');

      $connectedClients = $stats['connected_clients'] ?? 0;
      $maxClients = $server['maxclients'] ?? 'Unlimited';

      $details = [
        'Connected Clients' => $connectedClients,
        'Max Clients' => $maxClients,
      ];

      $status = 'ok';
      if (is_numeric($maxClients) && $connectedClients > 0) {
        $percentage = ($connectedClients / $maxClients) * 100;
        $details['Usage'] = number_format($percentage, 2) . '%';

        if ($percentage > 80) {
          $status = 'warning';
          $details['⚠ Warning'] = 'Approaching maximum client connections.';
        }
      }

      return [
        'title' => 'Client Connections',
        'status' => $status,
        'summary' => "Connected: {$connectedClients}",
        'details' => $details
      ];

    }
    catch (Exception $e) {
      return [
        'title' => 'Client Connections',
        'status' => 'error',
        'summary' => 'Failed to retrieve client information',
        'details' => ['Error' => $e->getMessage()]
      ];
    }
  }

  /**
   * Count keys matching a pattern
   * @param string $pattern
   * @return int
   */
  private function countKeysByPattern($pattern) {
    try {
      $count = 0;
      $cursor = '0';

      do {
        $reply = $this->redis->scan($cursor, $pattern);

        if ($reply === FALSE) {
          break;
        }

        $cursor = $reply[0];
        $keys = $reply[1];
        $count += count($keys);

      } while ($cursor != '0');

      return $count;

    }
    catch (Exception $e) {
      return -1;
    }
  }

  /**
   * Format bytes to human-readable format
   * @param int $bytes
   * @return string
   */
  private function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
  }

  /**
   * Format uptime to human-readable format
   * @param int $seconds
   * @return string
   */
  private function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    return "{$days}d {$hours}h {$minutes}m";
  }

  /**
   * Create consolidated single message with all data grouped
   * @param array $allCheckData
   * @param string $overallStatus
   * @return array
   */
  private function createConsolidatedMessage($allCheckData, $overallStatus = 'ok') {
    $html = $this->buildHtmlReport($allCheckData);

    // Determine summary message
    $summary = 'Redis Health Status: ';
    $overallSummary = $this->getOverallSummary($allCheckData);

    if ($overallStatus === 'error') {
      $summary .= 'CRITICAL ⚠️';
    }
    elseif ($overallStatus === 'warning') {
      $summary .= 'WARNINGS ⚠️';
    }
    else {
      $summary .= 'HEALTHY ✓';
    }
    $summary .= ' - ' . $overallSummary;


    if (!empty($html)) {
      $message =
        new CRM_Utils_Check_Message(
          __FUNCTION__,
          $html,
          $summary,
          \Psr\Log\LogLevel::INFO,
          'fa-bug'
        );
      $this->messages[] = $message;
    }
  }

  /**
   * Build HTML report for all checks
   * @param array $allCheckData
   * @return string
   */
  private function buildHtmlReport($allCheckData) {
    $html = '<style>
      .redis-check-group { margin: 0; }
      .redis-check-section { margin: 10px 0; padding: 10px; border-left: 4px solid #ccc; background: #f9f9f9; }
      .redis-check-section.ok { border-left-color: #28a745; }
      .redis-check-section.warning { border-left-color: #ffc107; background: #fffbf0; }
      .redis-check-section.error { border-left-color: #dc3545; background: #fff5f5; }
      .redis-check-title { font-weight: bold; font-size: 1.1em; margin-bottom: 8px; }
      .redis-check-title.ok { color: #28a745; }
      .redis-check-title.warning { color: #ff9800; }
      .redis-check-title.error { color: #dc3545; }
      .redis-check-summary { font-size: 0.95em; margin-bottom: 8px; color: #555; }
      .redis-check-details { margin-left: 10px; }
      .redis-check-detail-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #eee; }
      .redis-check-detail-row:last-child { border-bottom: none; }
      .redis-check-detail-label { font-weight: 500; color: #333; min-width: 200px; }
      .redis-check-detail-value { color: #666; text-align: right; }
    </style>';

    $html .= '<div class="redis-check-group">';

    foreach ($allCheckData as $checkKey => $checkData) {
      $status = $checkData['status'] ?? 'ok';
      $title = $checkData['title'] ?? 'Check';
      $summary = $checkData['summary'] ?? '';
      $details = $checkData['details'] ?? [];

      $statusIcon = [
        'ok' => '✓',
        'warning' => '⚠',
        'error' => '✗'
      ][$status] ?? '?';

      $html .= '<div class="redis-check-section ' . htmlspecialchars($status) . '">';
      $html .= '<div class="redis-check-title ' . htmlspecialchars($status) . '">' . $statusIcon . ' ' . htmlspecialchars($title) . '</div>';

      if ($summary) {
        $html .= '<div class="redis-check-summary">' . htmlspecialchars($summary) . '</div>';
      }

      if (!empty($details)) {
        $html .= '<div class="redis-check-details">';
        foreach ($details as $label => $value) {
          $html .= '<div class="redis-check-detail-row">';
          $html .= '<div class="redis-check-detail-label">' . htmlspecialchars($label) . ':</div>';
          $html .= '<div class="redis-check-detail-value">' . htmlspecialchars($value) . '</div>';
          $html .= '</div>';
        }
        $html .= '</div>';
      }

      $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Get overall summary for all checks
   * @param array $allCheckData
   * @return string
   */
  private function getOverallSummary($allCheckData) {
    $summary = [];
    foreach ($allCheckData as $checkData) {
      if (!empty($checkData['summary'])) {
        $summary[] = $checkData['summary'];
      }
    }

    return implode(' | ', array_slice($summary, 0, 3));
  }

  /**
   * Create an error message
   * @param string $title
   * @param string $message
   * @param string $details
   * @return array
   */
  private function createErrorMessage($title, $message, $details = '') {
    $check = new CRM_Utils_Check_Message(
      'redis_healthcheck_error',
      $message,
      $details
    );
    $check->setLevel(CRM_Utils_Check::severityMap(\Psr\Log\LogLevel::ERROR));

    return [$check];
  }

  /**
   * Create a warning message
   * @param string $title
   * @param string $message
   * @param string $details
   * @return array
   */
  private function createWarningMessage($title, $message, $details = '') {
    $check = new CRM_Utils_Check_Message(
      'redis_healthcheck_warning',
      $message,
      $details
    );
    $check->setLevel(CRM_Utils_Check::severityMap(\Psr\Log\LogLevel::WARNING));

    return [$check];
  }

  /**
   * Disconnect from Redis
   */
  private function disconnectRedis() {
    if ($this->redis !== NULL) {
      try {
        @$this->redis->close();
      }
      catch (Exception $e) {
        // Silently fail
      }
    }
  }

  /**
   * Destructor
   */
  public function __destruct() {
    $this->disconnectRedis();
  }

}
