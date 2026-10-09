<?php

declare(strict_types=1);

use Jovian\Input\Evdev\InputEvent;
use Surface\HumanInput\InputFrame;

if (! extension_loaded('posi')) {
    throw new RuntimeException('venusian-evdev tests need ext-posi loaded.');
}

/** A frame an hour long: within a test only reads end it. */
function inputFrame(): InputFrame
{
    return new InputFrame(fn (): int => 3_600_000_000_000);
}

/**
 * A FIFO as a stand-in input node: [read end (O_RDONLY | O_NONBLOCK, as a node is opened), write end].
 * Closed and unlinked by letGoOfFifos().
 *
 * @return array{int, int}
 */
function fifo(): array
{
    $path = tempnam(sys_get_temp_dir(), 'evdev');
    unlink($path);
    posix_mkfifo($path, 0600) || throw new RuntimeException("posix_mkfifo {$path} failed");
    $read = posix_open($path, O_RDONLY | O_NONBLOCK, 0);
    $write = posix_open($path, O_WRONLY | O_NONBLOCK, 0);
    ($read >= 0 && $write >= 0) || throw new RuntimeException("opening the FIFO {$path} failed: errno ".posi_errno());
    $GLOBALS['evdev_fifos'][] = [$path, $read, $write];

    return [$read, $write];
}

function letGoOfFifos(): void
{
    foreach ($GLOBALS['evdev_fifos'] ?? [] as [$path, $read, $write]) {
        posix_close($read);
        posix_close($write);
        if (file_exists($path)) {
            unlink($path);
        }
    }
    $GLOBALS['evdev_fifos'] = [];
}

/**
 * Queue events on a FIFO's write end as the kernel queues them on a node.
 *
 * @param list<array{int, int, int}> $events type, code, value
 */
function feed(int $write, array $events): void
{
    $bytes = implode('', array_map(fn (array $event): string => InputEvent::pack(...$event), $events));
    posix_write($write, $bytes, strlen($bytes)) === strlen($bytes) || throw new RuntimeException('feeding the FIFO failed: errno '.posi_errno());
}
