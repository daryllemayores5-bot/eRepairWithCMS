<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProjectDoctorTest extends TestCase
{
    private const NODE_RANGE = '^22.22.2 || ^24.15.0 || >=26.0.0';

    private function manifest(?string $range = self::NODE_RANGE): void
    {
        File::shouldReceive('get')->once()->with(base_path('../frontend/package.json'))
            ->andReturn(json_encode(['engines' => $range === null ? [] : ['node' => $range], 'dependencies' => []], JSON_THROW_ON_ERROR));
    }

    private function tools(string $version, int $nodeExit = 0, int $npmExit = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($version, $nodeExit, $npmExit) {
            return match ($process->command[0]) {
                'node' => Process::result(output: $version, exitCode: $nodeExit),
                'npm', 'npm.cmd' => Process::result(output: '11.9.0', exitCode: $npmExit),
                default => throw new RuntimeException('Unexpected diagnostic command'),
            };
        });
        Process::preventStrayProcesses();
    }

    private function check(): int
    {
        return Artisan::call('erepair:doctor', ['--skip-database' => true, '--skip-build' => true]);
    }

    public function test_rejects_the_current_unsupported_node_release_instead_of_reporting_ready(): void
    {
        $this->manifest();
        $this->tools('v24.14.0');

        $this->assertSame(1, $this->check());
        $output = Artisan::output();
        $this->assertStringContainsString(self::NODE_RANGE, $output);
        $this->assertStringContainsString('v24.14.0', $output);
        Process::assertRan(fn (PendingProcess $process) => $process->command === ['node', '--version']
            && $process->timeout === 10 && $process->path === base_path('..'));
    }

    #[DataProvider('nodeVersions')]
    public function test_node_support_follows_declared_stable_version_ranges(string $version, int $exit): void
    {
        $this->manifest();
        $this->tools($version);

        $this->assertSame($exit, $this->check());
    }

    public static function nodeVersions(): array
    {
        return [
            'old major' => ['v20.20.0', 1],
            'below Node 22 minimum patch' => ['v22.22.1', 1],
            'Node 22 minimum patch' => ['v22.22.2', 0],
            'later Node 22 patch' => ['v22.23.0', 0],
            'excluded Node 23 major' => ['v23.12.0', 1],
            'Node 24 minimum patch' => ['v24.15.0', 0],
            'later Node 24 patch' => ['v24.16.1', 0],
            'excluded Node 25 major' => ['v25.10.0', 1],
            'Node 26 minimum' => ['v26.0.0', 0],
            'later allowed major' => ['v27.0.0', 0],
            'prerelease at minimum' => ['v26.0.0-rc.1', 1],
            'prerelease above minimum' => ['v26.1.0-rc.1', 1],
            'unparseable output' => ['not-a-version', 1],
            'missing output' => ['', 1],
        ];
    }

    public function test_uses_a_changed_manifest_requirement_without_a_second_hardcoded_minimum(): void
    {
        $this->manifest('^30.0.0');
        $this->tools('v26.0.0');

        $this->assertSame(1, $this->check());
        $this->assertStringContainsString('^30.0.0', Artisan::output());
    }

    #[DataProvider('invalidRequirements')]
    public function test_missing_or_invalid_node_requirements_fail_closed(?string $range): void
    {
        $this->manifest($range);
        $this->tools('v26.0.0');

        $this->assertSame(1, $this->check());
    }

    public static function invalidRequirements(): array
    {
        return [[null], [''], ['not-a-version-range']];
    }

    public function test_a_failed_node_executable_is_unavailable_even_with_valid_version_output(): void
    {
        $this->manifest();
        $this->tools('v26.0.0', nodeExit: 1);

        $this->assertSame(1, $this->check());
        $this->assertStringContainsString('Executable could not be run', Artisan::output());
    }

    public function test_a_failed_npm_executable_is_not_hidden_by_a_supported_node_release(): void
    {
        $this->manifest();
        $this->tools('v26.0.0', npmExit: 1);

        $this->assertSame(1, $this->check());
    }

    public function test_process_start_exceptions_do_not_expose_their_details(): void
    {
        $this->manifest();
        Process::fake(fn () => throw new RuntimeException('private process environment'));
        Process::preventStrayProcesses();

        $this->assertSame(1, $this->check());
        $output = Artisan::output();
        $this->assertStringContainsString('Executable could not be run', $output);
        $this->assertStringNotContainsString('private process environment', $output);
    }
}
