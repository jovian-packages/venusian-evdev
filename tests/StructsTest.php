<?php

declare(strict_types=1);

use Jovian\Input\Evdev\AbsInfo;
use Jovian\Input\Evdev\Codes;
use Jovian\Input\Evdev\InputEvent;

it('packs and reads input_event structs, dropping a torn tail', function (): void {
    $bytes = InputEvent::pack(Codes::EV_KEY, Codes::BTN_SOUTH, 1).InputEvent::pack(Codes::EV_ABS, Codes::ABS_X, -5).InputEvent::pack(Codes::EV_SYN, Codes::SYN_REPORT, 0);

    expect(InputEvent::SIZE)->toBe(24)
        ->and(strlen($bytes))->toBe(72)
        ->and(InputEvent::many($bytes.substr($bytes, 0, 10)))->toEqual([new InputEvent(1, 0x130, 1), new InputEvent(3, 0, -5), new InputEvent(0, 0, 0)])
        ->and(InputEvent::many(''))->toBe([]);
});

it('reads struct input_absinfo', function (): void {
    expect(AbsInfo::fromBytes(pack('l6', 123, 0, 255, 0, 3, 0)))->toEqual(new AbsInfo(123, 0, 255, 0, 3, 0))
        ->and(AbsInfo::fromBytes(pack('l6', 0, -1, 1, 0, 0, 0)))->toEqual(new AbsInfo(0, -1, 1));
});
