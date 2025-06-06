<?php

namespace ClaudeGitHook\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    private string $testDir;
    private string $originalDir;
    private string $cliBinary;

    protected function setUp(): void
    {
        $this->originalDir = getcwd();
        $this->testDir = sys_get_temp_dir() . '/claude-git-hook-cli-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        // Path to the CLI binary (adjust based on your project structure)
        $this->cliBinary = $this->originalDir . '/bin/claude-git-hook';
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        $this->removeDirectory($this->testDir);
    }

    public function testCliShowsHelpByDefault(): void
    {
        // Act: Run CLI without arguments
        $output = $this->runCli([]);

        // Assert: Help message is shown
        $this->assertStringContainsString('Claude Git Hook CLI', $output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('Commands:', $output);
        $this->assertStringContainsString('install', $output);
        $this->assertStringContainsString('configure', $output);
        $this->assertStringContainsString('status', $output);
    }

    public function testCliHelpCommand(): void
    {
        // Act: Run help command
        $output = $this->runCli(['help']);

        // Assert: Help message is shown
        $this->assertStringContainsString('Claude Git Hook CLI', $output);
        $this->assertStringContainsString('Examples:', $output);
    }

    public function testCliStatusWithoutGitRepo(): void
    {
        // Act: Run status command outside git repo
        $output = $this->runCli(['status']);

        // Assert: Warning about no git repository
        $this->assertStringContainsString('Not in a git repository', $output);
    }

    public function testCliStatusWithGitRepo(): void
    {
        // Arrange: Create git repository
        mkdir('.git');
        mkdir('.git/hooks');

        // Act: Run status command
        $output = $this->runCli(['status']);

        // Assert: Status information is shown
        $this->assertStringContainsString('Checking Claude Git Hook status', $output);
        $this->assertStringContainsString('Hook not installed', $output);
    }

    public function testCliInstallInGitRepo(): void
    {
        // Arrange: Create git repository
        mkdir('.git');
        mkdir('.git/hooks');

        // Act: Run install command
        $output = $this->runCli(['install']);

        // Assert: Installation success message
        $this->assertStringContainsString('Installing Claude Git Hook', $output);
        $this->assertStringContainsString('Hook installed successfully', $output);
        $this->assertFileExists('.git/hooks/prepare-commit-msg');
    }

    public function testCliInstallOutsideGitRepo(): void
    {
        // Act: Run install command outside git repo
        $output = $this->runCli(['install']);

        // Assert: Warning message about git repository
        $this->assertStringContainsString('No git repository detected', $output);
    }

    public function testCliUninstallExistingHook(): void
    {
        // Arrange: Create git repo and install hook
        mkdir('.git');
        mkdir('.git/hooks');
        $this->runCli(['install']);
        $this->assertFileExists('.git/hooks/prepare-commit-msg');

        // Act: Run uninstall command
        $output = $this->runCli(['uninstall']);

        // Assert: Hook was removed
        $this->assertStringContainsString('Claude Git Hook uninstalled', $output);
        $this->assertFileDoesNotExist('.git/hooks/prepare-commit-msg');
    }

    public function testCliUninstallNonExistentHook(): void
    {
        // Arrange: Create git repo without hook
        mkdir('.git');
        mkdir('.git/hooks');

        // Act: Run uninstall command
        $output = $this->runCli(['uninstall']);

        // Assert: Warning about missing hook
        $this->assertStringContainsString('Hook not found', $output);
    }

    public function testCliDebugCommand(): void
    {
        // Act: Run debug command
        $output = $this->runCli(['debug']);

        // Assert: Debug information is shown
        $this->assertStringContainsString('Debug Information', $output);
        $this->assertStringContainsString('Current working directory:', $output);
        $this->assertStringContainsString('PHP version:', $output);
        $this->assertStringContainsString('Searching for autoload.php', $output);
    }

    public function testCliUnknownCommand(): void
    {
        // Act: Run unknown command
        $output = $this->runCli(['unknown-command']);

        // Assert: Error message and help
        $this->assertStringContainsString('Unknown command: unknown-command', $output);
        $this->assertStringContainsString('Usage:', $output);
    }

    public function testCliStatusAfterInstallation(): void
    {
        // Arrange: Create git repo and install hook
        mkdir('.git');
        mkdir('.git/hooks');
        $this->runCli(['install']);

        // Act: Check status
        $output = $this->runCli(['status']);

        // Assert: Hook is detected as installed
        $this->assertStringContainsString('Hook installed at:', $output);
        $this->assertStringContainsString('CLAUDE_API_KEY environment variable not set', $output);
        $this->assertStringContainsString('Ready to use!', $output);
    }

    /**
     * Execute the CLI command and return output
     */
    private function runCli(array $args): string
    {
        $command = 'php ' . escapeshellarg($this->cliBinary);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        exec($command . ' 2>&1', $output, $returnCode);
        return implode("\n", $output);
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
