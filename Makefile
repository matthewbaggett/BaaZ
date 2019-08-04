setup:
	composer update --ignore-platform-reqs

clean:
	sudo chown $(USER):$(USER) . -R
	docker-compose down --remove-orphans
	chmod +x *.runit bin/*
	-vendor/bin/php-cs-fixer fix

build:
	composer install --ignore-platform-reqs
	docker-compose build --pull

push:
	docker-compose push

up:
	docker-compose up -d \
		traefik \
		worker-feed \
		worker-images \
		worker-solr

test:
	-vendor/bin/php-cs-fixer fix
	vendor/bin/phpcs --warning-severity=6 --standard=PSR2 src tests
	vendor/bin/phpunit

worker-feed:
	docker-compose run baaz bin/feed-ingester

cli:
	docker-compose run baaz /bin/bash

wipe:
	docker-compose down -v --remove-orphans;
	sudo rm -Rfv /media/fantec/docker/baaz/solr/*
	#sudo rm -Rfv /media/fantec/docker/baaz/persist/*
	sudo rm -Rfv /media/fantec/docker/baaz/db/*
	sudo rm -Rfv /media/fantec/docker/baaz/redis-backend/*
	sudo rm -Rfv /media/fantec/docker/baaz/redis-frontend/*

wipe-and-restart: clean wipe up