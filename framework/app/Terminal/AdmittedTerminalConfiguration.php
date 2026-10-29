<?php

namespace App\Terminal;

use App\FreshWeb\FreshWebConfiguration;
use App\LegacyDatabase\AttendanceWriter;
use App\LegacyDatabase\RuntimeAdmission;
use App\LegacyDatabase\RuntimeDatabase;
use Illuminate\Foundation\Application;
use JsonSerializable;
use LogicException;

/** Pure existing-root composition, not database/key provisioning or activation. */
final readonly class AdmittedTerminalConfiguration implements JsonSerializable
{
    private AdmittedTerminalKeys $keys;

    private function __construct(
        Application $app,
        private array $settings,
        private array $roots,
        private RuntimeDatabase $database,
        private array $writerBinding,
        private array $protocolBinding,
        private ExistingTerminalFileRoot $device,
        private ExistingTerminalFileRoot $card,
    ) {
        // Every use rechecks composition, even through a previously resolved backend.
        $this->keys = new AdmittedTerminalKeys(
            function () use ($app): string { $this->assertCurrent($app); return $this->device->secret(); },
            function () use ($app): string { $this->assertCurrent($app); return $this->card->secret(); },
        );
    }

    public static function fromApplication(Application $app): ?self
    {
        $config = $app->make('config');
        $enabled = $config->get('chrono_terminal.existing_keys_enabled', false);
        if (! is_bool($enabled)) { throw new LogicException('Terminal root composition requires an explicit boolean opt-in.'); }
        if (! $enabled) { return null; }
        if ($app->configurationIsCached() || $app->routesAreCached()) {
            throw new LogicException('Terminal root composition requires uncached configuration and routes.');
        }
        foreach ([self::class, AdmittedTerminalKeys::class, ExistingTerminalFileRoot::class, ExistingTerminalRootAdmission::class] as $service) {
            if ($app->bound($service) || $app->resolved($service)) {
                throw new LogicException('Terminal root composition must not replace another admitted source.');
            }
        }
        if ($app->resolved(TerminalProtocol::class) || $app->resolved(AttendanceWriter::class)
            || $app->isShared(AttendanceWriter::class)
            || ! $app->bound(TerminalProtocol::class) || ! $app->bound(RuntimeDatabase::class) || ! $app->bound(AttendanceWriter::class)) {
            throw new LogicException('An unresolved terminal backend and existing runtime writer are required.');
        }
        $terminal = $config->get('chrono_terminal');
        $manifest = $config->get('chrono_runtime.admission');
        if (! is_array($terminal) || count($terminal) !== 3
            || array_diff(['http_enabled', 'existing_keys_enabled', 'existing_key_admissions'], array_keys($terminal)) !== []
            || ! is_array($terminal['existing_key_admissions']) || count($terminal['existing_key_admissions']) !== 2
            || array_diff(['device', 'card'], array_keys($terminal['existing_key_admissions'])) !== []
            || ! is_array($terminal['existing_key_admissions']['device']) || ! is_array($terminal['existing_key_admissions']['card'])
            || ! is_array($manifest)) {
            throw new LogicException('Complete existing terminal root metadata is required.');
        }
        $admission = RuntimeAdmission::fromConfiguration($manifest);
        // V5 is not aliased to V4: the exact selected RuntimeDatabase keeps its
        // existing NEW-cohort admission and lifecycle-aware operation writer.
        $settings = [
            'chrono_terminal' => $terminal, 'chrono_terminal.http_enabled' => true,
            'chrono_runtime.read_enabled' => true, 'chrono_runtime.write_enabled' => true,
            'chrono_runtime.admission' => $manifest, 'database.connections.chrono' => $admission->configuration(),
            'app.env' => 'production', 'app.debug' => false,
        ];
        foreach ($settings as $name => $value) {
            if ($config->get($name) !== $value) { throw new LogicException('The selected terminal configuration is incomplete.'); }
        }
        $device = ExistingTerminalRootAdmission::fromConfiguration($terminal['existing_key_admissions']['device'], $admission, 'device');
        $card = ExistingTerminalRootAdmission::fromConfiguration($terminal['existing_key_admissions']['card'], $admission, 'card');
        if ($device->path === $card->path || ($device->device === $card->device && $device->inode === $card->inode)) {
            throw new LogicException('Two distinct existing terminal root files are required.');
        }
        $roots = [dirname($app->basePath()), $app->publicPath()];
        $database = $app->make(RuntimeDatabase::class); // Pure existing object; no PDO/file access.
        if (! $database instanceof RuntimeDatabase || ! $database->matchesAdmission($admission)) {
            throw new LogicException('The exact existing runtime database admission is required.');
        }
        $bindings = $app->getBindings();
        return new self($app, $settings, $roots, $database,
            $bindings[AttendanceWriter::class], $bindings[TerminalProtocol::class],
            new ExistingTerminalFileRoot($device, $roots), new ExistingTerminalFileRoot($card, $roots));
    }

    public function keys(): AdmittedTerminalKeys { return $this->keys; }

    /** No DB/root-secret read; optional FreshWeb also rechecks its private metadata. */
    public function assertCurrent(Application $app): void
    {
        if ($app->configurationIsCached() || $app->routesAreCached()
            || [dirname($app->basePath()), $app->publicPath()] !== $this->roots) {
            throw new LogicException('The selected terminal application roots changed.');
        }
        $config = $app->make('config');
        foreach ($this->settings as $name => $value) {
            if ($config->get($name) !== $value) { throw new LogicException('The selected terminal configuration changed.'); }
        }
        if ($app->bound(FreshWebConfiguration::class)) {
            $app->make(FreshWebConfiguration::class)->assertCurrent($config);
        }
        $bindings = $app->getBindings();
        if (! $app->bound(self::class) || $app->make(self::class) !== $this
            || ! $app->bound(AdmittedTerminalKeys::class) || $app->make(AdmittedTerminalKeys::class) !== $this->keys
            || ! $app->bound(RuntimeDatabase::class) || $app->make(RuntimeDatabase::class) !== $this->database
            || $app->isShared(AttendanceWriter::class)
            || ($bindings[AttendanceWriter::class] ?? null) !== $this->writerBinding
            || ($bindings[TerminalProtocol::class] ?? null) !== $this->protocolBinding) {
            throw new LogicException('The selected terminal service profile changed.');
        }
    }

    public function __debugInfo(): array { return ['source' => 'explicit existing terminal root composition']; }
    public function jsonSerialize(): array { return $this->__debugInfo(); }
    public function __serialize(): array { throw new LogicException('Terminal root composition must not be serialized.'); }
}
