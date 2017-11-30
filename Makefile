# ------------------------------------------------
# Makefile for PePGen
#
# @author Andreas Mängel <andreas.maengel@gmail.com>
#
# * Targets in alphabetical order
# * you may group with prefixes and underscores
#
# ------------------------------------------------

# Variables to Use
COMPOSER=composer

all: #list all targets and run them.
	@echo "\r\nPePGen Makefile Targets\r\n"
	@echo "Use one of the following commands\r\n\r\n"
	@grep -E "^\w+:" Makefile | sort | perl -pe 's/^(\w+):([^\#]+)(\#\s*(.*))?/\1\n\t\4\n/'

dev: # init development system
	$(COMPOSER) install

live: # init live system, delete unnecessary libs
	$(COMPOSER) install --no-dev

fix: # automated repair of code smells
	vendor/bin/phpcbf --standard=psr2 app/

test: #runs all tests
	vendor/bin/phpcs --config-set ignore_warnings_on_exit 1 --standard=psr2 app/
	php -dzend_extension=xdebug.so vendor/bin/phpunit -c phpunit.xml.dist
