<?php

namespace Jovian\Input\Evdev;

/** What a node says about itself: name, uniq, capabilities, held keys and axis state (ioctl on a real node). */
interface EvdevProbe
{
    public function name(): string;

    /** The node's uniq (a Bluetooth pad's address), or null when it has none. */
    public function uniq(): ?string;

    /** @return list<int> the codes of $type the node reports */
    public function codes(int $type): array;

    /** @return list<int> the key codes held now */
    public function held(): array;

    public function absInfo(int $code): ?AbsInfo;
}
