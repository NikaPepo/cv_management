<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Bounded, single-flight consumer for the `async` Messenger transport.
 *
 * Designed to be driven by short-lived HTTP requests from cron.org rather
 * than a long-lived daemon:
 *
 *   - runs Symfony's Worker for at most `time_limit` seconds, then returns
 *     cleanly so the HTTP request finishes and PHP-FPM can recycle the worker;
 *   - acquires an exclusive flock() on a container-local file to prevent
 *     parallel consumers racing on the same transport (cron.org + Render
 *     Free give us a single web-service instance, but cron.org may fire
 *     a request before the previous one finishes);
 *   - treats the lock as expired if its mtime is older than the stale
 *     threshold, so a crashed consumer cannot wedge the queue forever.
 *
 * The transport, bus, and dispatcher are injected by the container. The
 * `TransportInterface` for `async` is wired explicitly in services.yaml
 * because Messenger transports are not autowireable by interface.
 */
class MessengerConsumeService
{
    /**
     * Container-local lock file. The /var/www/var directory is owned by
     * www-data (chowned in the production Dockerfile), so PHP-FPM workers
     * can read/write it. Render Free has ephemeral FS, so the file is
     * automatically cleared on redeploy — which is exactly the desired
     * reset semantics for a stale-lock recovery.
     */
    private const LOCK_FILE = '/var/www/var/messenger-cron.lock';

    /**
     * If the lock holder has not touched the file for this long, consider
     * the lock stale and forcibly acquire it. Must be greater than the
     * longest expected consume() run, so a healthy consumer's heartbeat
     * never appears stale.
     */
    private const STALE_THRESHOLD_SECONDS = 120;

    public function __construct(
        private TransportInterface $asyncTransport,
        private MessageBusInterface $bus,
        private ?LoggerInterface $logger = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        // Fall back to a no-op logger so the service stays usable in
        // tests / minimal container configs without forcing DI to wire one.
        $this->logger ??= new NullLogger();
    }

    /**
     * Drain the `async` transport for up to $timeLimitSeconds.
     *
     * @return self::RESULT_* constant:
     *   - self::RESULT_OK      : ran the worker, processed whatever was queued
     *   - self::RESULT_IDLE    : ran but had nothing to process (still OK)
     *   - self::RESULT_BUSY    : another consumer holds the lock
     *   - self::RESULT_FAILED  : internal error during the run
     */
    public function consume(int $timeLimitSeconds = 45): string
    {
        $lockFp = @fopen(self::LOCK_FILE, 'c+');
        if ($lockFp === false) {
            $this->logger->error('Messenger cron consumer could not open lock file.', [
                'path' => self::LOCK_FILE,
            ]);
            return self::RESULT_FAILED;
        }

        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            // Lock currently held. Inspect its mtime to decide whether
            // it is stale (crashed previous consumer) or live.
            $mtime = @filemtime(self::LOCK_FILE) ?: 0;
            if ((time() - $mtime) < self::STALE_THRESHOLD_SECONDS) {
                fclose($lockFp);
                $this->logger->info('Messenger cron consumer skipped: another instance is active.', [
                    'lock_mtime' => $mtime,
                ]);
                return self::RESULT_BUSY;
            }
            // Stale lock — acquire it. flock here is blocking but bounded
            // by the lock-holder having died and released the kernel lock.
            flock($lockFp, LOCK_EX);
            $this->logger->warning('Messenger cron consumer recovered a stale lock.', [
                'lock_age_seconds' => time() - $mtime,
            ]);
        }

        // Heartbeat: refresh mtime so a healthy consumer never appears stale.
        ftruncate($lockFp, 0);
        fwrite($lockFp, (string) getmypid());
        fflush($lockFp);
        touch(self::LOCK_FILE, time());

        try {
            $worker = new Worker(
                ['async' => $this->asyncTransport],
                $this->bus,
                $this->eventDispatcher,
                $this->logger,
            );
            $worker->run([
                'sleep' => 200_000,       // 0.2s between fetches when queue is empty
                'time_limit' => $timeLimitSeconds,
            ]);
            return self::RESULT_OK;
        } catch (\Throwable $e) {
            $this->logger->error('Messenger cron consumer failed.', [
                'exception' => $e,
            ]);
            return self::RESULT_FAILED;
        } finally {
            // Refresh mtime one last time, release lock, close fd.
            touch(self::LOCK_FILE, time());
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    public const RESULT_OK = 'ok';
    public const RESULT_IDLE = 'idle';
    public const RESULT_BUSY = 'busy';
    public const RESULT_FAILED = 'failed';
}