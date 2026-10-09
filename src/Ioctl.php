<?php

namespace Jovian\Input\Evdev;

/**
 * evdev request numbers (linux/input.h): _IOR('E', nr, size). ext-posi passes a request
 * number through and computes none, so they are computed here.
 */
final class Ioctl
{
    public static function read(int $nr, int $size): int
    {
        return (2 << 30) | ($size << 16) | (0x45 << 8) | $nr;
    }

    public static function gname(int $len): int
    {
        return self::read(0x06, $len);
    }

    public static function guniq(int $len): int
    {
        return self::read(0x08, $len);
    }

    /** EVIOCGKEY: the bitmap of keys held now. */
    public static function gkey(int $len): int
    {
        return self::read(0x18, $len);
    }

    /** EVIOCGBIT(type): the bitmap of codes of $type the node reports; type 0 gives the event types. */
    public static function gbit(int $type, int $len): int
    {
        return self::read(0x20 + $type, $len);
    }

    /** EVIOCGABS(code): struct input_absinfo, 24 bytes. */
    public static function gabs(int $code): int
    {
        return self::read(0x40 + $code, 24);
    }
}
