<?php

namespace ClaudeGitHook;

class Installer
{
    /**
     * Installs the Claude git hook
     */
    public static function install($event = null)
    {
        // Check if we're in a git repository
        if (!is_dir('.git')) {
            echo "⚠️  No git repository detected. Hook will be installed when you run git init.\n";
            return;
        }

        // Create hooks directory
        $hooksDir = '.git/hooks';
        if (!is_dir($hooksDir)) {
            mkdir($hooksDir, 0755, true);
        }

        // Create hook file
        $hookFile = $hooksDir . '/prepare-commit-msg';
        $hookContent = self::getHookContent();

        file_put_contents($hookFile, $hookContent);
        chmod($hookFile, 0755);

        echo "✅ Claude Git Hook installed successfully!\n";
        echo "📋 To use the hook:\n";
        echo "   1. Set your API key: export CLAUDE_API_KEY=\"your-key-here\"\n";
        echo "   2. Get your API key from: https://console.anthropic.com/\n";
        echo "   3. Use: git add . && git commit\n";
    }

    /**
     * Uninstalls the git hook
     */
    public static function uninstall()
    {
        $hookFile = '.git/hooks/prepare-commit-msg';
        if (file_exists($hookFile)) {
            unlink($hookFile);
            echo "✅ Claude Git Hook uninstalled\n";
        } else {
            echo "❌ Hook not found\n";
        }
    }

    /**
     * Configures the API key
     */
    public static function configure($event = null)
    {
        echo "🔧 Configuring Claude Git Hook...\n";
        echo "Enter your Claude API key: ";

        // Read API key from stdin
        $handle = fopen("php://stdin", "r");
        $apiKey = trim(fgets($handle));
        fclose($handle);

        if (empty($apiKey)) {
            echo "❌ API key cannot be empty\n";
            return;
        }

        // Add to shell profile
        $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
        if (empty($homeDir)) {
            echo "❌ Could not determine home directory\n";
            return;
        }

        $shellProfile = $homeDir . '/.bashrc';
        if (file_exists($homeDir . '/.zshrc')) {
            $shellProfile = $homeDir . '/.zshrc';
        }

        $exportLine = "export CLAUDE_API_KEY=\"$apiKey\"";

        // Check if the key is already configured
        $currentContent = file_exists($shellProfile) ? file_get_contents($shellProfile) : '';
        if (strpos($currentContent, 'CLAUDE_API_KEY') !== false) {
            echo "⚠️  CLAUDE_API_KEY already exists in $shellProfile\n";
            echo "Do you want to replace it? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $confirm = trim(fgets($handle));
            fclose($handle);

            if (strtolower($confirm) !== 'y') {
                echo "❌ Configuration cancelled\n";
                return;
            }

            // Remove existing CLAUDE_API_KEY lines
            $lines = explode("\n", $currentContent);
            $filteredLines = array_filter($lines, function ($line) {
                return strpos($line, 'CLAUDE_API_KEY') === false &&
                    strpos($line, '# Claude Git Hook') === false;
            });
            file_put_contents($shellProfile, implode("\n", $filteredLines));
        }

        file_put_contents(
            $shellProfile,
            "\n# Claude Git Hook\n$exportLine\n",
            FILE_APPEND
        );

        echo "✅ API key configured successfully!\n";
        echo "Run: source " . basename($shellProfile) . " to apply changes\n";
        echo "Or restart your terminal session\n";
    }

    /**
     * Gets the hook content
     */
    private static function getHookContent(): string
    {
        return <<<'HOOK'
#!/bin/bash

# Git hook: prepare-commit-msg
# Generates commit messages using Claude API
# Installed via Composer: claude-git-hook

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

# Function to call Claude API
call_claude_api() {
    local diff_content="$1"
    local branch_name="$2"
    
    # Extract issue from branch name (CAM-942, TRIGB2B-42141, etc.)
    # Handle formats like: feature/CAM-421-test, CAM-421-test, TRIGB2B-42141-fix
    local issue=$(echo "$branch_name" | grep -o '[A-Z][A-Z0-9]*-[0-9]\+' | head -1)
    
    # Create prompt for Claude
    local prompt="Analyze the following code changes and generate a concise and descriptive commit message. 
    The message should follow this EXACT format:

[$issue] type: short title (max 50 chars including the issue prefix)

Detailed description of what changed and why.
Include technical details and impact.

issue: $issue

Rules:
1. Valid types: feat, fix, docs, style, refactor, test, chore
2. Short title should be clear and specific
3. Description should be 1-3 sentences explaining the change
4. Always include the issue prefix in brackets at the start
5. Always include the issue line at the end
6. Do NOT include any other text or explanations

Changes to analyze:
\`\`\`diff
$diff_content
\`\`\`"

    # Simple JSON escaping without Python
    # Replace problematic characters
    local escaped_prompt=$(echo "$prompt" | sed 's/\\/\\\\/g' | sed 's/"/\\"/g' | \
        sed 's/$/\\n/g' | tr -d '\n' | sed 's/\\n$//')
    
    # Create JSON payload using basic shell tools
    local json_payload="{\"model\":\"$MODEL\",\"max_tokens\":300,\"messages\":\
[{\"role\":\"user\",\"content\":\"$escaped_prompt\"}]}"

    # Make API call
    local response=$(curl -s -X POST "$CLAUDE_API_URL" \
        -H "Content-Type: application/json" \
        -H "x-api-key: $CLAUDE_API_KEY" \
        -H "anthropic-version: 2023-06-01" \
        -d "$json_payload")

    # Extract content from response using grep and sed (no Python needed)
    local message=$(echo "$response" | grep -o '"text":"[^"]*"' | head -1 | \
        sed 's/"text":"//' | sed 's/"$//' | sed 's/\\n/\n/g' | sed 's/\\"/"/g')
    
    # Check if we got a valid response
    if [ -n "$message" ] && [[ "$message" != *"\"error\""* ]] && [[ "$message" != "Error: Invalid API response" ]]; then
        echo "$message"
    else
        echo "Error: Invalid API response"
        return 1
    fi
}

# Fallback function to generate basic message
generate_fallback_message() {
    local diff_content="$1"
    local branch_name="$2"
    
    # Extract issue from branch name
    # Handle formats like: feature/CAM-421-test, CAM-421-test, TRIGB2B-42141-fix
    local issue=$(echo "$branch_name" | grep -o '[A-Z][A-Z0-9]*-[0-9]\+' | head -1)
    
    # Basic diff analysis
    local files_changed=$(echo "$diff_content" | grep "^diff --git" | wc -l)
    local additions=$(echo "$diff_content" | grep "^+" | grep -v "^+++" | wc -l)
    local deletions=$(echo "$diff_content" | grep "^-" | grep -v "^---" | wc -l)
    
    # Detect change type and create message
    local type="feat"
    local description="Update code"
    
    if echo "$diff_content" | grep -q "\.md$\|\.txt$\|README"; then
        type="docs"
        description="Update documentation files"
    elif echo "$diff_content" | grep -q "test\|spec"; then
        type="test"
        description="Update test files and specifications"
    elif [ "$deletions" -gt "$additions" ]; then
        type="refactor"
        description="Refactor code and remove unused elements"
    else
        type="feat"
        description="Implement new features and functionality"
    fi
    
    # Format message with issue prefix
    local title="[$issue] $type: update $files_changed files"
    if [ -z "$issue" ]; then
        title="$type: update $files_changed files"
    fi
    
    echo "$title"
    echo ""
    echo "$description. Modified $files_changed files with $additions additions and $deletions deletions."
    echo ""
    if [ -n "$issue" ]; then
        echo "issue: $issue"
    else
        echo "issue: none"
    fi
}

echo "🔍 Analyzing changes with Claude..."

# Get current branch name
BRANCH_NAME=$(git rev-parse --abbrev-ref HEAD)
echo "📋 Branch: $BRANCH_NAME"

# Try to generate message with Claude
COMMIT_MESSAGE=$(call_claude_api "$DIFF" "$BRANCH_NAME")
EXIT_CODE=$?

# If API fails, use fallback message
if [ $EXIT_CODE -ne 0 ] || [ -z "$COMMIT_MESSAGE" ] || [[ "$COMMIT_MESSAGE" == "Error: Invalid API response" ]]; then
    echo "⚠️  Claude API unavailable, generating basic message..."
    COMMIT_MESSAGE=$(generate_fallback_message "$DIFF" "$BRANCH_NAME")
fi

# Write message to commit file
echo "$COMMIT_MESSAGE" > "$1"

echo "✅ Commit message generated:"
echo "   $COMMIT_MESSAGE"
HOOK;
    }
}
