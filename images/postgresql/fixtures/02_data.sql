-- Values from daemon's Dockerfile
-- Needs `id >= 32` for non-rfc php versions
INSERT INTO public."version" ("id", "name", "command")
	VALUES (32, 'alpine-latest','/usr/bin/php -c /etc -q');
