<?php

namespace ClaudeGitHook\Tests\Unit;

use ClaudeGitHook\Installer;
use PHPUnit\Framework\TestCase;

class InstallerTest extends TestCase
{
    private string $testDir;
    private string $originalDir;

    protected function setUp(): void
    {
        $this->originalDir = getcwd();
        $this->testDir = sys_get_temp_dir() . '/claude-git-hook-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        $this->removeDirectory($this->testDir);
    }

    public function testInstallCreatesHookInGitRepository(): void
    {
        // Arrange: Create a fake git repository
        mkdir('.git');
        mkdir('.git/hooks');

        // Act: Install the hook
        ob_start();
        Installer::install();
        $output = ob_get_clean();

        // Assert: Hook file was created
        $this->assertFileExists('.git/hooks/prepare-commit-msg');
        $this->assertTrue(is_executable('.git/hooks/prepare-commit-msg'));
        $this->assertStringContainsString('Claude Git Hook installed successfully!', $output);
    }

    public function testInstallWarnsWhenNotInGitRepository(): void
    {
        // Act: Try to install without git repository
        ob_start();
        Installer::install();
        $output = ob_get_clean();

        // Assert: Warning message is shown
        $this->assertStringContainsString('No git repository detected', $output);
        $this->assertFileDoesNotExist('.git/hooks/prepare-commit-msg');
    }

    public function testUninstallRemovesHook(): void
    {
        // Arrange: Create git repo and install hook
        mkdir('.git');
        mkdir('.git/hooks');
        Installer::install();
        $this->assertFileExists('.git/hooks/prepare-commit-msg');

        // Act: Uninstall hook
        ob_start();
        Installer::uninstall();
        $output = ob_get_clean();

        // Assert: Hook file was removed
        $this->assertFileDoesNotExist('.git/hooks/prepare-commit-msg');
        $this->assertStringContainsString('Claude Git Hook uninstalled', $output);
    }

    public function testUninstallWarnsWhenHookDoesNotExist(): void
    {
        // Arrange: Create git repo without hook
        mkdir('.git');
        mkdir('.git/hooks');

        // Act: Try to uninstall non-existent hook
        ob_start();
        Installer::uninstall();
        $output = ob_get_clean();

        // Assert: Warning message is shown
        $this->assertStringContainsString('Hook not found', $output);
    }

    public function testHookContentContainsExpectedElements(): void
    {
        // Arrange: Create git repository
        mkdir('.git');
        mkdir('.git/hooks');

        // Act: Install hook
        Installer::install();

        // Assert: Hook contains expected content
        $hookContent = file_get_contents('.git/hooks/prepare-commit-msg');

        $this->assertStringContainsString('#!/bin/bash', $hookContent);
        $this->assertStringContainsString('CLAUDE_API_KEY', $hookContent);
        $this->assertStringContainsString('call_claude_api', $hookContent);
        $this->assertStringContainsString('generate_fallback_message', $hookContent);
        $this->assertStringContainsString('git diff --cached', $hookContent);
        $this->assertStringContainsString('issue:', $hookContent);
    }

    public function testConfigureWithValidInput(): void
    {
        // Arrange: Prepare test environment
        $testApiKey = 'sk-test-api-key-12345';
        $testProfile = $this->testDir . '/.bashrc';

        // Create the test profile file first
        touch($testProfile);

        // Mock HOME environment variable
        $_SERVER['HOME'] = $this->testDir;

        // Act: Configure API key (we'll skip the actual stdin input test for now)
        $this->markTestSkipped('Stdin input testing requires more complex mocking setup');
    }

    public function testConfigureWithEmptyInput(): void
    {
        // Arrange: Mock empty stdin input
        $this->markTestSkipped('Stdin input testing requires more complex mocking setup');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
