#!/usr/bin/env bash

# Constants for PHP 8.5 (Modern Expression-Oriented PHP)
PHP_VERSION="8.5.4"
BIN_DIR="./bin"
PORT=8000

_install_php() {
    local os_type="$(uname -s)"
    local arch_type="$(uname -m)"
    local url=""
    local target_bin=""

    echo "Platform detected: $os_type ($arch_type)"
    mkdir -p "$BIN_DIR"

    case "$os_type" in
        Darwin*)
            target_bin="$BIN_DIR/php"
            if [ "$arch_type" = "arm64" ]; then
                url="https://dl.static-php.dev/static-php-cli/common/php-$PHP_VERSION-cli-macos-aarch64.tar.gz"
            else
                url="https://dl.static-php.dev/static-php-cli/common/php-$PHP_VERSION-cli-macos-x86_64.tar.gz"
            fi
            ;;
        Linux*)
            target_bin="$BIN_DIR/php"
            if [ "$arch_type" = "aarch64" ] || [ "$arch_type" = "arm64" ]; then
                url="https://dl.static-php.dev/static-php-cli/common/php-$PHP_VERSION-fpm-linux-aarch64.tar.gz"
            else
                url="https://dl.static-php.dev/static-php-cli/common/php-$PHP_VERSION-fpm-linux-x86_64.tar.gz"
            fi
            ;;
        MINGW*|MSYS*|CYGWIN*)
            url="https://github.com/crazywhalecc/static-php-cli/releases/download/2.8.3/spc-windows-x64.exe"
            target_bin="$BIN_DIR/php.exe"
            ;;
        *)
            echo "Error: Unsupported OS $os_type"
            exit 1
            ;;
    esac

    echo "Downloading PHP from $url..."
    if [[ "$url" == *.tar.gz ]]; then
        curl -L "$url" | tar -xz -C "$BIN_DIR"
        if [ -f "$BIN_DIR/php-cli" ]; then
            mv "$BIN_DIR/php-cli" "$target_bin"
        elif [ -f "$BIN_DIR/php" ] && [ "$target_bin" != "$BIN_DIR/php" ]; then
            mv "$BIN_DIR/php" "$target_bin"
        fi
    else
        curl -L "$url" -o "$target_bin"
    fi

    chmod +x "$target_bin"
    echo "PHP installed to $target_bin"
}

# Find or Install
if [ -f "$BIN_DIR/php.exe" ]; then
    PHP_EXEC="$BIN_DIR/php.exe"
elif [ -f "$BIN_DIR/php" ]; then
    PHP_EXEC="$BIN_DIR/php"
else
    _install_php
    [ -f "$BIN_DIR/php.exe" ] && PHP_EXEC="$BIN_DIR/php.exe" || PHP_EXEC="$BIN_DIR/php"
fi

# Final sanity check
if [ ! -f "$PHP_EXEC" ]; then
    echo "Failed to locate or install PHP binary."
    exit 1
fi

echo "Launching PHP $PHP_VERSION Development Server on http://localhost:$PORT"
exec "$PHP_EXEC" -S localhost:$PORT