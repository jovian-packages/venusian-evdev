<?php

namespace Jovian\Input\Evdev;

use Closure;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\PadSource;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\InputFrame;

/**
 * HumanInput's 'evdev' pad source: Linux game pads with no window, display or session. Nodes
 * are listed at connect() and every two seconds and classified from sysfs, readable by every
 * user, so only pad nodes are opened. A node the user may not open is a refusal naming the node
 * and the input group: thrown from connect(), or from pad reads for a pad plugged in later,
 * until a listing opens it. Ids are evdev-<node>, the hardware id the node's uniq.
 */
final class EvdevPadSource implements PadSource
{
    private const int LISTING_NS = 2_000_000_000;

    /** SDL's DualSense player LED patterns, players 1 to 5 (five LEDs, bit 0 = player-1). */
    private const array PLAYER_PATTERNS = [0x04, 0x0A, 0x15, 0x1B, 0x1F];

    /** Drivers that send X and Y by label (BTN_X, BTN_Y) rather than position, measured: xpad (Xbox Series pad, Pi 5, 2026-10-09). */
    private const array LABELLED_XY_DRIVERS = ['xpad'];

    private bool $up = false;

    /** @var array<string, EvdevPad> by node */
    private array $pads = [];

    /** @var list<HumanInputException> nodes refused at the last listing */
    private array $refused = [];

    private int $listed_at = 0;

    /** @var Closure(string): ?EvdevDevice */
    private readonly Closure $open;

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param (Closure(string): ?EvdevDevice)|null $open opens a node: a device, null when it is gone, a HumanInputException when refused; opener() by default
     * @param (Closure(): int)|null $clock monotonic nanoseconds; hrtime() by default
     */
    public function __construct(
        private readonly InputFrame $frame,
        private readonly string $dev = '/dev/input',
        private readonly string $sysfs = '/sys/class/input',
        ?Closure $open = null,
        ?Closure $clock = null,
    ) {
        $this->open = $open ?? self::opener(...);
        $this->clock = $clock ?? static fn (): int => hrtime(true);
    }

    /** @throws HumanInputException The user may not open the node. */
    public static function opener(string $path): ?EvdevDevice
    {
        $fd = posix_open($path, O_RDONLY | O_NONBLOCK, 0);
        if ($fd >= 0) {
            return new EvdevDevice(basename($path), $fd, new IoctlProbe($fd));
        }
        if (posi_errno() === EACCES) {
            throw new HumanInputException("evdev: {$path} cannot be opened: permission denied. Read access comes from the 'input' group (sudo usermod -aG input \$USER, then log in again) and the node's mode (crw-rw---- root input); HumanInput tries again at every listing.");
        }

        return null;
    }

    /** @throws HumanInputException A pad node the user may not open. */
    public function connect(): static
    {
        if ($this->up) {
            return $this;
        }
        $this->list(true);
        $this->up = true;

        return $this;
    }

    public function disconnect(): void
    {
        foreach ($this->pads as $pad) {
            $pad->release();
        }
        [$this->pads, $this->refused, $this->up] = [[], [], false];
    }

    public function connected(): bool
    {
        return $this->up;
    }

    public function settle(): void
    {
        foreach ($this->pads as $pad) {
            $pad->pad()->settle();
        }
    }

    public function poll(): void
    {
        if (! $this->up) {
            return;
        }
        if (($this->clock)() - $this->listed_at >= self::LISTING_NS) {
            $this->list(false);
        }
        foreach ($this->pads as $node => $pad) {
            if (! $pad->poll()) {
                $pad->release();
                unset($this->pads[$node]);
            }
        }
    }

    /** @throws HumanInputException A pad node refused at the last listing. */
    public function gamePads(): array
    {
        $this->refusal();

        return $this->devices(fn (GamePad $pad): bool => ! $pad instanceof GameController);
    }

    /** @throws HumanInputException A pad node refused at the last listing. */
    public function gameControllers(): array
    {
        $this->refusal();

        return $this->devices(fn (GamePad $pad): bool => $pad instanceof GameController);
    }

    /** Whether a sysfs capability bitmap (hex words, most significant first, one word per C long) has $bit. */
    public static function hasBit(string $words, int $bit): bool
    {
        $words = preg_split('/\s+/', trim($words), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $per_word = PHP_INT_SIZE * 8;
        $word = $words[count($words) - 1 - intdiv($bit, $per_word)] ?? null;
        if (is_null($word)) {
            return false;
        }
        $within = $bit % $per_word;
        $hex = str_pad($word, intdiv($per_word, 4), '0', STR_PAD_LEFT);
        $nibble = (int) hexdec($hex[strlen($hex) - 1 - intdiv($within, 4)]);

        return ($nibble & (1 << ($within % 4))) !== 0;
    }

    /** @throws HumanInputException At connect, the first refusal, after closing what was opened. */
    private function list(bool $connecting): void
    {
        $this->listed_at = ($this->clock)();
        $paths = glob($this->dev.'/event*') ?: [];
        usort($paths, 'strnatcmp');
        $refused = [];
        foreach ($paths as $path) {
            $node = basename($path);
            if (isset($this->pads[$node]) || ! $this->isPad($node)) {
                continue;
            }
            try {
                $device = ($this->open)($path);
            } catch (HumanInputException $refusal) {
                if ($connecting) {
                    $this->disconnect();
                    throw $refusal;
                }
                $refused[] = $refusal;

                continue;
            }
            if (! is_null($device)) {
                $this->pads[$node] = EvdevPad::adopt($device, $this->frame, fn (?int $index) => $this->light($node, $index), $this->labelledXY($node));
            }
        }
        $this->refused = $refused;
    }

    private function labelledXY(string $node): bool
    {
        $driver = realpath("{$this->sysfs}/{$node}/device/device/driver");

        return $driver !== false && in_array(basename($driver), self::LABELLED_XY_DRIVERS, true);
    }

    private function isPad(string $node): bool
    {
        $file = "{$this->sysfs}/{$node}/device/capabilities/key";

        return is_readable($file) && self::hasBit((string) file_get_contents($file), Codes::BTN_SOUTH);
    }

    /** @throws HumanInputException */
    private function refusal(): void
    {
        if ($this->refused !== []) {
            throw $this->refused[0];
        }
    }

    /** @return array<string, GamePad> */
    private function devices(Closure $keep): array
    {
        $devices = [];
        foreach ($this->pads as $pad) {
            if ($keep($pad->pad())) {
                $devices[$pad->pad()->id()] = $pad->pad();
            }
        }

        return $devices;
    }

    /** The node's player LEDs (hid-playstation's leds/*:player-N), where the user may write them; nothing otherwise. */
    private function light(string $node, ?int $index): void
    {
        $pattern = is_null($index) || $index < 0 ? 0 : self::PLAYER_PATTERNS[$index % count(self::PLAYER_PATTERNS)];
        foreach (glob("{$this->sysfs}/{$node}/device/device/leds/*:player-[1-5]") ?: [] as $led) {
            $brightness = "{$led}/brightness";
            if (is_writable($brightness)) {
                file_put_contents($brightness, ($pattern >> ((int) substr($led, -1) - 1)) & 1 ? '1' : '0');
            }
        }
    }
}
