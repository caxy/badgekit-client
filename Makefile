help:
	@echo "Please use \`make <target>' where <target> is one of"
	@echo "  dump-routes    to build the json routes file from yaml"

dump-routes:
	php build/dumpRoutes.php
