Shell Script (from anywhere)

# Basic uninstall - removes package from composer, config, and views
/Users/davepeloso/Herd-Profoto/prophoto-debug/scripts/uninstall.sh /Users/davepeloso/Herd-Profoto/sandbox

# Also drop database tables
/Users/davepeloso/Herd-Profoto/prophoto-debug/scripts/uninstall.sh /Users/davepeloso/Herd-Profoto/sandbox --drop-tables

# Skip confirmation prompts
/Users/davepeloso/Herd-Profoto/prophoto-debug/scripts/uninstall.sh /Users/davepeloso/Herd-Profoto/sandbox --drop-tables --force

# Show help
/Users/davepeloso/Herd-Profoto/prophoto-debug/scripts/uninstall.sh --help
Artisan Command (from within sandbox)

cd /Users/davepeloso/Herd-Profoto/sandbox

# Show options
php artisan debug:uninstall

# Drop tables only
php artisan debug:uninstall --drop-tables

# Remove config file only
php artisan debug:uninstall --remove-config

# Remove everything (tables, config, views)
php artisan debug:uninstall --all

# Skip prompts
php artisan debug:uninstall --all --force
Quick Reinstall Workflow

# Uninstall completely
/Users/davepeloso/Herd-Profoto/prophoto-debug/scripts/uninstall.sh ~/Herd-Profoto/sandbox --drop-tables --force

# Reinstall fresh
cd /Users/davepeloso/Herd-Profoto/sandbox
composer require prophoto/debug:@dev
php artisan migrate
php artisan vendor:publish --tag=debug-config
I still need to register the UninstallCommand in the service provider. Want me to add that import and registration?