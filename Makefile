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

setup:
	composer update --ignore-platform-reqs

clean-code:
	chmod +x *.runit bin/*
	-vendor/bin/php-cs-fixer fix

clean-perms:
	sudo chown $(USER):$(USER) . -R
clean-docker:
	$(COMPOSE_STATEMENT) \
		down --remove-orphans

clean: clean-perms clean-docker clean-code

build:
	composer install --ignore-platform-reqs
	$(COMPOSE_STATEMENT) build --pull $(SERVICE)

push:
	$(COMPOSE_STATEMENT) push $(SERVICE)

restart:
	$(COMPOSE_STATEMENT) restart $(SERVICE)

up:
	$(COMPOSE_STATEMENT) \
		up -d \
			traefik \
			worker-feed \
			worker-images \
			worker-solr \
			worker-stats

down:
	$(COMPOSE_STATEMENT) \
		 down --remove-orphans;

logs:
	$(COMPOSE_STATEMENT) \
		 logs -f --tail=100 $(SERVICE);

ps:
	$(COMPOSE_STATEMENT) \
		 ps

redis-cli:
	$(COMPOSE_STATEMENT) \
		exec redis-backend \
		redis-cli

test:
	-vendor/bin/php-cs-fixer fix
	vendor/bin/phpcs --warning-severity=6 --standard=PSR2 src tests
	vendor/bin/phpunit

worker-feed:
	$(COMPOSE_STATEMENT) \
		run baaz bin/feed-ingester

cli-frontend:
	$(COMPOSE_STATEMENT) \
		run frontend /bin/bash

cli-backend:
	$(COMPOSE_STATEMENT) \
		run backend /bin/bash

wipe: down
	sudo rm -Rfv /media/fantec/docker/baaz/solr/*
	#sudo rm -Rfv /media/fantec/docker/baaz/persist/*
	sudo rm -Rfv /media/fantec/docker/baaz/db/*
	sudo rm -Rfv /media/fantec/docker/baaz/redis-backend/*
	sudo rm -Rfv /media/fantec/docker/baaz/redis-frontend/*

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
