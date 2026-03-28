#!/usr/bin/env bash

# Setup directory and local binary path
mkdir -p bin
PHP_BIN="./bin/php"
[[ "$(uname -s)" == MINGW* || "$(uname -s)" == MSYS* ]] && PHP_BIN="./bin/php.exe"

# 1. Install if missing
if [ ! -f "$PHP_BIN" ]; then
    echo "Installing static PHP binary..."
    
    case "$(uname -s)-$(uname -m)" in
        Darwin-arm64)  URL="https://dl.static-php.dev/static-php-cli/common/php-8.5.4-cli-macos-aarch64.tar.gz" ;;
        Darwin-x86_64) URL="https://dl.static-php.dev/static-php-cli/common/php-8.5.4-cli-macos-x86_64.tar.gz" ;;
        Linux-aarch64) URL="https://dl.static-php.dev/static-php-cli/common/php-8.5.4-cli-linux-aarch64.tar.gz" ;;
        Linux-x86_64)  URL="https://dl.static-php.dev/static-php-cli/common/php-8.5.4-cli-linux-x86_64.tar.gz" ;;
        MINGW*-*|MSYS*-*) URL="https://github.com/crazywhalecc/static-php-cli/releases/download/2.8.3/spc-windows-x64.exe" ;;
        *) echo "Unsupported platform"; exit 1 ;;
    esac

    if [[ "$URL" == *.tar.gz ]]; then
        curl -L "$URL" | tar -xz -C bin/
        [ -f bin/php-cli ] && mv bin/php-cli "$PHP_BIN"
    else
        curl -L "$URL" -o "$PHP_BIN"
    fi
    chmod +x "$PHP_BIN"
fi

# 2. Launch Server (Transparent OS process)
echo "Starting PHP server on http://localhost:8000"
exec "$PHP_BIN" -S localhost:8000