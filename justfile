default:
	cat justfile

install:
	composer install

serve:
	php -S localhost:8000

run: serve
	


