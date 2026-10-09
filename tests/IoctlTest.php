<?php

declare(strict_types=1);

use Jovian\Input\Evdev\Codes;
use Jovian\Input\Evdev\Ioctl;

it('computes evdev request numbers as linux/input.h does', function (): void {
    expect([Ioctl::gname(256), Ioctl::guniq(256), Ioctl::gkey(96), Ioctl::gbit(Codes::EV_KEY, 96), Ioctl::gbit(Codes::EV_ABS, 8), Ioctl::gabs(Codes::ABS_X), Ioctl::gabs(Codes::ABS_HAT0Y)])
        ->toBe([0x81004506, 0x81004508, 0x80604518, 0x80604521, 0x80084523, 0x80184540, 0x80184551]);
});
