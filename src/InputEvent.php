<?php

namespace Jovian\Input\Evdev;

/**
 * struct input_event: a timeval (two longs), type and code (u16), value (s32). 24 bytes on a
 * 64-bit kernel, 16 on a 32-bit one; PHP's word size follows the kernel's. The time is skipped.
 */
final readonly class InputEvent
{
    public const int SIZE = PHP_INT_SIZE === 8 ? 24 : 16;

    public function __construct(public int $type, public int $code, public int $value) {}

    /** @return list<self> every whole event in $bytes; a torn tail is dropped */
    public static function many(string $bytes): array
    {
        $format = 'x'.(self::SIZE - 8).'/vtype/vcode/lvalue';
        $events = [];
        for ($at = 0; $at + self::SIZE <= strlen($bytes); $at += self::SIZE) {
            $event = unpack($format, $bytes, $at);
            $events[] = new self($event['type'], $event['code'], $event['value']);
        }

        return $events;
    }

    /** An event as the kernel queues it, its time zero. */
    public static function pack(int $type, int $code, int $value): string
    {
        return str_repeat("\0", self::SIZE - 8).pack('vvl', $type, $code, $value);
    }
}
