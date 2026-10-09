<?php

declare(strict_types=1);

use Jovian\Input\Evdev\EvdevPadSource;
use Jovian\Input\Evdev\Providers\VenusianEvdevServiceProvider;
use Surface\Bridge\ToolkitManager;
use Surface\HumanInput\HumanInputManager;

it('registers the evdev pad source: HumanInput runs it with no session', function (): void {
    $toolkits = new class extends ToolkitManager {
        public function __construct() {}

        public function getDefaultDriver(): string
        {
            return 'none';
        }
    };
    $input = new HumanInputManager($toolkits, inputFrame(), 'linux', 'evdev', fn (object $mail) => null);
    VenusianEvdevServiceProvider::pads($input);
    $input->poll();

    try {
        expect($input->pads())->toBeInstanceOf(EvdevPadSource::class)
            ->and($input->engines())->toBe([])
            ->and($input->gameControllers())->toBeArray();
    } finally {
        $input->destroy();
    }
});
