<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CiEnvironmentPreparationTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir().'/draka-ci-'.bin2hex(random_bytes(8));
        mkdir($this->workspace, 0700);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workspace, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->workspace);
        parent::tearDown();
    }

    public function test_clean_checkout_gets_writable_runtime_directories_without_touching_existing_files(): void
    {
        file_put_contents($this->workspace.'/unrelated-marker', 'preserve');
        foreach ([1, 2] as $attempt) {
            $process = $this->prepare('testing');
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            foreach ([
                'bootstrap/cache',
                'storage/framework/cache/data',
                'storage/framework/sessions',
                'storage/framework/views',
                'storage/framework/testing',
                'storage/logs',
            ] as $directory) {
                $path = $this->workspace.'/'.$directory;
                $this->assertDirectoryExists($path);
                $this->assertSame(2, file_put_contents($path.'/write-probe', 'ok'));
                unlink($path.'/write-probe');
                if (PHP_OS_FAMILY !== 'Windows') {
                    $this->assertSame(0700, fileperms($path) & 0777);
                }
            }
        }
        $this->assertSame('preserve', file_get_contents($this->workspace.'/unrelated-marker'));
        $this->assertFileDoesNotExist($this->workspace.'/.env');
        $this->assertFileDoesNotExist($this->workspace.'/database/database.sqlite');
    }

    public function test_preparation_refuses_a_non_testing_environment_before_creating_directories(): void
    {
        $process = $this->prepare('production');
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('requires APP_ENV=testing', $process->getErrorOutput());
        $this->assertDirectoryDoesNotExist($this->workspace.'/bootstrap');
        $this->assertDirectoryDoesNotExist($this->workspace.'/storage');
    }

    private function prepare(string $environment): Process
    {
        $process = new Process(
            [PHP_BINARY, dirname(__DIR__, 2).'/scripts/prepare-ci.php'],
            $this->workspace,
            ['APP_ENV' => $environment],
        );
        $process->run();

        return $process;
    }
}
