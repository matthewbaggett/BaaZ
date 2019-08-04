setup:
	composer update --ignore-platform-reqs

clean:
	docker-compose down --remove-orphans
	chmod +x *.runit bin/*
	-vendor/bin/php-cs-fixer fix

build:
	composer install --ignore-platform-reqs
	docker-compose build --pull

push:
	docker-compose push

up:
	docker-compose up \
		frontend \
		backend \
		worker-feed \
		worker-images

test:
	-vendor/bin/php-cs-fixer fix
	vendor/bin/phpcs --warning-severity=6 --standard=PSR2 src tests
	vendor/bin/phpunit

worker-feed:
	docker-compose run baaz bin/feed-ingester

cli:
	docker-compose run baaz /bin/bash