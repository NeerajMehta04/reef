#!/usr/bin/env bash

# 1. Setup environment 
mkdir -p bin
OS="$(uname -s)"
ARCH="$(uname -m)"
PHP_BIN="./bin/php"
[[ "$OS" == MINGW* || "$OS" == MSYS* ]] && PHP_BIN="./bin/php.exe"

# 2. Install if missing 
if [ ! -f "$PHP_BIN" ]; then
    echo "Installing static PHP binary for $OS-$ARCH..."
    
    case "$OS-$ARCH" in
        Darwin-arm64)  URL="https://dl.static-php.dev/static-php-cli/common/php-8.4.19-cli-macos-aarch64.tar.gz" ;;
        Darwin-x86_64) URL="https://dl.static-php.dev/static-php-cli/common/php-8.4.19-cli-macos-x86_64.tar.gz" ;;
        Linux-aarch64|Linux-arm64) URL="https://dl.static-php.dev/static-php-cli/common/php-8.4.19-fpm-linux-aarch64.tar.gz" ;;
        Linux-x86_64)  URL="https://dl.static-php.dev/static-php-cli/common/php-8.4.19-fpm-linux-x86_64.tar.gz" ;;
        MINGW*-*|MSYS*-*) URL="https://dl.static-php.dev/static-php-cli/windows/spc-max/php-8.4.18-cli-win.zip" ;;
        *) echo "Unsupported platform: $OS-$ARCH"; exit 1 ;;
    esac

    if [[ "$URL" == *.tar.gz ]]; then
        curl -L "$URL" | tar -xz -C bin/
        [ -f bin/php-cli ] && mv bin/php-cli "$PHP_BIN"
        [ -f bin/php ] && [ "$PHP_BIN" != "./bin/php" ] && mv bin/php "$PHP_BIN"
    elif [[ "$URL" == *.zip ]]; then
        curl -L "$URL" -o bin/php.zip
        unzip -o bin/php.zip -d bin/
        # The zip usually contains php.exe directly
        rm bin/php.zip
    else
        curl -L "$URL" -o "$PHP_BIN"
    fi
    
    chmod +x "$PHP_BIN"
fi

# 3. Launch Server 
echo "Starting PHP server on http://localhost:8000"
exec "$PHP_BIN" -S localhost:8000