<?php

/**
 * Limits how often something can be done from one network or for one account: failed logins, registrations and
 * password reset requests. The events are stored in the table rate_limit_events. Old events are removed automatically.
 *
 * Email addresses are stored as a hash. If the table does not exist or the database fails, nothing is limited and the
 * error is logged. That way a problem here can never stop visitors from logging in.
 */
class RateLimit {
  public const LOGIN_FAILED = 'login_failed';
  public const REGISTER     = 'register';
  public const RESET        = 'reset';

  /**
   * Every limit works the same way: some events are free, after that the visitor is blocked. The block starts at
   * FIRST_BLOCK seconds and doubles with every further event (1, 2, 4, 8, 16, 32 minutes), up to MAX_BLOCK.
   * A short first block hardly bothers a real visitor, but it makes a brute force bot very slow, as every guess after
   * the free ones costs more time. The maximum of an hour keeps a mistake from locking someone out for a day.
   * Blocked attempts are not recorded, so trying again during a block does not make it longer.
   */
  private const FIRST_BLOCK = 60;
  private const MAX_BLOCK   = 60 * 60;

  private const HOUR = 60 * 60;
  private const DAY  = 24 * 60 * 60;

  /**
   * Failed logins. Three limits, because an attacker can come at it from three sides. The longest wait of the three counts.
   * * One account from one network: 5 free tries is enough for a real visitor who mistypes a password. Remembered for
   *   a day, so a slow attacker does not get a fresh start every hour. A correct login clears this counter.
   * * One network: 20 free tries, because many visitors can share one address (school, office, mobile network).
   *   It catches a bot that tries many different accounts. Remembered for only an hour, so honest typos spread over
   *   a day do not add up to long blocks for everyone on that address.
   * * One account from all networks: 30 free tries. It catches an attack on one account from many addresses.
   *   Remembered for a day, as this is a targeted attack.
   */
  private const LOGIN_FREE_PER_ACCOUNT_AND_IP = 5;
  private const LOGIN_FREE_PER_IP             = 20;
  private const LOGIN_FREE_PER_ACCOUNT        = 30;

  /**
   * Registrations and password reset requests per network, remembered for an hour. 10 free tries is far more than a real
   * visitor needs, but stops a bot that fills the database or sends many emails.
   */
  private const REGISTER_FREE_PER_IP = 10;
  private const RESET_FREE_PER_IP    = 10;

  /**
   * The network of the visitor. IPv6 addresses are cut to their /64 network: one visitor has a whole network of
   * addresses, so counting single addresses would make the limits useless.
   *
   * This uses REMOTE_ADDR, which a visitor cannot fake. Headers like X-Forwarded-For can be faked, so they are not used.
   * If the website ever runs behind a proxy or CDN, REMOTE_ADDR is the proxy and all visitors share one address:
   * change this function then.
   */
  public static function clientIP(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $packed = @inet_pton($ip);
    if ($packed === false) return $ip;

    if (strlen($packed) === 16) {
      // An IPv4 address in IPv6 notation (::ffff:1.2.3.4) is an IPv4 address
      if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) return inet_ntop(substr($packed, 12));
      return bin2hex(substr($packed, 0, 8)) . '::/64';
    }

    return $ip;
  }

  public static function subjectHash(string $email): string {
    return hash('sha256', strtolower(trim($email)));
  }

  public static function record(Database $database, string $kind, ?string $subject = null): void {
    try {
      $sql = 'INSERT INTO rate_limit_events (kind, ip, subject) VALUES (:kind, :ip, :subject)';
      $database->execute($sql, [':kind' => $kind, ':ip' => self::clientIP(), ':subject' => $subject]);

      // Clean up now and then
      if (mt_rand(1, 100) === 1) {
        $database->execute('DELETE FROM rate_limit_events WHERE created_at < (NOW() - INTERVAL 1 DAY)');
      }
    } catch (Throwable $e) {
      error_log('RateLimit record failed: ' . $e->getMessage());
    }
  }

  /**
   * Forgets the failed logins of this account from this network, after a successful login.
   */
  public static function clearLoginFailures(Database $database, string $email): void {
    try {
      $sql = 'DELETE FROM rate_limit_events WHERE kind=:kind AND ip=:ip AND subject=:subject';
      $database->execute($sql, [':kind' => self::LOGIN_FAILED, ':ip' => self::clientIP(), ':subject' => self::subjectHash($email)]);
    } catch (Throwable $e) {
      error_log('RateLimit clear failed: ' . $e->getMessage());
    }
  }

  /**
   * Seconds until the next event of this kind is allowed, for a network and/or a subject. 0 if not blocked.
   * Only the events in the last $historySeconds seconds count. Fails open: on a database error nothing is blocked.
   */
  private static function waitSeconds(Database $database, string $kind, ?string $ip, ?string $subject, int $free, int $historySeconds): int {
    try {
      $sql = 'SELECT COUNT(*) AS events, TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS seconds_ago FROM rate_limit_events' .
        ' WHERE kind=:kind AND created_at > (NOW() - INTERVAL ' . (int)$historySeconds . ' SECOND)';
      $params = [':kind' => $kind];
      if ($ip !== null)      { $sql .= ' AND ip=:ip';           $params[':ip'] = $ip; }
      if ($subject !== null) { $sql .= ' AND subject=:subject'; $params[':subject'] = $subject; }

      $row = $database->fetch($sql, $params);
      $events = (int)($row['events'] ?? 0);
      if ($events < $free) return 0;

      $block = min(self::MAX_BLOCK, self::FIRST_BLOCK * (2 ** min($events - $free, 20)));
      return max(0, $block - (int)$row['seconds_ago']);
    } catch (Throwable $e) {
      error_log('RateLimit failed: ' . $e->getMessage());
      return 0;
    }
  }

  /** "30 seconds", "1 minute", "5 minutes". Minutes are rounded up. */
  public static function formatWait(int $seconds): string {
    if ($seconds < 60) return max(1, $seconds) . ($seconds === 1 ? ' second' : ' seconds');
    $minutes = max(1, (int)ceil($seconds / 60));
    return $minutes . ($minutes === 1 ? ' minute' : ' minutes');
  }

  /** Seconds the visitor has to wait before trying to log in again. 0 if not blocked. */
  public static function loginWaitSeconds(Database $database, string $email): int {
    $ip      = self::clientIP();
    $subject = self::subjectHash($email);

    return max(
      self::waitSeconds($database, self::LOGIN_FAILED, $ip,   $subject, self::LOGIN_FREE_PER_ACCOUNT_AND_IP, self::DAY),
      self::waitSeconds($database, self::LOGIN_FAILED, $ip,   null,     self::LOGIN_FREE_PER_IP,             self::HOUR),
      self::waitSeconds($database, self::LOGIN_FAILED, null,  $subject, self::LOGIN_FREE_PER_ACCOUNT,        self::DAY),
    );
  }

  public static function registerWaitSeconds(Database $database): int {
    return self::waitSeconds($database, self::REGISTER, self::clientIP(), null, self::REGISTER_FREE_PER_IP, self::HOUR);
  }

  public static function resetWaitSeconds(Database $database): int {
    return self::waitSeconds($database, self::RESET, self::clientIP(), null, self::RESET_FREE_PER_IP, self::HOUR);
  }
}
