cscheck:
	vendor/bin/ecs check src
	vendor/bin/phpstan --memory-limit=1G analyse src

csfix:
	vendor/bin/ecs check src --fix
	vendor/bin/phpstan --memory-limit=1G analyse src
