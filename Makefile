setup:
	composer update

clean:
	docker-compose down -v --remove-orphans
	vendor/bin/php-cs-fixer fix

build:
	composer install
	docker-compose build --pull

push:
	docker-compose push

up:
	docker-compose up frontend redis-commander

test:
	vendor/bin/phpcs --warning-severity=6 --standard=PSR2 src tests
	vendor/bin/phpunit

worker-feed:
	docker-compose run baaz bin/feed-ingester

cli:
	docker-compose run baaz /bin/bash