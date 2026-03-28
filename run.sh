# download PHP static binary and put it in bin/php
curl -L https://github.com/crazywhalecc/static-php-cli/releases/download/2.8.3/spc-windows-x64.exe -o bin/php.exe
bin/php.exe --server localhost:8000