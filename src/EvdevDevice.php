<?php

namespace Jovian\Input\Evdev;

/**
 * One open input node. read() drains what the kernel queued, never waiting (the node is open
 * O_NONBLOCK). An empty queue reads as no events; the node gone (ENODEV), end of file, or any
 * other read failure closes it and reads as null: the source reports the pad disconnected, and
 * its next listing opens the node again if it is still there.
 */
final class EvdevDevice
{
    private const int BATCH = 64;

    public function __construct(
        public readonly string $node,
        private int $fd,
        public readonly EvdevProbe $probe,
    ) {}

    /** @return list<InputEvent>|null every whole event queued now, or null once the node is gone */
    public function read(): ?array
    {
        if ($this->fd < 0) {
            return null;
        }

        $chunk = InputEvent::SIZE * self::BATCH;
        $bytes = '';
        while (true) {
            $read = posix_read($this->fd, $chunk);
            if ($read === false && posi_errno() === EAGAIN) {
                break;
            }
            if ($read === false || $read === '') {
                $this->close();

                return null;
            }
            $bytes .= $read;
            if (strlen($read) < $chunk) {
                break;
            }
        }

        return InputEvent::many($bytes);
    }

    public function isOpen(): bool
    {
        return $this->fd >= 0;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->fd >= 0) {
            posix_close($this->fd);
            $this->fd = -1;
        }
    }
}
