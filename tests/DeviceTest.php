<?php

declare(strict_types=1);

use Jovian\Input\Evdev\Codes;
use Jovian\Input\Evdev\EvdevDevice;
use Jovian\Input\Evdev\InputEvent;
use Jovian\Input\Evdev\Tests\Support\FakeProbe;

afterEach(fn () => letGoOfFifos());

it('reads every whole event queued, and none when the queue is empty', function (): void {
    [$read, $write] = fifo();
    $device = new EvdevDevice('event3', $read, new FakeProbe());
    $empty = $device->read();
    feed($write, [[Codes::EV_KEY, Codes::BTN_SOUTH, 1], [Codes::EV_SYN, Codes::SYN_REPORT, 0]]);

    expect($empty)->toBe([])
        ->and($device->read())->toEqual([new InputEvent(1, 0x130, 1), new InputEvent(0, 0, 0)])
        ->and($device->read())->toBe([])
        ->and($device->isOpen())->toBeTrue();
});

it('reads a queue longer than one batch whole', function (): void {
    [$read, $write] = fifo();
    $device = new EvdevDevice('event3', $read, new FakeProbe());
    feed($write, array_map(fn (int $i): array => [Codes::EV_ABS, Codes::ABS_X, $i], range(0, 199)));
    $events = $device->read();

    expect($events)->toHaveCount(200)
        ->and($events[199])->toEqual(new InputEvent(Codes::EV_ABS, Codes::ABS_X, 199));
});

it('closes, and reads null from then on, once the node is gone', function (): void {
    [$read, $write] = fifo();
    $device = new EvdevDevice('event3', $read, new FakeProbe());
    posix_close($write);

    expect($device->read())->toBeNull()
        ->and($device->isOpen())->toBeFalse()
        ->and($device->read())->toBeNull();
});

it('closes its node when it is dropped without close()', function (): void {
    [$read] = fifo();
    $device = new EvdevDevice('event3', $read, new FakeProbe());
    unset($device);

    expect(posix_read($read, InputEvent::SIZE))->toBeFalse()
        ->and(posi_errno())->toBe(EBADF);
});
