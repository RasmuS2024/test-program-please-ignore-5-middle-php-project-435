-include .env
PORT ?= 8080
export

.PHONY: install build start test lint check contract contract-check

install:
	composer install
	npm ci

build:
	rm -rf public/assets public/images public/index.html
	mkdir -p public
	cp -R node_modules/@hexlet/php-online-store-frontend/dist/. public/

start:
	php -S 0.0.0.0:$(PORT) -t public public/index.php

test:
	vendor/bin/phpunit

lint:
	vendor/bin/phpcs

check: lint test

contract:
	npx tsp compile contract

contract-check:
	npx tsp compile contract
	git diff --exit-code -- contract/openapi.yaml
