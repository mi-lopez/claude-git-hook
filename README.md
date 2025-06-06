# Claude Git Hook

[![CI](https://github.com/mi-lopez/claude-git-hook/workflows/CI/badge.svg)](https://github.com/mi-lopez/claude-git-hook/actions)
[![Latest Stable Version](https://poser.pugx.org/mi-lopez/claude-git-hook/v)](https://packagist.org/packages/mi-lopez/claude-git-hook)
[![License](https://poser.pugx.org/mi-lopez/claude-git-hook/license)](https://packagist.org/packages/mi-lopez/claude-git-hook)
[![PHP Version Require](https://poser.pugx.org/mi-lopez/claude-git-hook/require/php)](https://packagist.org/packages/mi-lopez/claude-git-hook)

Automatically generate intelligent commit messages using Claude AI. This package installs a git hook that analyzes your code changes and creates descriptive commit messages following conventional commit standards with automatic issue extraction from branch names.

## Features

- 🤖 **AI-powered**: Uses Claude AI to analyze code changes
- 📝 **Conventional commits**: Follows standard commit message format
- 🎯 **Issue extraction**: Automatically extracts issue numbers from branch names
- 🔄 **Fallback system**: Works even when API is unavailable
- 🚀 **Easy installation**: Simple Composer package
- ⚡ **Fast setup**: Automatic installation via Composer scripts
- 🐍 **No Python dependency**: Works with basic Unix tools

## Commit Message Format

Generated messages follow this format:

```
[ISSUE-123] type: short descriptive title

Detailed description of what changed and why.
Include technical details and impact.

issue: ISSUE-123
```

### Examples

**Input:** Branch `feature/CAM-942-implement-auth` with authentication code changes

**Output:**
```
[CAM-942] feat: implement user authentication system

Add JWT-based authentication with login, logout, and token refresh.
Includes middleware for route protection and session management.

issue: CAM-942
```

## Installation

### Via Composer (Recommended)

```bash
composer require mi-lopez/claude-git-hook --dev
```

The hook will be automatically installed after Composer finishes.

### Manual Installation

1. Clone this repository
2. Run the installer:
```bash
php src/Installer.php
```

## Configuration

### 1. Get Claude API Key

1. Visit [Anthropic Console](https://console.anthropic.com/)
2. Create an account or sign in
3. Generate an API key

### 2. Configure API Key

```bash
# Use the CLI tool to configure interactively
./vendor/bin/claude-git-hook configure
```

Or set manually:
```bash
# Set environment variable
export CLAUDE_API_KEY="your-api-key-here"

# Make it permanent
echo 'export CLAUDE_API_KEY="your-api-key-here"' >> ~/.bashrc
source ~/.bashrc
```

## Usage

Once installed and configured, the hook works automatically:

```bash
# Make your changes on a branch like: feature/CAM-123-new-feature
git add .

# Commit - message will be generated automatically
git commit

# Result:
# [CAM-123] feat: implement new feature functionality
# 
# Add comprehensive feature implementation with proper error handling.
# Includes unit tests and documentation updates.
# 
# issue: CAM-123
```

### Manual Commands

```bash
# Check installation status
./vendor/bin/claude-git-hook status

# Reinstall hook
./vendor/bin/claude-git-hook install

# Remove hook
./vendor/bin/claude-git-hook uninstall

# Configure API key
./vendor/bin/claude-git-hook configure

# Debug information
./vendor/bin/claude-git-hook debug
```

## Branch Name Patterns

The hook automatically extracts issue numbers from branch names:

- ✅ `feature/CAM-942-implement-auth` → `CAM-942`
- ✅ `CAM-942-implement-auth` → `CAM-942`
- ✅ `TRIGB2B-42141-fix-login` → `TRIGB2B-42141`
- ✅ `bugfix/PROJ-123-memory-leak` → `PROJ-123`
- ❌ `feature-branch` → No issue extracted

## How It Works

1. **Code Analysis**: When you run `git commit`, the hook captures your staged changes
2. **Branch Analysis**: Extracts issue number from current branch name
3. **AI Processing**: Sends the diff to Claude AI for analysis
4. **Message Generation**: Claude generates a structured commit message
5. **Fallback**: If API fails, generates a basic message based on file analysis

## Commit Message Types

The generated messages use conventional commit types:

- `feat`: New features
- `fix`: Bug fixes
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Test changes
- `chore`: Maintenance tasks

## Configuration Options

### Environment Variables

- `CLAUDE_API_KEY`: Your Claude API key (required)

### Custom Configuration

You can modify the hook behavior by editing `.git/hooks/prepare-commit-msg` directly.

## Requirements

- Git repository
- curl (for API calls)
- Basic Unix tools (grep, sed, tr)
- Claude API key

## Troubleshooting

### Hook Not Working

```bash
# Check status
./vendor/bin/claude-git-hook status

# Reinstall
./vendor/bin/claude-git-hook install
```

### API Key Issues

```bash
# Verify API key is set
echo $CLAUDE_API_KEY

# Reconfigure
./vendor/bin/claude-git-hook configure
```

### Permission Issues

```bash
# Fix hook permissions
chmod +x .git/hooks/prepare-commit-msg
```

### Debug Information

```bash
# Get detailed debug info
./vendor/bin/claude-git-hook debug
```

## Development

### Project Structure

```
├── composer.json          # Package configuration
├── src/
│   └── Installer.php      # Installation logic
├── bin/
│   └── claude-git-hook    # CLI command
├── tests/                 # PHPUnit tests
├── .github/
│   └── workflows/         # GitHub Actions
└── README.md             # Documentation
```

### Running Tests

```bash
# Install dev dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Run the test suite
6. Submit a pull request

### Code Style

This project follows PSR-12 coding standards:

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Support

- 📖 [Documentation](https://github.com/mi-lopez/claude-git-hook/wiki)
- 🐛 [Issues](https://github.com/mi-lopez/claude-git-hook/issues)
- 💬 [Discussions](https://github.com/mi-lopez/claude-git-hook/discussions)

## Changelog

### v1.0.0
- Initial release
- Basic commit message generation
- Issue extraction from branch names
- Composer package support
- CLI interface
- Fallback system
- No Python dependency

---

Made with ❤️ and AI by [mi-lopez](https://github.com/mi-lopez)
