-include .env
PORT ?= 8080
export

.PHONY: install start test lint check

install:
	composer install

start:
	php -S 0.0.0.0:$(PORT) -t public public/index.php

test:
	vendor/bin/phpunit

lint:
	vendor/bin/phpcs

check: lint test
