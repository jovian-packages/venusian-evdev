<?php

declare(strict_types=1);

use Jovian\Input\Evdev\Codes;
use Jovian\Input\Evdev\IoctlProbe;

it('reads the set bits of a kernel bitmap as codes', function (): void {
    expect(IoctlProbe::bits("\x01\x80\x00\x02"))->toBe([0, 15, 25])
        ->and(IoctlProbe::bits(str_repeat("\0", 8)))->toBe([])
        ->and(IoctlProbe::bits(null))->toBe([]);
});

it('reads a real input node through ioctl', function (): void {
    $nodes = array_values(array_filter(glob('/dev/input/event*') ?: [], 'is_readable'));
    if ($nodes === []) {
        test()->markTestSkipped('no readable /dev/input/event* node here (Linux with the user in input reads them)');
    }
    $fd = posix_open($nodes[0], O_RDONLY | O_NONBLOCK, 0);
    $probe = new IoctlProbe($fd);

    try {
        expect($probe->name())->not->toBe('')
            ->and($probe->codes(Codes::EV_SYN))->toContain(Codes::EV_SYN)
            ->and($probe->held())->toBeArray();
    } finally {
        posix_close($fd);
    }
});
