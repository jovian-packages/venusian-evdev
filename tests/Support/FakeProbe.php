<?php

namespace Jovian\Input\Evdev\Tests\Support;

use Jovian\Input\Evdev\AbsInfo;
use Jovian\Input\Evdev\Codes;
use Jovian\Input\Evdev\EvdevProbe;

/** A node's capabilities and state, set by the test: what IoctlProbe reads from a real node. */
final class FakeProbe implements EvdevProbe
{
    /**
     * @param list<int> $keys key codes the node has
     * @param array<int, AbsInfo> $abs axes the node has, by code, with their current values
     * @param list<int> $held key codes held now
     */
    public function __construct(
        public string $name = 'Fake pad',
        public ?string $uniq = null,
        public array $keys = [],
        public array $abs = [],
        public array $held = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function uniq(): ?string
    {
        return $this->uniq;
    }

    public function codes(int $type): array
    {
        return match ($type) {
            Codes::EV_KEY => $this->keys,
            Codes::EV_ABS => array_keys($this->abs),
            default => [],
        };
    }

    public function held(): array
    {
        return $this->held;
    }

    public function absInfo(int $code): ?AbsInfo
    {
        return $this->abs[$code] ?? null;
    }

    /** A DualSense as the Pi's kernel reports it (measured 2026-10-09): its buttons, sticks 0..255, triggers 0..255, the hat −1..1. */
    public static function dualSense(): self
    {
        return new self(
            'Sony Interactive Entertainment DualSense Wireless Controller',
            '4c:b9:9b:49:be:4a',
            [Codes::BTN_SOUTH, Codes::BTN_EAST, Codes::BTN_NORTH, Codes::BTN_WEST, Codes::BTN_TL, Codes::BTN_TR, Codes::BTN_TL2, Codes::BTN_TR2, Codes::BTN_SELECT, Codes::BTN_START, Codes::BTN_MODE, Codes::BTN_THUMBL, Codes::BTN_THUMBR],
            [
                Codes::ABS_X => new AbsInfo(128, 0, 255), Codes::ABS_Y => new AbsInfo(128, 0, 255), Codes::ABS_Z => new AbsInfo(0, 0, 255),
                Codes::ABS_RX => new AbsInfo(128, 0, 255), Codes::ABS_RY => new AbsInfo(128, 0, 255), Codes::ABS_RZ => new AbsInfo(0, 0, 255),
                Codes::ABS_HAT0X => new AbsInfo(0, -1, 1), Codes::ABS_HAT0Y => new AbsInfo(0, -1, 1),
            ],
        );
    }
}
