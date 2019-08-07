FILE1 = docker-compose.`hostname`.yml
ifeq ($(shell test -e $(FILE1) && echo -n yes),yes)
    COMPOSE_STATEMENT = docker-compose \
    						-f docker-compose.data.yml \
    						-f docker-compose.http.yml \
    						-f docker-compose.workers.yml \
    						-f docker-compose.`hostname`.yml
else
    COMPOSE_STATEMENT = docker-compose \
    						-f docker-compose.data.yml \
    						-f docker-compose.http.yml \
    						-f docker-compose.workers.yml
endif

conf-crush:
	$(COMPOSE_STATEMENT) config > docker-compose.yml

setup:
	composer update --ignore-platform-reqs

clean-code:
	chmod +x *.runit bin/*
	-vendor/bin/php-cs-fixer fix

clean-perms:
	sudo chown $(USER):$(USER) . -R
clean-docker:
	docker-compose \
		down --remove-orphans

clean: clean-perms clean-docker clean-code

build: conf-crush
	composer install --ignore-platform-reqs
	docker-compose build --pull $(SERVICE)

push: conf-crush
	docker-compose push $(SERVICE)

stop: conf-crush
	docker-compose stop $(SERVICE)

start: conf-crush
	docker-compose start $(SERVICE)

restart: stop start

up: conf-crush
	docker-compose \
		up -d --remove-orphans \
			traefik \
			worker-feed \
			worker-images \
			worker-solr

run: conf-crush
	docker-compose \
		 up --remove-orphans $(SERVICE)

down: conf-crush
	docker-compose \
		 down --remove-orphans;

logs: conf-crush
	docker-compose \
		 logs -f --tail=100 $(SERVICE);

ps: conf-crush
	docker-compose \
		 ps

redis-cli: conf-crush
	docker-compose \
		exec redis \
		redis-cli

redis-mon: redis-monitor
redis-monitor: conf-crush
	docker-compose \
		exec redis \
		redis-cli MONITOR

test:
	-vendor/bin/php-cs-fixer fix
	vendor/bin/phpcs --warning-severity=6 --standard=PSR2 src tests
	vendor/bin/phpunit

worker-feed: conf-crush
	docker-compose \
		run baaz bin/feed-ingester

cli-frontend: conf-crush
	docker-compose \
		run frontend /bin/bash

cli-backend: conf-crush
	docker-compose \
		run backend /bin/bash

wipe: down
	sudo rm -Rfv /media/fantec/docker/baaz/solr/*
	#sudo rm -Rfv /media/fantec/docker/baaz/persist/*
	sudo rm -Rfv /media/fantec/docker/baaz/db/*
	sudo rm -Rfv /media/fantec/docker/baaz/redis/*
	sudo rm -Rfv /media/fantec/docker/baaz/redis-frontend/*
	sudo rm -Rfv ./solr/data

wipe-and-restart: clean wipe up

tilix:
	tilix --maximize --session tilix.json

ngrok:
	ngrok \
		start \
			-config ~/.ngrok2/ngrok.yml \
			-config .ngrok.yml \
				frontend \
				backend \
				ngrok

docker-purge-running:
	docker rm $(docker stop $(docker ps -aq))
