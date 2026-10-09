<?php

namespace Jovian\Input\Evdev;

/** EvdevProbe over ext-posi's ioctl on an open node. A failed read answers empty. */
final class IoctlProbe implements EvdevProbe
{
    /** KEY_MAX 0x2ff: 768 bits. */
    private const int KEY_BYTES = 96;

    /** ABS_MAX 0x3f, and EV_MAX 0x1f: 64 bits cover both. */
    private const int SMALL_BYTES = 8;

    public function __construct(private readonly int $fd) {}

    public function name(): string
    {
        return self::cString($this->fetch(Ioctl::gname(256), 256)) ?? '';
    }

    public function uniq(): ?string
    {
        $uniq = self::cString($this->fetch(Ioctl::guniq(256), 256));

        return $uniq === '' ? null : $uniq;
    }

    public function codes(int $type): array
    {
        $length = $type === Codes::EV_KEY ? self::KEY_BYTES : self::SMALL_BYTES;

        return self::bits($this->fetch(Ioctl::gbit($type, $length), $length));
    }

    public function held(): array
    {
        return self::bits($this->fetch(Ioctl::gkey(self::KEY_BYTES), self::KEY_BYTES));
    }

    public function absInfo(int $code): ?AbsInfo
    {
        $bytes = $this->fetch(Ioctl::gabs($code), 24);

        return is_null($bytes) ? null : AbsInfo::fromBytes($bytes);
    }

    /** @return list<int> the positions of the set bits, least significant bit of the first byte first */
    public static function bits(?string $bytes): array
    {
        $bits = [];
        for ($i = 0; $i < strlen($bytes ?? ''); $i++) {
            $byte = ord($bytes[$i]);
            for ($bit = 0; $byte !== 0 && $bit < 8; $bit++) {
                if (($byte & (1 << $bit)) !== 0) {
                    $bits[] = $i * 8 + $bit;
                }
            }
        }

        return $bits;
    }

    private function fetch(int $request, int $length): ?string
    {
        $value = null;
        $result = ioctl($this->fd, $request, str_repeat("\0", $length), $value);

        return $result < 0 || ! is_string($value) ? null : $value;
    }

    private static function cString(?string $bytes): ?string
    {
        return is_null($bytes) ? null : strstr($bytes."\0", "\0", true);
    }
}
