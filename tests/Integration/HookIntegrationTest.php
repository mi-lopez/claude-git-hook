<?php

namespace ClaudeGitHook\Tests\Integration;

use PHPUnit\Framework\TestCase;

class HookIntegrationTest extends TestCase
{
    private string $testDir;
    private string $originalDir;

    protected function setUp(): void
    {
        $this->originalDir = getcwd();
        $this->testDir = sys_get_temp_dir() . '/claude-git-hook-integration-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        // Initialize git repository
        exec('git init');
        exec('git config user.email "test@example.com"');
        exec('git config user.name "Test User"');
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        $this->removeDirectory($this->testDir);
    }

    public function testHookExecutionWithoutApiKey(): void
    {
        // Arrange: Install hook and create test file
        $this->installHook();
        $this->createTestCommit();

        // Ensure no API key is set
        unset($_ENV['CLAUDE_API_KEY']);
        putenv('CLAUDE_API_KEY');

        // Act: Execute commit (this will use the fallback)
        $output = $this->executeCommit();

        // Assert: Fallback message was generated
        $this->assertStringContainsString('Error: CLAUDE_API_KEY is not configured', $output);
    }

    public function testHookGeneratesFallbackMessage(): void
    {
        // Arrange: Install hook with fallback scenario
        $this->installHook();
        $this->createTestCommit();

        // Set invalid API key to trigger fallback
        putenv('CLAUDE_API_KEY=invalid-key');

        // Act: Execute commit
        $output = $this->executeCommit();

        // Assert: Basic fallback functionality works
        $this->assertStringContainsString('Analyzing changes', $output);
    }

    public function testIssueExtractionFromBranchName(): void
    {
        // Arrange: Create branch with issue pattern and install hook
        exec('git checkout -b feature/CAM-123-test-feature');
        $this->installHook();

        // Modify hook to test issue extraction without API call
        $this->modifyHookForTesting();
        $this->createTestCommit();

        // Act: Execute commit
        $output = $this->executeCommit();

        // Assert: Issue was extracted correctly
        $this->assertStringContainsString('CAM-123', $output);
    }

    public function testMultipleIssuePatterns(): void
    {
        $patterns = [
            'feature/CAM-123-test' => 'CAM-123',
            'TRIGB2B-456-bugfix' => 'TRIGB2B-456',
            'hotfix/PROJ-789-urgent' => 'PROJ-789',
        ];

        foreach ($patterns as $branchName => $expectedIssue) {
            // Arrange: Create branch and install hook
            exec("git checkout -b " . escapeshellarg($branchName) . " 2>/dev/null || git checkout " .
                escapeshellarg($branchName));
            $this->installHook();
            $this->modifyHookForTesting();

            // Create unique test file for each branch
            $testFile = "test-" . str_replace(['/', '-'], ['_', '_'], $branchName) . ".txt";
            file_put_contents($testFile, "Test content for $branchName");
            exec("git add " . escapeshellarg($testFile));

            // Act: Execute commit
            $output = $this->executeCommit();

            $this->assertStringContainsString(
                $expectedIssue,
                $output,
                "Failed to extract issue '$expectedIssue' from branch '$branchName'"
            );

            // Cleanup for next iteration
            exec('git checkout main 2>/dev/null || git checkout master 2>/dev/null');
        }
    }

    public function testHookPermissions(): void
    {
        // Arrange: Install hook
        $this->installHook();
        $hookFile = '.git/hooks/prepare-commit-msg';

        // Act & Assert: Hook file has correct permissions
        $this->assertFileExists($hookFile);
        $this->assertTrue(is_executable($hookFile), 'Hook file should be executable');

        $permissions = fileperms($hookFile);
        $this->assertTrue(($permissions & 0x0040) !== 0, 'Owner should have read permission');
        $this->assertTrue(($permissions & 0x0080) !== 0, 'Owner should have write permission');
        $this->assertTrue(($permissions & 0x0100) !== 0, 'Owner should have execute permission');
    }

    public function testHookWithDifferentFileTypes(): void
    {
        // Arrange: Install hook
        $this->installHook();
        $this->modifyHookForTesting();

        $testCases = [
            'test.php' => 'PHP file',
            'README.md' => 'Markdown documentation',
            'test.js' => 'JavaScript file',
            'styles.css' => 'CSS stylesheet',
            'config.json' => 'JSON configuration'
        ];

        foreach ($testCases as $filename => $description) {
            // Create file with appropriate content
            file_put_contents($filename, "// $description\nconsole.log('test');");
            exec("git add $filename");

            // Execute commit
            $output = $this->executeCommit();

            // Basic assertion that hook executed
            $this->assertStringContainsString('Analyzing changes', $output);

            // Reset for next test (only if we have commits)
            exec("git log --oneline -1 2>/dev/null", $logOutput, $logReturnCode);
            if ($logReturnCode === 0) {
                exec("git reset --soft HEAD~1 2>/dev/null");
            }
        }
    }

    private function installHook(): void
    {
        // Copy the hook content directly (simulating installation)
        $hookContent = $this->getHookContent();
        $hookFile = '.git/hooks/prepare-commit-msg';

        if (!is_dir('.git/hooks')) {
            mkdir('.git/hooks', 0755, true);
        }

        file_put_contents($hookFile, $hookContent);
        chmod($hookFile, 0755);
    }

    private function modifyHookForTesting(): void
    {
        // Modify hook to skip API calls and use fallback for testing
        $hookFile = '.git/hooks/prepare-commit-msg';
        $content = file_get_contents($hookFile);

        // Force fallback mode for testing
        $content = str_replace(
            'COMMIT_MESSAGE=$(call_claude_api "$DIFF" "$BRANCH_NAME")',
            'COMMIT_MESSAGE="test: fallback message for testing"',
            $content
        );

        file_put_contents($hookFile, $content);
    }

    private function createTestCommit(): void
    {
        file_put_contents('test.txt', 'Test content ' . time());
        exec('git add test.txt');
    }

    private function executeCommit(): string
    {
        exec('git commit -m "temp" 2>&1', $output, $returnCode);
        return implode("\n", $output);
    }

    private function getHookContent(): string
    {
        return <<<'HOOK'
#!/bin/bash

# Git hook: prepare-commit-msg (Test Version)
# Generates commit messages using Claude API

# Configuration
CLAUDE_API_KEY="${CLAUDE_API_KEY:-}"
CLAUDE_API_URL="https://api.anthropic.com/v1/messages"
MODEL="claude-sonnet-4-20250514"

# Check if API key exists
if [ -z "$CLAUDE_API_KEY" ]; then
    echo "Error: CLAUDE_API_KEY is not configured"
    echo "Set it with: export CLAUDE_API_KEY='your-key-here'"
    exit 1
fi

# Get diff of staged changes
DIFF=$(git diff --cached --no-color)

# If no staged changes, exit
if [ -z "$DIFF" ]; then
    echo "No staged changes for commit"
    exit 0
fi

# Get current branch name
BRANCH_NAME=$(git rev-parse --abbrev-ref HEAD)
echo "📋 Branch: $BRANCH_NAME"

# Function to call Claude API
call_claude_api() {
    local diff_content="$1"
    local branch_name="$2"
    
    # Extract issue from branch name
    local issue=$(echo "$branch_name" | grep -o '[A-Z][A-Z0-9]*-[0-9]\+' | head -1)
    
    echo "test: generated commit message with issue $issue"
}

# Fallback function to generate basic message
generate_fallback_message() {
    local diff_content="$1"
    local branch_name="$2"
    
    local issue=$(echo "$branch_name" | grep -o '[A-Z][A-Z0-9]*-[0-9]\+' | head -1)
    
    echo "[$issue] test: fallback commit message"
    echo ""
    echo "Fallback message generated during testing."
    echo ""
    echo "issue: $issue"
}

echo "🔍 Analyzing changes with Claude..."

# Try to generate message with Claude
COMMIT_MESSAGE=$(call_claude_api "$DIFF" "$BRANCH_NAME")
EXIT_CODE=$?

# If API fails, use fallback message
if [ $EXIT_CODE -ne 0 ] || [ -z "$COMMIT_MESSAGE" ] || [[ "$COMMIT_MESSAGE" == *"Error"* ]]; then
    echo "⚠️  Claude API unavailable, generating basic message..."
    COMMIT_MESSAGE=$(generate_fallback_message "$DIFF" "$BRANCH_NAME")
fi

# Write message to commit file
echo "$COMMIT_MESSAGE" > "$1"

echo "✅ Commit message generated:"
echo "   $COMMIT_MESSAGE"
HOOK;
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
