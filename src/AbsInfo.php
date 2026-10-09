<?php

namespace Jovian\Input\Evdev;

/** struct input_absinfo: an axis's current value, its range, fuzz, flat (deadzone) and resolution. */
final readonly class AbsInfo
{
    public function __construct(
        public int $value,
        public int $minimum,
        public int $maximum,
        public int $fuzz = 0,
        public int $flat = 0,
        public int $resolution = 0,
    ) {}

    public static function fromBytes(string $bytes): self
    {
        $info = unpack('lvalue/lminimum/lmaximum/lfuzz/lflat/lresolution', $bytes);

        return new self($info['value'], $info['minimum'], $info['maximum'], $info['fuzz'], $info['flat'], $info['resolution']);
    }
}
