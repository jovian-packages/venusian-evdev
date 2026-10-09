<?php

declare(strict_types=1);

use Jovian\Input\Evdev\AbsInfo;
use Jovian\Input\Evdev\Codes;
use Jovian\Input\Evdev\EvdevDevice;
use Jovian\Input\Evdev\EvdevPad;
use Jovian\Input\Evdev\EvdevPadSource;
use Jovian\Input\Evdev\Tests\Support\FakeProbe;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\PadSource;
use Surface\HumanInput\Devices\GameController;

const DUALSENSE_KEYS = '7fdb000000000000 0 0 0 0';
const TOUCHPAD_KEYS = '2420 10000 0 0 0 0';

/** @return array{string, string} a /dev/input and a /sys/class/input of the test's own, removed after it */
function inputWorld(): array
{
    $root = sys_get_temp_dir().'/evdev-world-'.getmypid().'-'.count($GLOBALS['evdev_worlds'] ?? []);
    mkdir("{$root}/dev", 0777, true);
    mkdir("{$root}/sys", 0777, true);
    $GLOBALS['evdev_worlds'][] = $root;

    return ["{$root}/dev", "{$root}/sys"];
}

/** A node in the test world: its /dev entry and its sysfs key bits. */
function plug(array $world, string $node, string $keys): void
{
    [$dev, $sys] = $world;
    touch("{$dev}/{$node}");
    @mkdir("{$sys}/{$node}/device/capabilities", 0777, true);
    file_put_contents("{$sys}/{$node}/device/capabilities/key", $keys."\n");
}

function unplug(array $world, string $node): void
{
    unlink("{$world[0]}/{$node}");
}

afterEach(function (): void {
    letGoOfFifos();
    foreach ($GLOBALS['evdev_worlds'] ?? [] as $root) {
        exec('rm -rf '.escapeshellarg($root));
    }
    $GLOBALS['evdev_worlds'] = [];
});

/**
 * A source over the test world. $opens maps a node to what opening it does: a device over a
 * FIFO, null (gone), or a throw. $clock is read by reference so a test moves time.
 */
function padSource(array $world, array &$opens, ?int &$clock = null): EvdevPadSource
{
    $clock ??= 0;

    return new EvdevPadSource(
        inputFrame(),
        $world[0],
        $world[1],
        function (string $path) use (&$opens): ?EvdevDevice {
            $open = $opens[basename($path)] ?? throw new RuntimeException('opened '.basename($path).', which the test never offered');

            return $open($path);
        },
        function () use (&$clock): int {
            return $clock;
        },
    );
}

/** @return array{EvdevDevice, int} a device over a FIFO, and the FIFO's write end */
function fifoDevice(string $node, FakeProbe $probe): array
{
    [$read, $write] = fifo();

    return [new EvdevDevice($node, $read, $probe), $write];
}

it('lists a pad node as a game controller named by the node, and never opens a node that is no pad', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    plug($world, 'event4', TOUCHPAD_KEYS);
    plug($world, 'event5', '0');
    [$device] = fifoDevice('event3', FakeProbe::dualSense());
    $opens = ['event3' => fn (): EvdevDevice => $device];
    $source = padSource($world, $opens)->connect();
    $pad = $source->gameControllers()['evdev-event3'] ?? null;

    expect(array_keys($source->gameControllers()))->toBe(['evdev-event3'])
        ->and($source->gamePads())->toBe([])
        ->and($pad)->toBeInstanceOf(GameController::class)
        ->and($pad->name())->toBe('Sony Interactive Entertainment DualSense Wireless Controller')
        ->and($pad->hardwareId())->toBe('4c:b9:9b:49:be:4a')
        ->and($pad->axes())->toBe([GamepadAxis::LEFT_X, GamepadAxis::LEFT_Y, GamepadAxis::RIGHT_X, GamepadAxis::RIGHT_Y, GamepadAxis::LEFT_TRIGGER, GamepadAxis::RIGHT_TRIGGER])
        ->and(array_map(fn (GamepadButton $b): bool => $pad->supports($b), GamepadButton::cases()))->not->toContain(false);
});

it('reads buttons, sticks, triggers and the hat from the stream', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    [$device, $write] = fifoDevice('event3', FakeProbe::dualSense());
    $opens = ['event3' => fn (): EvdevDevice => $device];
    $source = padSource($world, $opens)->connect();
    feed($write, [
        [Codes::EV_KEY, Codes::BTN_SOUTH, 1], [Codes::EV_KEY, Codes::BTN_MODE, 1],
        [Codes::EV_ABS, Codes::ABS_X, 255], [Codes::EV_ABS, Codes::ABS_Y, 0], [Codes::EV_ABS, Codes::ABS_RZ, 255],
        [Codes::EV_ABS, Codes::ABS_HAT0X, -1], [Codes::EV_ABS, Codes::ABS_HAT0Y, 1], [Codes::EV_SYN, Codes::SYN_REPORT, 0],
    ]);
    $source->poll();
    $pad = $source->gameControllers()['evdev-event3'];

    expect([$pad->isDown(GamepadButton::SOUTH), $pad->isPressed(GamepadButton::GUIDE), $pad->isDown(GamepadButton::DPAD_LEFT), $pad->isDown(GamepadButton::DPAD_DOWN), $pad->isDown(GamepadButton::DPAD_RIGHT)])
        ->toBe([true, true, true, true, false])
        ->and($pad->leftStick())->toBe(['x' => 1.0, 'y' => -1.0])
        ->and($pad->rightTrigger())->toBe(1.0)
        ->and($pad->leftTrigger())->toBe(0.0);
});

it('reads the state a pad is in when it is adopted', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    $probe = FakeProbe::dualSense();
    $probe->held = [Codes::BTN_EAST];
    $probe->abs[Codes::ABS_X] = new AbsInfo(255, 0, 255);
    [$device] = fifoDevice('event3', $probe);
    $opens = ['event3' => fn (): EvdevDevice => $device];
    $pad = padSource($world, $opens)->connect()->gameControllers()['evdev-event3'];

    expect($pad->isDown(GamepadButton::EAST))->toBeTrue()
        ->and($pad->leftStick()['x'])->toBe(1.0);
});

it('discards events up to the next report after SYN_DROPPED, then reads the state', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    $probe = FakeProbe::dualSense();
    [$device, $write] = fifoDevice('event3', $probe);
    $opens = ['event3' => fn (): EvdevDevice => $device];
    $source = padSource($world, $opens)->connect();
    feed($write, [
        [Codes::EV_KEY, Codes::BTN_SOUTH, 1], [Codes::EV_SYN, Codes::SYN_DROPPED, 0],
        [Codes::EV_KEY, Codes::BTN_SOUTH, 0], [Codes::EV_ABS, Codes::ABS_X, 0], [Codes::EV_SYN, Codes::SYN_REPORT, 0],
    ]);
    $probe->held = [Codes::BTN_SOUTH];
    $probe->abs[Codes::ABS_X] = new AbsInfo(255, 0, 255);
    $source->poll();
    $pad = $source->gameControllers()['evdev-event3'];

    expect($pad->isDown(GamepadButton::SOUTH))->toBeTrue()
        ->and($pad->leftStick()['x'])->toBe(1.0);
});

it('releases a pad before dropping it at an unplug', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    [$device, $write] = fifoDevice('event3', FakeProbe::dualSense());
    $opens = ['event3' => fn (): EvdevDevice => $device];
    $source = padSource($world, $opens)->connect();
    feed($write, [[Codes::EV_KEY, Codes::BTN_SOUTH, 1], [Codes::EV_ABS, Codes::ABS_X, 255], [Codes::EV_SYN, Codes::SYN_REPORT, 0]]);
    $source->poll();
    $pad = $source->gameControllers()['evdev-event3'];
    posix_close($write);
    unplug($world, 'event3');
    $source->poll();

    expect($source->gameControllers())->toBe([])
        ->and($pad->isDown(GamepadButton::SOUTH))->toBeFalse()
        ->and($pad->leftStick())->toBe(['x' => 0.0, 'y' => 0.0])
        ->and($device->isOpen())->toBeFalse();
});

it('lists nodes again every two seconds, and only then', function (): void {
    $world = inputWorld();
    $opens = [];
    $clock = 0;
    $source = padSource($world, $opens, $clock)->connect();
    plug($world, 'event7', DUALSENSE_KEYS);
    [$device] = fifoDevice('event7', FakeProbe::dualSense());
    $opens['event7'] = fn (): EvdevDevice => $device;
    $clock = 1_999_999_999;
    $source->poll();
    $early = $source->gameControllers();
    $clock = 2_000_000_000;
    $source->poll();

    expect($early)->toBe([])
        ->and(array_keys($source->gameControllers()))->toBe(['evdev-event7']);
});

it('refuses at connect naming the node and the group, closing what it opened first', function (): void {
    $world = inputWorld();
    plug($world, 'event2', DUALSENSE_KEYS);
    plug($world, 'event3', DUALSENSE_KEYS);
    [$device] = fifoDevice('event2', FakeProbe::dualSense());
    $opens = [
        'event2' => fn (): EvdevDevice => $device,
        'event3' => fn (string $path) => throw new HumanInputException("evdev: {$path} cannot be opened: permission denied."),
    ];

    expect(fn () => padSource($world, $opens)->connect())->toThrow(HumanInputException::class, "event3 cannot be opened: permission denied")
        ->and($device->isOpen())->toBeFalse();
});

it('reports a pad refused after connect on pad reads, and opens it once a listing can', function (): void {
    $world = inputWorld();
    $opens = [];
    $clock = 0;
    $source = padSource($world, $opens, $clock)->connect();
    plug($world, 'event3', DUALSENSE_KEYS);
    $opens['event3'] = fn (string $path) => throw new HumanInputException("evdev: {$path} cannot be opened: permission denied.");
    $clock += 2_000_000_000;
    $source->poll();

    expect(fn () => $source->gamePads())->toThrow(HumanInputException::class, 'event3 cannot be opened')
        ->and(fn () => $source->gameControllers())->toThrow(HumanInputException::class, 'event3 cannot be opened');

    [$device] = fifoDevice('event3', FakeProbe::dualSense());
    $opens['event3'] = fn (): EvdevDevice => $device;
    $clock += 2_000_000_000;
    $source->poll();

    expect(array_keys($source->gameControllers()))->toBe(['evdev-event3']);
});

it('skips a node gone before it opens', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    $opens = ['event3' => fn (): ?EvdevDevice => null];
    $source = padSource($world, $opens)->connect();

    expect($source->gameControllers())->toBe([])
        ->and($source->gamePads())->toBe([]);
});

it('lights the player LEDs in SDL\'s DualSense patterns where it may write them', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    $leds = "{$world[1]}/event3/device/device/leds";
    foreach (range(1, 5) as $n) {
        mkdir("{$leds}/input31:white:player-{$n}", 0777, true);
        file_put_contents("{$leds}/input31:white:player-{$n}/brightness", '0');
    }
    [$device] = fifoDevice('event3', FakeProbe::dualSense());
    $opens = ['event3' => fn (): EvdevDevice => $device];
    $pad = padSource($world, $opens)->connect()->gameControllers()['evdev-event3'];
    $lit = fn (): string => implode('', array_map(fn (int $n): string => trim(file_get_contents("{$leds}/input31:white:player-{$n}/brightness")), range(1, 5)));

    $pad->setPlayerIndex(1);
    $two = $lit();
    $pad->setPlayerIndex(2);
    $three = $lit();
    $pad->setPlayerIndex(null);

    expect([$two, $three, $lit()])->toBe(['01010', '10101', '00000']);
});

it('reads sysfs capability bits by word, most significant word first', function (): void {
    expect([
        EvdevPadSource::hasBit(DUALSENSE_KEYS, Codes::BTN_SOUTH),
        EvdevPadSource::hasBit(DUALSENSE_KEYS, Codes::BTN_NORTH),
        EvdevPadSource::hasBit(DUALSENSE_KEYS, 0x132),
        EvdevPadSource::hasBit(TOUCHPAD_KEYS, Codes::BTN_SOUTH),
        EvdevPadSource::hasBit(TOUCHPAD_KEYS, 0x110),
        EvdevPadSource::hasBit('0', Codes::BTN_SOUTH),
        EvdevPadSource::hasBit('', 0),
    ])->toBe([true, true, false, false, true, false, false]);
});

it('normalises sticks about their middle inside the flat deadzone, and triggers from their minimum', function (): void {
    $stick = new AbsInfo(0, 0, 255, 0, 10, 0);

    expect([EvdevPad::normal(GamepadAxis::LEFT_X, $stick, 130), EvdevPad::normal(GamepadAxis::LEFT_X, $stick, 255), EvdevPad::normal(GamepadAxis::LEFT_X, $stick, 0)])
        ->toBe([0.0, 1.0, -1.0])
        ->and(EvdevPad::normal(GamepadAxis::LEFT_TRIGGER, new AbsInfo(0, 0, 255), 51))->toBe(0.2)
        ->and(EvdevPad::normal(GamepadAxis::LEFT_X, new AbsInfo(0, 5, 5), 5))->toBe(0.0);
});

it('is the evdev pad source: connected once, closing its nodes at disconnect', function (): void {
    $world = inputWorld();
    plug($world, 'event3', DUALSENSE_KEYS);
    [$device] = fifoDevice('event3', FakeProbe::dualSense());
    $opens = ['event3' => fn (): EvdevDevice => $device];
    $source = padSource($world, $opens);

    expect($source)->toBeInstanceOf(PadSource::class)
        ->and($source->connect())->toBe($source)
        ->and($source->connect())->toBe($source)
        ->and($source->connected())->toBeTrue();

    $source->disconnect();

    expect($source->connected())->toBeFalse()
        ->and($source->gameControllers())->toBe([])
        ->and($device->isOpen())->toBeFalse();
});

it('opens a node it may read, refuses one it may not naming the node and the group, and skips one that is gone', function (): void {
    [$dev] = inputWorld();
    touch("{$dev}/event3");
    touch("{$dev}/event4");
    chmod("{$dev}/event4", 0);
    $opened = EvdevPadSource::opener("{$dev}/event3");

    try {
        expect($opened)->toBeInstanceOf(EvdevDevice::class)
            ->and($opened->node)->toBe('event3')
            ->and(fn () => EvdevPadSource::opener("{$dev}/event4"))->toThrow(HumanInputException::class, "evdev: {$dev}/event4 cannot be opened: permission denied. Read access comes from the 'input' group")
            ->and(EvdevPadSource::opener("{$dev}/event9"))->toBeNull();
    } finally {
        $opened->close();
    }
});

it('reads an xpad pad\'s X and Y by position: xpad sends BTN_X and BTN_Y, which are BTN_NORTH and BTN_WEST', function (): void {
    $world = inputWorld();
    plug($world, 'event4', '7cdb000000000000 0 8000000000 0 0');
    mkdir("{$world[1]}/drivers/xpad", 0777, true);
    mkdir("{$world[1]}/event4/device/device", 0777, true);
    symlink("{$world[1]}/drivers/xpad", "{$world[1]}/event4/device/device/driver");
    [$device, $write] = fifoDevice('event4', FakeProbe::dualSense());
    $opens = ['event4' => fn (): EvdevDevice => $device];
    $source = padSource($world, $opens)->connect();
    feed($write, [[Codes::EV_KEY, Codes::BTN_NORTH, 1], [Codes::EV_SYN, Codes::SYN_REPORT, 0]]);
    $source->poll();
    $pad = $source->gameControllers()['evdev-event4'];
    $x = [$pad->isDown(GamepadButton::WEST), $pad->isDown(GamepadButton::NORTH)];
    feed($write, [[Codes::EV_KEY, Codes::BTN_NORTH, 0], [Codes::EV_KEY, Codes::BTN_WEST, 1], [Codes::EV_SYN, Codes::SYN_REPORT, 0]]);
    $source->poll();

    expect($x)->toBe([true, false])
        ->and([$pad->isDown(GamepadButton::NORTH), $pad->isDown(GamepadButton::WEST)])->toBe([true, false]);
});
