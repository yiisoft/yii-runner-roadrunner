help:		## Display help information
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

build:		## Build an image from a docker compose file. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose up -d --build

down:		## Stop and remove containers, networks
	docker compose down

start:		## Start services
	docker compose up -d

sh:		## Enter the container with the application
	docker exec -it yii-runner-roadrunner sh

test:		## Run tests. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose build --pull yii-runner-roadrunner
	PHP_VERSION=$(filter-out $@,$(v)) docker compose run yii-runner-roadrunner vendor/bin/phpunit --colors=always --debug
	make down

mutation:	## Run mutation tests. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose build --pull yii-runner-roadrunner
	PHP_VERSION=$(filter-out $@,$(v)) docker compose run yii-runner-roadrunner vendor/bin/roave-infection-static-analysis-plugin -j2 --ignore-msi-with-no-mutations --only-covered
	make down
