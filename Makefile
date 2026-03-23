# Integration DocuSeal - Makefile
app_name=$(notdir $(CURDIR))
build_dir=$(CURDIR)/build
cert_dir=$(HOME)/.nextcloud/certificates

all: dev-setup build

# Dev environment setup
dev-setup: composer npm

composer:
	composer install --prefer-dist

npm:
	npm install

# Building
build: npm-build

npm-build:
	npm run build

npm-watch:
	npm run watch

# Linting
lint: lint-php lint-js lint-css

lint-php:
	vendor/bin/psalm --no-cache

lint-js:
	npm run lint

lint-css:
	npm run stylelint

lint-fix:
	npm run lint:fix

# Testing
test: test-php

test-php:
	vendor/bin/phpunit -c tests/phpunit.xml

# Cleaning
clean:
	rm -rf js/
	rm -rf node_modules/
	rm -rf vendor/

# Packaging for app store
appstore: clean npm build
	mkdir -p $(build_dir)
	tar czf $(build_dir)/$(app_name).tar.gz \
		--exclude-from=$(CURDIR)/.gitignore \
		--exclude=".git" \
		--exclude="$(build_dir)" \
		--exclude="tests" \
		--exclude="src" \
		--exclude="node_modules" \
		--exclude="webpack.config.js" \
		--exclude="Makefile" \
		--exclude=".eslintrc.js" \
		--exclude=".stylelintrc.js" \
		--exclude="psalm.xml" \
		--exclude="phpunit.xml" \
		--exclude=".github" \
		-C $(CURDIR)/.. $(app_name)

.PHONY: all dev-setup composer npm build npm-build npm-watch lint lint-php lint-js lint-css lint-fix test test-php clean appstore
