export COMPOSE_PROJECT_NAME=yii-runner-roadrunner

help:	## Display help information
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

build:	## Build an image from a docker-compose file. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker-compose -f tests/Docker/docker-compose.yml up -d --build

down:	## Stop and remove containers, networks
	docker-compose -f tests/Docker/docker-compose.yml down

start:	## Start services
	docker-compose -f tests/Docker/docker-compose.yml up -d

sh:	## Enter the container with the application
	docker exec -it yii-runner-roadrunner sh

test:	## Run tests. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker-compose -f tests/Docker/docker-compose.yml build --pull yii-runner-roadrunner
	PHP_VERSION=$(filter-out $@,$(v)) docker-compose -f tests/Docker/docker-compose.yml run yii-runner-roadrunner vendor/bin/phpunit --colors=always -v --debug
	make down
