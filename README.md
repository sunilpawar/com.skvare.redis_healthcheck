# Redis Health Check Extension for CiviCRM

A comprehensive Redis cache monitoring and diagnostics extension for CiviCRM. This extension provides detailed health checks and performance metrics through the CiviCRM System Status Check interface.

## Features

### Connection & Reachability
- Redis connection status (Connected/Not Connected)
- Network reachability verification
- Response time and latency measurement
- Authentication validation
- Redis version detection

### Configuration Monitoring
- Cache backend verification
- Configuration constant validation
- Key prefix monitoring
- Cache timeout (TTL) tracking
- Password authentication status

### Memory & Performance Analysis
- Real-time memory usage tracking
- Memory fragmentation ratio monitoring
- Key capacity insights
- Hit/miss statistics
- Commands per second throughput
- Connection efficiency metrics

### Key Health Monitoring
- Total keys count
- CiviCRM-specific key count
- Write/read operation testing
- Key pattern analysis
- Cache effectiveness measurement

### Advanced Diagnostics
- Eviction policy verification
- Persistence configuration (RDB/AOF)
- Client connection management
- Slow query log analysis capability
- Memory pressure indicators

## Requirements

- CiviCRM 5.0+
- PHP Redis extension installed (`php-redis`)
- Redis server configured in CiviCRM

## Installation

1. Download the extension to your CiviCRM extensions directory:
   ```bash
   cd /path/to/civicrm/extensions
   git clone https://github.com/skvare/redis_healthcheck.git
   ```

2. Enable the extension in CiviCRM:
   - Navigate to **Administer > System Settings > Extensions**
   - Find "Redis Health Check"
   - Click "Install"

3. Verify Redis PHP extension is installed:
   ```bash
   php -m | grep redis
   ```

   If not installed:
   ```bash
   # For Ubuntu/Debian
   sudo apt-get install php-redis
   
   # For RHEL/CentOS
   sudo yum install php-pecl-redis
   
   # Or using pecl
   pecl install redis
   ```

## Configuration

The extension requires Redis to be configured in your `civicrm.settings.php`:

```php
// Enable Redis caching
define('CIVICRM_DB_CACHE_CLASS', 'Redis');
define('CIVICRM_DB_CACHE_HOST', 'localhost');
define('CIVICRM_DB_CACHE_PORT', 6379);
define('CIVICRM_DB_CACHE_PREFIX', 'civicrm_');
define('CIVICRM_DB_CACHE_TIMEOUT', 3600);

// Optional: If Redis requires password authentication
define('CIVICRM_DB_CACHE_PASSWORD', 'your-redis-password');
```

## Usage

### Viewing Redis Health Status

1. Navigate to **Administer > System Settings > System Status**
2. Scroll to the "Redis Health Check" section
3. Review the detailed diagnostics

### Understanding the Status Indicators

**Green (OK)**
- All checks passed successfully
- System is operating normally

**Yellow (Warning)**
- Minor issues detected
- Attention may be needed but not critical
- Examples: High memory usage (80-90%), High latency (>50ms)

**Red (Error)**
- Critical issues detected
- Immediate action may be required
- Examples: Connection failed, Redis unreachable, Memory critical (>90%)

### Key Metrics Explained

**Response Time**
- Time it takes Redis to respond to a PING command
- Normal: <10ms on localhost, <50ms on network
- High latency indicates network issues or Redis overload

**Memory Usage**
- Used: Current memory consumed
- Max: Maximum memory available (if configured)
- Fragmentation: Ratio of physical memory vs allocated (>1.5 indicates fragmentation)

**Cache Hit Ratio**
- Percentage of successful cache lookups
- Higher is better (>80% is good)
- Low ratio may indicate cache misconfiguration

**Evicted Keys**
- Number of keys removed when memory limit reached
- High eviction indicates insufficient memory
- Review `maxmemory-policy` configuration

**Connected Clients**
- Number of active connections to Redis
- Monitor for connection leaks
- Warning if approaching max connections

## Troubleshooting

### "Redis PHP Extension Not Installed"
Install the PHP Redis extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-redis
sudo systemctl restart php-fpm  # or apache2

# RHEL/CentOS
sudo yum install php-pecl-redis
sudo systemctl restart httpd
```

### "Failed to Connect to Redis"
- Verify Redis is running: `redis-cli ping`
- Check host and port configuration
- Verify firewall allows connections
- Ensure `CIVICRM_DB_CACHE_HOST` and `CIVICRM_DB_CACHE_PORT` are correct

### "Redis Authentication Failed"
- Verify the password in `CIVICRM_DB_CACHE_PASSWORD` is correct
- Check Redis server requires authentication: `redis-cli config get requirepass`
- Test connection: `redis-cli -h host -p port -a password ping`

### "High Memory Usage"
- Check evicted keys - indicates insufficient memory
- Review cache timeout - too long means stale keys persist
- Consider increasing Redis `maxmemory` configuration
- Clear cache: `redis-cli FLUSHDB`

### "High Memory Fragmentation"
- Fragmentation ratio >1.5 indicates inefficient memory use
- Solution: Restart Redis server (downtime required)
- Consider upgrading Redis version
- Monitor cache key sizes

### "Low Cache Hit Ratio"
- May indicate cache keys expire too quickly
- Verify `CIVICRM_DB_CACHE_TIMEOUT` is appropriate
- Check for multiple CiviCRM instances overwriting cache
- Monitor key prefix to ensure no conflicts

## API Reference

### Hook: hook_civicrm_check

The extension implements `hook_civicrm_check` to provide diagnostics:

```php
function redis_healthcheck_civicrm_check(&$messages) {
  // Extension automatically adds Redis health checks
  // No custom implementation needed
}
```

### Constants Used

```php
CIVICRM_DB_CACHE_CLASS      // Cache backend (must be 'Redis')
CIVICRM_DB_CACHE_HOST       // Redis server hostname
CIVICRM_DB_CACHE_PORT       // Redis server port (default: 6379)
CIVICRM_DB_CACHE_PASSWORD   // Redis password (optional)
CIVICRM_DB_CACHE_TIMEOUT    // Cache TTL in seconds (default: 3600)
CIVICRM_DB_CACHE_PREFIX     // Key prefix for CiviCRM keys
```

## Performance Impact

The health check extension has minimal performance impact:
- Checks run only when System Status page is accessed
- Average check execution: 100-500ms
- No background processes or scheduled jobs
- No data modification, read-only operations

## Support

For issues or feature requests:
- GitHub: https://github.com/skvare/redis_healthcheck/issues
- Email: hello@skvare.com

## License

AGPLv3 - See LICENSE file for details

## Author

Skvare - https://www.skvare.com

## Changelog

### 1.0.0 (2025-02-11)
- Initial release
- Connection and reachability checks
- Configuration validation
- Memory usage monitoring
- Key health analysis
- Performance metrics
- Eviction policy monitoring
- Persistence configuration checks
- Client connection tracking